<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrgLevel;
use App\Exceptions\RedemptionException;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPointWallet;
use App\Models\OrgNode;
use App\Models\ShopMonthlyAllowance;
use App\Models\StockBalance;
use App\Models\SystemSetting;
use App\Services\RedemptionService;
use App\Services\SecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * เคาน์เตอร์รับแลกแต้ม — สำหรับพนักงานร้านค้า
 *
 * ขั้นตอนใช้งานหน้าร้าน
 *   1. พนักงานค้นหาลูกค้าด้วยเบอร์โทร
 *   2. ระบบแสดงแต้มคงเหลือแยกตามร้านผู้ออก
 *   3. เลือกว่าจะแลกเป็นอะไร (เงินสด/สินค้า/บริการ/ส่วนลด)
 *   4. ยืนยัน -> ตัดแต้มลูกค้า + ตัดวงเงินร้าน + ตัดสต๊อก (ถ้าเป็นสินค้า)
 *
 * ความปลอดภัย
 *   - ต้องมี ability 'accept-redeem'
 *   - แลกได้เฉพาะร้านที่ตัวเองสังกัดเท่านั้น
 *   - ทุกการปฏิเสธถูกบันทึกไว้ตรวจสอบ
 */
class RedeemDeskController extends Controller
{
    public function __construct(
        private RedemptionService $redemption,
        private SecurityService $security,
    ) {
    }

    /** หน้าเคาน์เตอร์ */
    public function index(Request $request): View
    {
        $this->authorizeRedeem();

        $shop = $this->currentShop();
        $allowance = $this->currentAllowance($shop);

        $customer = null;
        $wallets = collect();

        if ($phone = $request->query('phone')) {
            $customer = Customer::where('phone', $phone)->first();

            if ($customer) {
                $wallets = CustomerPointWallet::with('issuer')
                    ->where('customer_id', $customer->id)
                    ->where('balance', '>', 0)
                    ->orderByDesc('balance')
                    ->get();
            }
        }

        return view('redeem.desk', [
            'shop'       => $shop,
            'allowance'  => $allowance,
            'customer'   => $customer,
            'wallets'    => $wallets,
            'searched'   => (bool) $request->query('phone'),
            'pointValue' => (float) SystemSetting::get('point_value_baht', 0.25),
            'stock'      => $this->availableStock($shop),
            'recent'     => $this->recentRedemptions($shop),
        ]);
    }

