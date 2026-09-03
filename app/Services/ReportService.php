<?php

namespace App\Services;

use App\Enums\QrStatus;
use App\Models\OrgNode;
use App\Models\ProductQrcode;
use App\Models\QrScanLog;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * รายงานทั้งหมด — ทุกเมธอดจำกัดขอบเขตด้วย $user->visibleNodeIds() เสมอ
 * ผู้ใช้จะเห็นได้เฉพาะข้อมูลของหน่วยงานตัวเองและลูกหลาน
 */
class ReportService
{
    /** ตัวเลขสรุปบนหัว Dashboard */
    public function dashboardKpi(User $user): array
    {
        $nodes = $user->visibleNodeIds();
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $salesToday = Sale::whereIn('org_node_id', $nodes)
            ->where('status', 'completed')
            ->where('sold_at', '>=', $today);

        $salesMonth = Sale::whereIn('org_node_id', $nodes)
            ->where('status', 'completed')
            ->where('sold_at', '>=', $monthStart);

        return [
            'stock_qty'        => (int) StockBalance::whereIn('org_node_id', $nodes)->sum('qty_on_hand'),
            'stock_value'      => (float) StockBalance::whereIn('org_node_id', $nodes)
                                    ->join('products', 'products.id', '=', 'stock_balances.product_id')
                                    ->sum(DB::raw('stock_balances.qty_on_hand * products.cost_price')),
            'sales_today'      => (float) $salesToday->sum('total'),
            'bills_today'      => (clone $salesToday)->count(),
            'sales_month'      => (float) $salesMonth->sum('total'),
            'low_stock_count'  => StockBalance::whereIn('org_node_id', $nodes)->lowStock()->count(),
            'pending_approve'  => Transfer::whereIn('from_node_id', $nodes)
                                    ->where('status', 'pending_approve')->count(),
            'pending_receive'  => Transfer::whereIn('to_node_id', $nodes)
                                    ->where('status', 'shipped')->count(),
            'member_count'     => User::whereIn('org_node_id', $nodes)
                                    ->where('id', '!=', $user->id)->count(),
            'child_node_count' => OrgNode::whereIn('id', $nodes)
                                    ->where('id', '!=', $user->org_node_id)->count(),
            'scans_month'      => QrScanLog::whereIn('org_node_id', $nodes)
                                    ->where('result', 'success')
                                    ->where('scanned_at', '>=', $monthStart)->count(),
            'points_month'     => (int) QrScanLog::whereIn('org_node_id', $nodes)
                                    ->where('scanned_at', '>=', $monthStart)->sum('points_awarded'),
        ];
    }

    /** สินค้าใกล้หมด */
    public function lowStock(User $user, int $limit = 20): Collection
    {
        return StockBalance::with(['product:id,sku,name', 'node:id,code,name'])
            ->whereIn('org_node_id', $user->visibleNodeIds())
            ->lowStock()
            ->orderByRaw('qty_on_hand - reorder_point ASC')
            ->limit($limit)
            ->get();
    }

    /** ใบโอนที่รอเรารับของ */
    public function pendingIncoming(User $user): Collection
    {
        return Transfer::with(['fromNode:id,code,name'])
            ->whereIn('to_node_id', $user->visibleNodeIds())
            ->where('status', 'shipped')
            ->latest('shipped_at')
            ->limit(10)
            ->get();
    }

    /** ใบโอนที่รอเราอนุมัติ */
    public function pendingApproval(User $user): Collection
    {
        return Transfer::with(['toNode:id,code,name'])
            ->whereIn('from_node_id', $user->visibleNodeIds())
            ->where('status', 'pending_approve')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    /** ยอดขายรายวันย้อนหลัง N วัน (สำหรับกราฟ) */
    public function salesTrend(User $user, int $days = 14): Collection
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = Sale::whereIn('org_node_id', $user->visibleNodeIds())
            ->where('status', 'completed')
            ->where('sold_at', '>=', $from)
            ->selectRaw('DATE(sold_at) as d, SUM(total) as total, COUNT(*) as bills')
            ->groupBy('d')
            ->pluck('total', 'd');

        // เติมวันที่ไม่มียอดให้เป็น 0
        return collect(range(0, $days - 1))->map(function ($i) use ($from, $rows) {
            $date = $from->copy()->addDays($i)->toDateString();

            return ['date' => $date, 'total' => (float) ($rows[$date] ?? 0)];
        });
    }

