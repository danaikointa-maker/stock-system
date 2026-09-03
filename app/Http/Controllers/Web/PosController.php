<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrgLevel;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\OrgNode;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** หน้าขายหน้าร้าน (POS) สำหรับร้านค้า (Lv5) และผู้ขาย (Lv6) */
class PosController extends Controller
{
    public function __construct(private SaleService $sales) {}

    /** หน้าเปิดบิลขาย */
    public function index(Request $request): View
    {
        $this->authorize('create', Sale::class);

        $user = $request->user();
        $node = $this->resolveNode($request, $user);

        return view('pos.index', [
            'node'        => $node,
            'nodes'       => $this->sellableNodes($user),
            'items'       => $this->availableStock($node),
            'recentSales' => Sale::with('items')
                ->where('org_node_id', $node->id)
                ->latest('sold_at')
                ->limit(8)
                ->get(),
            'todayTotal'  => (float) Sale::where('org_node_id', $node->id)
                ->where('status', 'completed')
                ->whereDate('sold_at', today())
                ->sum('total'),
            'todayBills'  => Sale::where('org_node_id', $node->id)
                ->where('status', 'completed')
                ->whereDate('sold_at', today())
                ->count(),
        ]);
    }

    /** บันทึกบิลขาย */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Sale::class);

        $data = $request->validate([
            'org_node_id'        => ['required', 'integer', 'exists:org_nodes,id'],
            'customer_phone'     => ['nullable', 'string', 'max:30'],
            'payment_method'     => ['required', 'in:cash,transfer,qr,credit'],
            'discount'           => ['nullable', 'numeric', 'min:0'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty'        => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ], [], [
            'items'          => 'รายการสินค้า',
            'payment_method' => 'วิธีชำระเงิน',
        ]);

        $node = OrgNode::findOrFail($data['org_node_id']);
        $this->authorize('sellAs', [Sale::class, $node]);

        // ผูกลูกค้าจากเบอร์โทร (ถ้ากรอก) เพื่อให้สะสมคะแนนได้
        $customerId = null;
        if (! empty($data['customer_phone'])) {
            $customerId = Customer::firstOrCreate(
                ['phone' => $data['customer_phone']],
                ['referred_by_node_id' => $node->id]
            )->id;
        }

        try {
            $sale = $this->sales->create(
                node: $node,
                items: $data['items'],
                customerId: $customerId,
                paymentMethod: $data['payment_method'],
                billDiscount: (float) ($data['discount'] ?? 0),
            );
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['pos' => $e->getMessage()]);
        }

        return redirect()->route('pos.receipt', $sale)
            ->with('status', "บันทึกบิล {$sale->doc_no} เรียบร้อย");
    }

    /** ใบเสร็จ */
    public function receipt(Request $request, Sale $sale): View
    {
        $this->authorize('view', $sale);

        return view('pos.receipt', [
            'sale' => $sale->load(['items.product', 'node', 'customer']),
        ]);
    }

    /** ประวัติการขาย */
    public function history(Request $request): View
    {
        $this->authorize('viewAny', Sale::class);

        $sales = Sale::with(['node:id,code,name', 'customer:id,phone,name'])
            ->whereIn('org_node_id', $request->user()->visibleNodeIds())
            ->when($request->q, fn ($q, $s) => $q->where('doc_no', 'like', "%$s%"))
            ->when($request->from, fn ($q, $d) => $q->where('sold_at', '>=', $d . ' 00:00:00'))
            ->when($request->to, fn ($q, $d) => $q->where('sold_at', '<=', $d . ' 23:59:59'))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest('sold_at')
            ->paginate(25)
            ->withQueryString();

        return view('pos.history', ['sales' => $sales]);
    }

    /** ยกเลิกบิล (คืนของเข้าสต๊อก) */
    public function void(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('void', $sale);

        $reason = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ])['reason'] ?? null;

        try {
            $this->sales->void($sale, $reason);
        } catch (\Throwable $e) {
            return back()->withErrors(['pos' => $e->getMessage()]);
        }

        return back()->with('status', "ยกเลิกบิล {$sale->doc_no} และคืนสินค้าเข้าสต๊อกแล้ว");
    }

    // ---------------- helpers ----------------

    /** หน่วยงานที่ผู้ใช้ขายในนามได้ (ร้านค้า/ผู้ขาย ในสายงานตัวเอง) */
    private function sellableNodes($user)
    {
        return OrgNode::whereIn('id', $user->visibleNodeIds())
            ->whereIn('level_id', [OrgLevel::Shop->value, OrgLevel::Seller->value])
            ->where('status', 'active')
            ->orderBy('path')
            ->get(['id', 'code', 'name', 'level_id', 'path', 'depth']);
    }

    private function resolveNode(Request $request, $user): OrgNode
    {
        $nodes = $this->sellableNodes($user);

        abort_if($nodes->isEmpty(), 403, 'บัญชีของคุณไม่มีหน่วยงานที่เปิดบิลขายได้');

        if ($request->filled('node')) {
            $picked = $nodes->firstWhere('id', (int) $request->node);
            if ($picked) {
                return $picked;
            }
        }

        // ค่าเริ่มต้น: หน่วยงานของตัวเองถ้าขายได้ ไม่งั้นตัวแรกในรายการ
        return $nodes->firstWhere('id', $user->org_node_id) ?? $nodes->first();
    }

    /** สินค้าที่มีของพร้อมขายในหน่วยงานนี้ (รวมทุกล็อต) */
    private function availableStock(OrgNode $node)
    {
        return StockBalance::with('product:id,sku,name,retail_price,points_per_unit')
            ->where('org_node_id', $node->id)
            ->selectRaw('MIN(id) as id, org_node_id, product_id,
                         SUM(qty_on_hand) as qty_on_hand,
                         SUM(qty_reserved) as qty_reserved,
                         0 as qty_in_transit, 0 as reorder_point')
            ->groupBy('org_node_id', 'product_id')
            ->havingRaw('SUM(qty_on_hand) - SUM(qty_reserved) > 0')
            ->get()
            ->filter(fn ($b) => $b->product !== null)
            ->values();
    }
}