    /** ยืนยันการแลก */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRedeem();

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'wallet_id'   => ['required', 'integer', 'exists:customer_point_wallets,id'],
            'points'      => ['required', 'integer', 'min:1', 'max:1000000'],
            'redeem_type' => ['required', 'in:cash,goods,service,discount'],
            'reward_name' => ['required', 'string', 'max:200'],
            // เฉพาะการแลกสินค้า
            'items'                => ['nullable', 'array', 'max:20'],
            'items.*.product_id'   => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.lot_id'       => ['nullable', 'integer', 'exists:product_lots,id'],
            'items.*.qty'          => ['required_with:items', 'integer', 'min:1', 'max:1000'],
        ], [
            'points.min'       => 'จำนวนแต้มต้องมากกว่า 0',
            'reward_name.required' => 'กรุณาระบุว่าแลกอะไร',
        ]);

        $shop = $this->currentShop();
        $customer = Customer::findOrFail($data['customer_id']);

        // กระเป๋าต้องเป็นของลูกค้าคนนี้จริง — กันยิง request ตรงเพื่อใช้แต้มคนอื่น
        $wallet = CustomerPointWallet::where('id', $data['wallet_id'])
            ->where('customer_id', $customer->id)
            ->first();

        if (! $wallet) {
            $this->security->log(
                SecurityService::E_DATA_TAMPER,
                'พยายามใช้กระเป๋าแต้มที่ไม่ใช่ของลูกค้าคนนั้น',
                'high',
                ['customer_id' => $customer->id, 'wallet_id' => $data['wallet_id']],
            );

            return back()->withErrors(['points' => 'ข้อมูลกระเป๋าแต้มไม่ถูกต้อง'])->withInput();
        }

        $issuer = OrgNode::findOrFail($wallet->issuer_node_id);

        try {
            $redemption = $this->redemption->redeem(
                customer: $customer,
                issuerNode: $issuer,
                acceptingNode: $shop,
                points: (int) $data['points'],
                rewardName: $data['reward_name'],
                redeemType: $data['redeem_type'],
                items: $data['items'] ?? [],
                confirmedBy: $request->user()->id,
            );
        } catch (RedemptionException $e) {
            return back()
                ->withErrors(['points' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('redeem.receipt', $redemption)
            ->with('status', 'แลกแต้มสำเร็จ');
    }

    /** ใบเสร็จหลังแลก */
    public function receipt(Request $request, int $redemption): View
    {
        $this->authorizeRedeem();

        $shop = $this->currentShop();

        $record = DB::table('point_redemptions')
            ->where('id', $redemption)
            ->where('accepting_node_id', $shop->id)   // ดูได้เฉพาะของร้านตัวเอง
            ->first();

        abort_if(! $record, 404);

        $items = DB::table('redemption_items')
            ->where('redemption_id', $redemption)
            ->get();

        $customer = Customer::find($record->customer_id);

        return view('redeem.receipt', [
            'r'         => $record,
            'items'     => $items,
            'customer'  => $customer,
            'shop'      => $shop,
            'allowance' => $this->currentAllowance($shop),
        ]);
    }

    /** ประวัติการรับแลกของร้าน */
    public function history(Request $request): View
    {
        $this->authorizeRedeem();

        $shop = $this->currentShop();

        $query = DB::table('point_redemptions as r')
            ->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')
            ->where('r.accepting_node_id', $shop->id)
            ->select('r.*', 'c.name as customer_name', 'c.phone as customer_phone');

        if ($from = $request->query('from')) {
            $query->whereDate('r.redeemed_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('r.redeemed_at', '<=', $to);
        }

        if ($type = $request->query('type')) {
            $query->where('r.redeem_type', $type);
        }

        $rows = $query->orderByDesc('r.redeemed_at')->paginate(30)->withQueryString();

        return view('redeem.history', [
            'shop'      => $shop,
            'rows'      => $rows,
            'allowance' => $this->currentAllowance($shop),
            'summary'   => $this->periodSummary($shop),
        ]);
    }

    // ────────────────────────────────────────────────────────────

    /** ตรวจสิทธิ์รับแลกแต้ม */
    private function authorizeRedeem(): void
    {
        abort_unless(auth()->user()?->hasAbility('accept-redeem'), 403,
            'คุณไม่มีสิทธิ์รับแลกแต้ม');
    }

    /**
     * ร้านของผู้ใช้ที่ล็อกอินอยู่
     * ผู้ขายใช้ร้านแม่ เพราะสมาชิกและวงเงินผูกไว้ที่ระดับร้าน
     */
    private function currentShop(): OrgNode
    {
        $node = auth()->user()?->node;

        abort_unless($node, 403, 'บัญชีของคุณยังไม่ได้ผูกกับหน่วยงาน');

        // ระดับผู้ขาย (6) ให้ใช้ร้านแม่
        // หมายเหตุ: level_id ถูก cast เป็น enum OrgLevel จึงต้องอ่าน ->value
        $level = $node->level_id instanceof OrgLevel
            ? $node->level_id->value
            : (int) $node->level_id;

        if ($level === OrgLevel::Seller->value && $node->parent_id) {
            return OrgNode::findOrFail($node->parent_id);
        }

        return $node;
    }

    /** วงเงินเดือนปัจจุบันของร้าน */
    private function currentAllowance(OrgNode $shop): ?ShopMonthlyAllowance
    {
        return ShopMonthlyAllowance::with('subscription')
            ->where('shop_node_id', $shop->id)
            ->where('period_ym', now()->format('Y-m'))
            ->first();
    }

    /** สินค้าที่ร้านมีให้แลก (กรองของหมดอายุออก เรียงแบบ FEFO) */
    private function availableStock(OrgNode $shop)
    {
        return DB::table('stock_balances as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin('product_lots as l', 'l.id', '=', 'b.lot_id')
            ->where('b.org_node_id', $shop->id)
            ->whereRaw('b.qty_on_hand - b.qty_reserved > 0')
            ->where(function ($q) {
                $q->whereNull('l.expiry_date')
                  ->orWhereDate('l.expiry_date', '>=', now()->toDateString());
            })
            ->select(
                'p.id as product_id', 'p.sku', 'p.name as product_name',
                'l.id as lot_id', 'l.lot_no', 'l.expiry_date',
                DB::raw('(b.qty_on_hand - b.qty_reserved) as qty_available'),
            )
            ->orderByRaw('l.expiry_date IS NULL, l.expiry_date')
            ->limit(100)
            ->get();
    }

    /** รายการแลกล่าสุดของร้าน */
    private function recentRedemptions(OrgNode $shop)
    {
        return DB::table('point_redemptions as r')
            ->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')
            ->where('r.accepting_node_id', $shop->id)
            ->where('r.status', 'confirmed')
            ->select('r.code', 'r.reward_name', 'r.points_used', 'r.redeemed_at',
                     'c.name as customer_name', 'c.phone as customer_phone')
            ->orderByDesc('r.redeemed_at')
            ->limit(8)
            ->get();
    }

    /** สรุปยอดเดือนนี้ */
    private function periodSummary(OrgNode $shop): array
    {
        // ใช้ช่วงวันที่แทน DATE_FORMAT เพื่อให้ทำงานได้ทั้ง MySQL และ SQLite
        // (DATE_FORMAT เป็นฟังก์ชันเฉพาะ MySQL) และยังใช้ index ได้ด้วย
        $row = DB::table('point_redemptions')
            ->where('accepting_node_id', $shop->id)
            ->where('status', 'confirmed')
            ->whereBetween('redeemed_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(points_used),0) as pts, COALESCE(SUM(cash_value),0) as amt')
            ->first();

        return [
            'count'  => (int) ($row->cnt ?? 0),
            'points' => (int) ($row->pts ?? 0),
            'amount' => (float) ($row->amt ?? 0),
        ];
    }
}