    /** ผลงานของหน่วยงานลูกโดยตรง — ใช้เปรียบเทียบตัวแทน/ร้านค้า */
    public function childNodePerformance(User $user, ?string $from = null, ?string $to = null): Collection
    {
        $node = $user->node;

        if (! $node) {
            return collect();
        }

        // ต้องดึง path/depth มาด้วย มิฉะนั้น subtreeIds() จะคำนวณผิด (ได้เฉพาะตัวเอง)
        $children = OrgNode::where('parent_id', $node->id)
            ->get(['id', 'parent_id', 'code', 'name', 'level_id', 'path', 'depth']);

        if ($children->isEmpty()) {
            return collect();
        }

        $from ??= now()->startOfMonth()->toDateString();
        $to ??= now()->toDateString();

        return $children->map(function (OrgNode $child) use ($from, $to) {
            $subtree = $child->subtreeIds();

            return [
                'node'        => $child,
                'sales'       => (float) Sale::whereIn('org_node_id', $subtree)
                                    ->where('status', 'completed')
                                    ->whereBetween('sold_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                                    ->sum('total'),
                'bills'       => Sale::whereIn('org_node_id', $subtree)
                                    ->where('status', 'completed')
                                    ->whereBetween('sold_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                                    ->count(),
                'stock_qty'   => (int) StockBalance::whereIn('org_node_id', $subtree)->sum('qty_on_hand'),
                'sub_nodes'   => count($subtree) - 1,
                'members'     => User::whereIn('org_node_id', $subtree)->count(),
            ];
        })->sortByDesc('sales')->values();
    }

    /** รายงานสต๊อกคงเหลือแยกตามหน่วยงาน */
    public function stockByNode(User $user, ?int $productId = null): Collection
    {
        return collect(DB::table('v_stock_summary')
            ->whereIn('node_id', $user->visibleNodeIds())
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->orderBy('node_code')->orderBy('sku')
            ->get());
    }

    /** รายงานสินค้าขายดี */
    public function topProducts(User $user, string $from, string $to, int $limit = 20): Collection
    {
        return collect(DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereIn('sales.org_node_id', $user->visibleNodeIds())
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->selectRaw('products.sku, products.name,
                         SUM(sale_items.qty) as qty,
                         SUM(sale_items.line_total) as revenue')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get());
    }

    /** การ์ดสินค้า / ความเคลื่อนไหวสต๊อก */
    public function movementReport(User $user, array $filters = [])
    {
        return StockMovement::with(['product:id,sku,name', 'node:id,code,name', 'lot:id,lot_no'])
            ->whereIn('org_node_id', $user->visibleNodeIds())
            ->when($filters['node_id'] ?? null, fn ($q, $v) => $q->where('org_node_id', $v))
            ->when($filters['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v . ' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v . ' 23:59:59'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();
    }

    /** รายงาน QR & คะแนน */
    public function qrReport(User $user, string $from, string $to): array
    {
        $nodes = $user->visibleNodeIds();
        $range = [$from . ' 00:00:00', $to . ' 23:59:59'];

        $byResult = QrScanLog::whereIn('org_node_id', $nodes)
            ->whereBetween('scanned_at', $range)
            ->groupBy('result')
            ->selectRaw('result, COUNT(*) as c')
            ->pluck('c', 'result');

        $daily = QrScanLog::whereIn('org_node_id', $nodes)
            ->where('result', 'success')
            ->whereBetween('scanned_at', $range)
            ->selectRaw('DATE(scanned_at) as d, COUNT(*) as scans, SUM(points_awarded) as pts')
            ->groupBy('d')->orderBy('d')
            ->get();

        return [
            'by_result'     => $byResult,
            'daily'         => $daily,
            'total_scans'   => (int) $byResult->sum(),
            'success_scans' => (int) ($byResult['success'] ?? 0),
            'total_points'  => (int) QrScanLog::whereIn('org_node_id', $nodes)
                                    ->whereBetween('scanned_at', $range)->sum('points_awarded'),
            'qr_status'     => ProductQrcode::whereIn('current_node_id', $nodes)
                                    ->groupBy('status')->selectRaw('status, COUNT(*) as c')
                                    ->pluck('c', 'status'),
            // สแกน QR ที่ยังไม่ถูกขาย = ของอาจหลุดจากคลัง
            'suspicious'    => QrScanLog::with('qrcode.product:id,name')
                                    ->whereIn('org_node_id', $nodes)
                                    ->whereBetween('scanned_at', $range)
                                    ->whereIn('result', ['already_used', 'invalid'])
                                    ->latest('scanned_at')->limit(20)->get(),
        ];
    }

    /** รายงานยอดขายแยกตามหน่วยงาน (ทั้ง subtree) */
    public function salesByNode(User $user, string $from, string $to): Collection
    {
        return collect(DB::table('sales')
            ->join('org_nodes', 'org_nodes.id', '=', 'sales.org_node_id')
            ->join('org_levels', 'org_levels.id', '=', 'org_nodes.level_id')
            ->whereIn('sales.org_node_id', $user->visibleNodeIds())
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->groupBy('org_nodes.id', 'org_nodes.code', 'org_nodes.name', 'org_levels.name_th')
            ->selectRaw('org_nodes.code, org_nodes.name, org_levels.name_th as level_name,
                         COUNT(*) as bills, SUM(sales.total) as revenue')
            ->orderByDesc('revenue')
            ->get());
    }
}
