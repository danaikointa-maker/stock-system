<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrgNode;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function __construct(private StockService $stock) {}

    /** GET /api/stock — สต๊อกในสายงานที่ตัวเองดูแล */
    public function index(Request $request): JsonResponse
    {
        $rows = DB::table('v_stock_summary')
            ->whereIn('node_id', $request->user()->visibleNodeIds())
            ->when($request->node_id, fn ($q, $id) => $q->where('node_id', $id))
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('product_name', 'like', "%$s%")->orWhere('sku', 'like', "%$s%")))
            ->orderBy('node_code')->orderBy('sku')
            ->paginate(50);

        return response()->json($rows);
    }

    /** GET /api/stock/tree/{node} — สรุปทั้ง subtree */
    public function tree(Request $request, OrgNode $node): JsonResponse
    {
        abort_unless($request->user()->canAccessNode($node->id), 403);

        return response()->json($this->stock->subtreeSummary($node, $request->product_id));
    }

    /** GET /api/stock/low — ของใกล้หมด */
    public function lowStock(Request $request): JsonResponse
    {
        $rows = StockBalance::with(['product:id,sku,name', 'node:id,code,name'])
            ->whereIn('org_node_id', $request->user()->visibleNodeIds())
            ->lowStock()
            ->get();

        return response()->json($rows);
    }

    /** GET /api/stock/movements — การ์ดสินค้า */
    public function movements(Request $request): JsonResponse
    {
        $rows = StockMovement::with(['product:id,sku,name', 'node:id,code,name'])
            ->whereIn('org_node_id', $request->user()->visibleNodeIds())
            ->when($request->node_id, fn ($q, $id) => $q->where('org_node_id', $id))
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->from, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('created_at', '<=', $d . ' 23:59:59'))
            ->latest('id')
            ->paginate(50);

        return response()->json($rows);
    }

    /** POST /api/stock/adjust — ปรับยอดตามที่นับได้จริง */
    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'node_id'     => ['required', 'integer', 'exists:org_nodes,id'],
            'product_id'  => ['required', 'integer', 'exists:products,id'],
            'lot_id'      => ['nullable', 'integer', 'exists:product_lots,id'],
            'counted_qty' => ['required', 'integer', 'min:0'],
            'note'        => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless($request->user()->canAccessNode($data['node_id']), 403);

        $movement = DB::transaction(fn () => $this->stock->adjustTo(
            $data['node_id'], $data['product_id'], $data['counted_qty'],
            $data['lot_id'] ?? null, $data['note'] ?? null,
        ));

        return response()->json([
            'message'  => $movement ? 'ปรับยอดเรียบร้อย' : 'ยอดตรงอยู่แล้ว ไม่มีการเปลี่ยนแปลง',
            'movement' => $movement,
        ]);
    }
}
