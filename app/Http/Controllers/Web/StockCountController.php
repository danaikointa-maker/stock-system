<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OrgNode;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * นับสต๊อกจริง แล้วปรับยอดให้ตรง (stock count / cycle count)
 * ต้องมี ability `adjust-stock` และเข้าถึงหน่วยงานนั้นได้
 */
class StockCountController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $node = $this->resolveNode($request, $user);

        $this->authorize('adjust', [StockBalance::class, $node]);

        $rows = StockBalance::with(['product', 'lot'])
            ->where('org_node_id', $node->id)
            ->get()
            ->sortBy(fn ($b) => $b->product?->sku ?? '')
            ->values();

        return view('stock.count', [
            'node'    => $node,
            'nodes'   => OrgNode::whereIn('id', $user->visibleNodeIds())
                ->active()->orderBy('path')->get(),
            'rows'    => $rows,
            'recent'  => StockMovement::with(['product', 'createdBy'])
                ->where('org_node_id', $node->id)
                ->whereIn('type', ['adjust_in', 'adjust_out'])
                ->latest('id')->limit(15)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'org_node_id'       => ['required', 'integer', 'exists:org_nodes,id'],
            'note'              => ['nullable', 'string', 'max:255'],
            'counted'           => ['required', 'array', 'min:1'],
            'counted.*.balance_id' => ['required', 'integer'],
            'counted.*.qty'     => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);

        $node = OrgNode::findOrFail($data['org_node_id']);

        $this->authorize('adjust', [StockBalance::class, $node]);

        $changed = 0;
        $note = ($data['note'] ?? '') ?: 'ปรับยอดจากการนับสต๊อก';

        DB::transaction(function () use ($data, $node, $note, &$changed) {
            foreach ($data['counted'] as $row) {
                // เว้นว่าง = ไม่ได้นับรายการนี้ ข้ามไป (ไม่ใช่แปลว่านับได้ 0)
                if (! isset($row['qty']) || $row['qty'] === null || $row['qty'] === '') {
                    continue;
                }

                $balance = StockBalance::where('id', $row['balance_id'])
                    ->where('org_node_id', $node->id)
                    ->first();

                if (! $balance) {
                    continue;
                }

                $movement = $this->stock->adjustTo(
                    nodeId: $node->id,
                    productId: $balance->product_id,
                    countedQty: (int) $row['qty'],
                    lotId: $balance->lot_id,
                    note: $note,
                );

                if ($movement) {
                    $changed++;
                }
            }
        });

        return redirect()->route('stock.count', ['node' => $node->id])->with(
            'ok',
            $changed === 0
                ? 'นับเรียบร้อย — ยอดตรงทุกรายการ ไม่มีการปรับ'
                : "ปรับยอดเรียบร้อย {$changed} รายการ (บันทึกลงการ์ดสินค้าแล้ว)"
        );
    }

    private function resolveNode(Request $request, $user): OrgNode
    {
        $visible = $user->visibleNodeIds();

        if ($id = $request->query('node')) {
            abort_unless(in_array((int) $id, $visible, true), 403);

            return OrgNode::findOrFail($id);
        }

        // ถ้าหน่วยงานของผู้ใช้ไม่มีสต๊อกเลย (เช่น HQ) ให้เลือกหน่วยงานแรกในสายงานที่มีของ
        // เพื่อไม่ให้เปิดหน้ามาเจอใบนับว่างเปล่าโดยไม่จำเป็น
        $own = $user->orgNode;

        if ($own && StockBalance::where('org_node_id', $own->id)->exists()) {
            return $own;
        }

        $withStock = OrgNode::whereIn('id', $visible)
            ->whereIn('id', StockBalance::select('org_node_id')->whereIn('org_node_id', $visible))
            ->orderBy('depth')
            ->first();

        return $withStock ?? $own ?? OrgNode::whereIn('id', $visible)->orderBy('depth')->firstOrFail();
    }
}
