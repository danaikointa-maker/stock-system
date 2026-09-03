<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrgLevel;
use App\Http\Controllers\Controller;
use App\Models\OrgNode;
use App\Models\ShopProfile;
use App\Models\ShopReward;
use App\Services\SecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * ตั้งค่าหน้าร้าน — เจ้าของร้านแก้ไขเอง
 *
 * ทำอะไรได้
 *   - อัปโหลดโลโก้ / รูปปก
 *   - ตั้งชื่อร้าน คำโปรย รายละเอียด
 *   - เลือกเทมเพลตตามประเภทธุรกิจ (6 แบบ) หรือกำหนดสีเอง
 *   - เปิด/ปิดบล็อกเนื้อหาบนหน้าร้าน
 *   - จัดการรายการของรางวัลที่รับแลก
 *
 * ความปลอดภัยของการอัปโหลด
 *   - จำกัดชนิดไฟล์ด้วย mimes (ตรวจเนื้อไฟล์จริง ไม่ใช่แค่นามสกุล)
 *   - จำกัดขนาดและมิติภาพ
 *   - ตั้งชื่อไฟล์ใหม่เสมอ ไม่ใช้ชื่อจากผู้ใช้ (กัน path traversal / ไฟล์สคริปต์)
 *   - เก็บใน storage/app/public ไม่ใช่ public โดยตรง
 */
class ShopSettingController extends Controller
{
    /** ชนิดไฟล์รูปที่ยอมรับ */
    private const IMAGE_RULES = ['image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:max_width=4000,max_height=4000'];

    public function __construct(private SecurityService $security)
    {
    }

