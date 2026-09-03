<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ShopProfile;
use App\Models\ShopReward;
use Illuminate\View\View;

/**
 * หน้าร้านสาธารณะ — ลูกค้าเปิดดูได้โดยไม่ต้องล็อกอิน
 *
 * เข้าถึงผ่าน /shop/{slug}
 * แสดงเฉพาะร้านที่ตั้งสถานะเป็น active แล้วเท่านั้น
 */
class StorefrontController extends Controller
{
    public function show(string $slug): View
    {
        $profile = ShopProfile::where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        return view('shop.storefront', [
            'profile' => $profile,
            'shop'    => $profile->node,
            'rewards' => ShopReward::where('shop_node_id', $profile->node_id)->active()->get(),
            'colors'  => $profile->themeColors(),
        ]);
    }
}
