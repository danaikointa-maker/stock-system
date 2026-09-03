<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPointWallet;
use App\Models\OrgNode;
use App\Models\ShopProfile;
use App\Models\ShopReward;
use App\Services\RedemptionService;
use App\Exceptions\RedemptionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * หน้า QR ร้านค้า — ลูกค้าสแกน QR ที่ติดหน้าร้าน
 *
 * Flow:
 *   1. ลูกค้าสแกน QR → เปิดหน้านี้ (public ไม่ต้อง login)
 *   2. เห็นข้อมูลร้าน + รายการของรางวัลที่แลกได้
 *   3. กรอกเบอร์โทร → ระบบแสดงแต้มที่มีในกระเป๋าร้านนี้
 *   4. เลือกของรางวัล → ยืนยันแลก → สร้างคำขอแลก (pending)
 *   5. พนักงานที่ redeem desk เห็นคำขอ → ยืนยัน/ปฏิเสธ
 *
 * หมายเหตุ: การแลกจริงต้องผ่าน redeem desk ของพนักงาน
 *           หน้านี้แค่ส่ง "คำขอ" เข้าระบบ พนักงานเป็นผู้ยืนยัน
 */
class ShopQrController extends Controller
{
    public function __construct(private RedemptionService $redemption) {}

    /** หน้าหลัก — แสดงข้อมูลร้าน + ของรางวัล */
    public function show(Request $request, string $token): View
    {
        $profile = ShopProfile::where('shop_qr_token', $token)
            ->where('status', 'active')
            ->firstOrFail();

        $shop = $profile->node;
        $rewards = ShopReward::where('shop_node_id', $shop->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // ถ้ากรอกเบอร์มาแล้ว แสดงแต้ม
        $customer = null;
        $wallet = null;

        if ($phone = $request->query('phone')) {
            $customer = Customer::where('phone', $phone)->first();

            if ($customer) {
                $wallet = CustomerPointWallet::where('customer_id', $customer->id)
                    ->where('issuer_node_id', $shop->id)
                    ->first();
            }
        }

        return view('shop.qr-landing', [
            'profile'  => $profile,
            'shop'     => $shop,
            'rewards'  => $rewards,
            'colors'   => $profile->themeColors(),
            'customer' => $customer,
            'wallet'   => $wallet,
        ]);
    }

    /** ส่งคำขอแลกของรางวัล */
    public function redeem(Request $request, string $token): RedirectResponse
    {
        $profile = ShopProfile::where('shop_qr_token', $token)
            ->where('status', 'active')
            ->firstOrFail();

        $shop = $profile->node;

        $data = $request->validate([
            'phone'       => ['required', 'string', 'regex:/^0[0-9]{8,9}$/'],
            'reward_id'   => ['required', 'integer', 'exists:shop_rewards,id'],
        ], [
            'phone.required'  => 'กรุณาระบุเบอร์โทรศัพท์',
            'phone.regex'     => 'เบอร์โทรศัพท์ไม่ถูกต้อง',
            'reward_id.required' => 'กรุณาเลือกของรางวัล',
        ]);

        $customer = Customer::firstOrCreate(
            ['phone' => $data['phone']],
            ['name' => 'ลูกค้า ' . $data['phone'], 'referred_by_node_id' => $shop->id]
        );

        $reward = ShopReward::where('id', $data['reward_id'])
            ->where('shop_node_id', $shop->id)
            ->where('is_active', true)
            ->firstOrFail();

        // ตรวจแต้มพอ
        $wallet = CustomerPointWallet::where('customer_id', $customer->id)
            ->where('issuer_node_id', $shop->id)
            ->first();

        $balance = $wallet?->balance ?? 0;

        if ($balance < $reward->points_cost) {
            return redirect()
                ->route('shop-qr.show', ['token' => $token, 'phone' => $data['phone']])
                ->withErrors(['points' => "แต้มไม่พอ (มี {$balance} แต้ม ต้องใช้ {$reward->points_cost} แต้ม)"]);
        }

        // สร้างคำขอแลก (pending) — พนักงานจะยืนยันที่ redeem desk
        try {
            $this->redemption->redeem(
                customer: $customer,
                issuerNode: $shop,
                acceptingNode: $shop,
                points: (int) $reward->points_cost,
                rewardName: $reward->name,
                redeemType: $reward->reward_type,
                items: [],
                confirmedBy: null, // พนักงานยังไม่ได้ยืนยัน
            );
        } catch (RedemptionException $e) {
            return redirect()
                ->route('shop-qr.show', ['token' => $token, 'phone' => $data['phone']])
                ->withErrors(['points' => $e->getMessage()]);
        }

        return redirect()
            ->route('shop-qr.show', ['token' => $token, 'phone' => $data['phone']])
            ->with('status', "แลก \"{$reward->name}\" สำเร็จ! แสดงหน้านี้ให้พนักงานดูเพื่อยืนยัน");
    }
}