    /** หน้าตั้งค่าร้าน */
    public function edit(Request $request): View
    {
        $this->authorizeShop();

        $shop = $this->currentShop();
        $profile = $this->profileFor($shop);

        return view('shop.settings', [
            'shop'      => $shop,
            'profile'   => $profile,
            'templates' => $this->templates(),
            'rewards'   => ShopReward::where('shop_node_id', $shop->id)
                ->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    /** บันทึกข้อมูลร้าน */
    public function update(Request $request): RedirectResponse
    {
        $this->authorizeShop();

        $shop = $this->currentShop();
        $profile = $this->profileFor($shop);

        $data = $request->validate([
            'display_name'    => ['required', 'string', 'max:150'],
            'tagline'         => ['nullable', 'string', 'max:255'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'business_type'   => ['required', 'in:cafe,restaurant,carwash,beauty,pharmacy,retail,other'],
            'color_primary'   => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_secondary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'line_id'         => ['nullable', 'string', 'max:80'],
            'address'         => ['nullable', 'string', 'max:500'],
            'lat'             => ['nullable', 'numeric', 'between:-90,90'],
            'lng'             => ['nullable', 'numeric', 'between:-180,180'],
            'logo'            => array_merge(['nullable'], self::IMAGE_RULES),
            'cover'           => array_merge(['nullable'], self::IMAGE_RULES),
            'blocks'          => ['nullable', 'array'],
            'blocks.*'        => ['in:0,1'],
            'status'          => ['required', 'in:draft,active'],
        ], [
            'display_name.required' => 'กรุณาระบุชื่อร้าน',
            'color_primary.regex'   => 'รหัสสีต้องอยู่ในรูปแบบ #RRGGBB',
            'color_secondary.regex' => 'รหัสสีต้องอยู่ในรูปแบบ #RRGGBB',
            'logo.mimes'            => 'โลโก้ต้องเป็นไฟล์ jpg, png หรือ webp เท่านั้น',
            'logo.max'              => 'โลโก้ต้องมีขนาดไม่เกิน 3 MB',
            'cover.mimes'           => 'รูปปกต้องเป็นไฟล์ jpg, png หรือ webp เท่านั้น',
        ]);

        // อัปโหลดรูป (เก็บไฟล์เดิมไว้ถ้าไม่ได้ส่งมาใหม่)
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $this->storeImage($request->file('logo'), $shop->id, 'logo', $profile->logo_path);
        }

        if ($request->hasFile('cover')) {
            $data['cover_path'] = $this->storeImage($request->file('cover'), $shop->id, 'cover', $profile->cover_path);
        }

        unset($data['logo'], $data['cover']);

        // แปลงค่า checkbox เป็น boolean
        // ถ้าฟอร์มไม่ได้ส่ง blocks มาเลย ให้คงค่าเดิมไว้ (หรือใช้ค่าเริ่มต้น)
        // ไม่งั้นการบันทึกจากฟอร์มอื่นจะปิดบล็อกทั้งหมดโดยไม่ตั้งใจ
        if ($request->has('blocks')) {
            $data['blocks'] = collect($request->input('blocks', []))
                ->map(fn ($v) => (bool) $v)
                ->all();
        } else {
            $data['blocks'] = $profile->blocks ?: ['rewards' => true, 'contact' => true, 'map' => true];
        }

        // slug สร้างครั้งเดียว ไม่เปลี่ยนตามชื่อ เพื่อไม่ให้ลิงก์เดิมพัง
        if (! $profile->slug) {
            $data['slug'] = $this->uniqueSlug($data['display_name'], $shop->id);
        }

        $profile->fill($data)->save();

        return back()->with('status', 'บันทึกข้อมูลร้านเรียบร้อย');
    }

    /** เพิ่มของรางวัล */
    public function storeReward(Request $request): RedirectResponse
    {
        $this->authorizeShop();

        $shop = $this->currentShop();

        $data = $request->validate([
            'name'               => ['required', 'string', 'max:200'],
            'description'        => ['nullable', 'string', 'max:500'],
            'reward_type'        => ['required', 'in:goods,service,discount,cash'],
            'points_cost'        => ['required', 'integer', 'min:1', 'max:10000000'],
            'cash_value'         => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'icon'               => ['nullable', 'string', 'max:10'],
            'product_id'         => ['nullable', 'integer', 'exists:products,id'],
            'qty_per_redeem'     => ['nullable', 'integer', 'min:1', 'max:100'],
            'stock_limit'        => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'limit_per_customer' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'image'              => array_merge(['nullable'], self::IMAGE_RULES),
        ], [
            'name.required'        => 'กรุณาระบุชื่อของรางวัล',
            'points_cost.required' => 'กรุณาระบุจำนวนแต้มที่ใช้แลก',
            'points_cost.min'      => 'จำนวนแต้มต้องมากกว่า 0',
        ]);

        // ของรางวัลประเภทสินค้าต้องผูกกับสินค้าจริง เพื่อให้ตัดสต๊อกได้
        if ($data['reward_type'] === 'goods' && empty($data['product_id'])) {
            return back()
                ->withErrors(['product_id' => 'ของรางวัลประเภทสินค้าต้องเลือกสินค้าที่จะจ่ายออก'])
                ->withInput();
        }

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request->file('image'), $shop->id, 'reward');
        }

        unset($data['image']);

        $data['shop_node_id'] = $shop->id;
        $data['qty_per_redeem'] = $data['qty_per_redeem'] ?? 1;
        $data['sort_order'] = (int) ShopReward::where('shop_node_id', $shop->id)->max('sort_order') + 1;

        ShopReward::create($data);

        return back()->with('status', 'เพิ่มของรางวัลเรียบร้อย');
    }

    /** เปิด/ปิดของรางวัล */
    public function toggleReward(Request $request, ShopReward $reward): RedirectResponse
    {
        $this->authorizeShop();
        $this->authorizeOwnReward($reward);

        $reward->update(['is_active' => ! $reward->is_active]);

        return back()->with('status', $reward->is_active ? 'เปิดใช้ของรางวัลแล้ว' : 'ปิดของรางวัลแล้ว');
    }

    /** ลบของรางวัล */
    public function destroyReward(Request $request, ShopReward $reward): RedirectResponse
    {
        $this->authorizeShop();
        $this->authorizeOwnReward($reward);

        // ลบรูปทิ้งด้วยเพื่อไม่ให้ไฟล์ค้าง
        if ($reward->image_path) {
            Storage::disk('public')->delete($reward->image_path);
        }

        $reward->delete();

        return back()->with('status', 'ลบของรางวัลเรียบร้อย');
    }

    /** ดูตัวอย่างหน้าร้าน */
    public function preview(Request $request): View
    {
        $this->authorizeShop();

        $shop = $this->currentShop();
        $profile = $this->profileFor($shop);

        return view('shop.storefront', [
            'profile' => $profile,
            'shop'    => $shop,
            'rewards' => ShopReward::where('shop_node_id', $shop->id)->active()->get(),
            'colors'  => $profile->themeColors(),
            'isPreview' => true,
        ]);
    }

    /**
     * ดาวน์โหลด QR ร้านค้าเป็นไฟล์ SVG (สำหรับสั่งพิมพ์สติ๊กเกอร์)
     *
     * สร้าง QR Code แบบ SVG ที่ encode URL ของหน้า QR ร้านค้า
     * ลูกค้าสแกน QR นี้ → เปิดหน้าแลกของรางวัลของร้าน
     */
    public function downloadShopQr(Request $request): \Illuminate\Http\Response
    {
        $this->authorizeShop();

        $shop = $this->currentShop();
        $profile = $this->profileFor($shop);
        $url = $profile->shopQrUrl();

        // สร้าง QR Code SVG โดยใช้ Google Charts API fallback
        // สำหรับ production ควรใช้ library เช่น simplesoftwareio/simple-qrcode
        $qrSize = 300;
        $shopName = e($profile->display_name);
        $qrData = urlencode($url);

        $svg = $this->generateQrSvg($url, $qrSize, $shopName);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="shop-qr-' . ($profile->slug ?: $shop->id) . '.svg"',
        ]);
    }

    /** สร้าง QR Code เป็น SVG โดยใช้ API ภายนอก */
    private function generateQrSvg(string $data, int $size, string $label): string
    {
        // สร้าง QR matrix แบบง่าย (ใช้ API สำหรับ demo)
        // สำหรับ production ควร install: composer require simplesoftwareio/simple-qrcode
        $encodedData = urlencode($data);
        $qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedData}&format=svg";

        // สร้าง SVG ที่ประกอบด้วย QR image + label
        $labelEscaped = htmlspecialchars($label, ENT_XML1, 'UTF-8');
        $dataEscaped = htmlspecialchars($data, ENT_XML1, 'UTF-8');

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
     width="{$size}" height="{$size}">
  <rect width="100%" height="100%" fill="white"/>
  <!-- QR Data: {$dataEscaped} -->
  <!-- Shop: {$labelEscaped} -->
  <!-- เปิด URL นี้เพื่อใช้ QR: {$dataEscaped} -->
  <image xlink:href="{$qrImageUrl}" width="{$size}" height="{$size}"/>
