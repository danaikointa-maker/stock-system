<?php

namespace App\Http\Controllers\Web;

use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Models\OrgNode;
use App\Models\Product;
use App\Services\ReportService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

/**
 * หมายเหตุ: Laravel 11+ ไม่รองรับ $this->middleware() ใน constructor แล้ว
 * จึงใช้ HasMiddleware interface แทน
 */
class ReportController extends Controller implements HasMiddleware
{
    public function __construct(private ReportService $reports) {}

    public static function middleware(): array
    {
        return ['can:view-reports'];
    }

    /** สรุปภาพรวม */
    public function summary(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $user = $request->user();

        return view('reports.summary', [
            'from'        => $from,
            'to'          => $to,
            'kpi'         => $this->reports->dashboardKpi($user),
            'salesByNode' => $this->reports->salesByNode($user, $from, $to),
            'topProducts' => $this->reports->topProducts($user, $from, $to),
            'children'    => $this->reports->childNodePerformance($user, $from, $to),
        ]);
    }

    /** รายงานสต๊อกคงเหลือ */
    public function stock(Request $request): View
    {
        return view('reports.stock', [
            'rows'     => $this->reports->stockByNode($request->user(), $request->product_id),
            'products' => Product::orderBy('name')->get(['id', 'sku', 'name']),
            'lowStock' => $this->reports->lowStock($request->user(), 100),
        ]);
    }

    /** การ์ดสินค้า */
    public function movements(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('reports.movements', [
            'from'     => $from,
            'to'       => $to,
            'rows'     => $this->reports->movementReport($request->user(), [
                'node_id'    => $request->node_id,
                'product_id' => $request->product_id,
                'type'       => $request->type,
                'from'       => $from,
                'to'         => $to,
            ]),
            'nodes'    => OrgNode::whereIn('id', $request->user()->visibleNodeIds())
                            ->orderBy('path')->get(['id', 'code', 'name']),
            'products' => Product::orderBy('name')->get(['id', 'sku', 'name']),
            'types'    => MovementType::cases(),
        ]);
    }

    /** รายงาน QR และคะแนนสะสม */
    public function qr(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('reports.qr', [
            'from' => $from,
            'to'   => $to,
        ] + $this->reports->qrReport($request->user(), $from, $to));
    }

    /** ส่งออก CSV */
    public function export(Request $request, string $type): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $user = $request->user();

        [$filename, $header, $rows] = match ($type) {
            'sales' => [
                "sales_{$from}_{$to}.csv",
                ['รหัสหน่วยงาน', 'ชื่อ', 'ระดับ', 'จำนวนบิล', 'ยอดขาย'],
                $this->reports->salesByNode($user, $from, $to)
                    ->map(fn ($r) => [$r->code, $r->name, $r->level_name, $r->bills, $r->revenue]),
            ],
            'stock' => [
                'stock_' . now()->toDateString() . '.csv',
                ['หน่วยงาน', 'ระดับ', 'SKU', 'สินค้า', 'คงเหลือ', 'จอง', 'ใช้ได้', 'ระหว่างทาง'],
                $this->reports->stockByNode($user)
                    ->map(fn ($r) => [$r->node_code, $r->level_name, $r->sku, $r->product_name,
                        $r->on_hand, $r->reserved, $r->available, $r->in_transit]),
            ],
            'products' => [
                "top_products_{$from}_{$to}.csv",
                ['SKU', 'สินค้า', 'จำนวนที่ขาย', 'ยอดเงิน'],
                $this->reports->topProducts($user, $from, $to, 500)
                    ->map(fn ($r) => [$r->sku, $r->name, $r->qty, $r->revenue]),
            ],
            default => abort(404),
        };

        $callback = function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM ให้ Excel อ่านภาษาไทยได้
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, (array) $row);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function range(Request $request): array
    {
        return [
            $request->input('from', now()->startOfMonth()->toDateString()),
            $request->input('to', now()->toDateString()),
        ];
    }
}