</svg>
SVG;
    }

    // ────────────────────────────────────────────────────────────

    private function authorizeShop(): void
    {
        abort_unless(auth()->user()?->hasAbility('manage-shop'), 403,
            'คุณไม่มีสิทธิ์ตั้งค่าหน้าร้าน');
    }

    /** ของรางวัลต้องเป็นของร้านตัวเองเท่านั้น */
    private function authorizeOwnReward(ShopReward $reward): void
    {
        if ($reward->shop_node_id !== $this->currentShop()->id) {
            $this->security->log(
                SecurityService::E_PERMISSION_DENIED,
                'พยายามแก้ไขของรางวัลของร้านอื่น',
                'high',
                ['reward_id' => $reward->id, 'owner' => $reward->shop_node_id],
            );

            abort(404);
        }
    }

    private function currentShop(): OrgNode
    {
        $node = auth()->user()?->node;

        abort_unless($node, 403, 'บัญชีของคุณยังไม่ได้ผูกกับหน่วยงาน');

        $level = $node->level_id instanceof OrgLevel
            ? $node->level_id->value
            : (int) $node->level_id;

        if ($level === OrgLevel::Seller->value && $node->parent_id) {
            return OrgNode::findOrFail($node->parent_id);
        }

        return $node;
    }

    private function profileFor(OrgNode $shop): ShopProfile
    {
        return ShopProfile::firstOrNew(['node_id' => $shop->id], [
            'display_name'  => $shop->name,
            'business_type' => 'retail',
            'template_key'  => 'retail',
            'status'        => 'draft',
        ]);
    }

    /**
     * บันทึกรูปที่อัปโหลด
     *
     * ตั้งชื่อไฟล์ใหม่เสมอด้วยค่าสุ่ม — ไม่ใช้ชื่อเดิมจากผู้ใช้เด็ดขาด
     * เพราะชื่อไฟล์จากภายนอกอาจมี ../ หรือ .php แฝงมาได้
     */
    private function storeImage(UploadedFile $file, int $shopId, string $kind, ?string $oldPath = null): string
    {
        $ext = strtolower($file->getClientOriginalExtension());

        // ยืนยันอีกชั้นว่านามสกุลอยู่ในรายการที่อนุญาต
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            abort(422, 'ชนิดไฟล์ไม่ถูกต้อง');
        }

        $name = sprintf('%s-%s.%s', $kind, Str::random(24), $ext);
        $path = $file->storeAs("shops/{$shopId}", $name, 'public');

        // ลบไฟล์เก่าเพื่อไม่ให้พื้นที่บวม
        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return $path;
    }

    /** สร้าง slug ที่ไม่ซ้ำ */
    private function uniqueSlug(string $name, int $shopId): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;
        $i = 1;

        while (ShopProfile::where('slug', $slug)->where('node_id', '!=', $shopId)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }

    /** เทมเพลต 6 แบบตามประเภทธุรกิจ */
    private function templates(): array
    {
        return [
            'cafe'       => ['name' => 'ร้านกาแฟ / เครื่องดื่ม', 'icon' => '☕', 'colors' => ['#8B5A2B', '#B8763C']],
            'restaurant' => ['name' => 'ร้านอาหาร',              'icon' => '🍜', 'colors' => ['#F04800', '#FF6B2B']],
            'carwash'    => ['name' => 'ล้างรถ / คาร์แคร์',       'icon' => '🚗', 'colors' => ['#0A7EA4', '#22A7D0']],
            'beauty'     => ['name' => 'เสริมสวย / ตัดผม',        'icon' => '💇', 'colors' => ['#C2185B', '#E5487F']],
            'pharmacy'   => ['name' => 'ร้านยา',                  'icon' => '💊', 'colors' => ['#006018', '#0C8A2C']],
            'retail'     => ['name' => 'ค้าปลีก / มินิมาร์ท',     'icon' => '🏪', 'colors' => ['#7A6A00', '#A08C10']],
            'other'      => ['name' => 'อื่น ๆ',                  'icon' => '🏬', 'colors' => ['#455A64', '#607D8B']],
        ];
    }
}
