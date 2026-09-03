<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** หน้าร้าน — โลโก้/ชื่อ/ธีมที่เจ้าของร้านตั้งเอง */
class ShopProfile extends Model
{
    protected $fillable = [
        'node_id', 'slug', 'shop_qr_token', 'display_name', 'tagline', 'description',
        'business_type', 'template_key', 'logo_path', 'cover_path',
        'color_primary', 'color_secondary', 'phone', 'line_id', 'address',
        'lat', 'lng', 'open_hours', 'blocks', 'gallery', 'status',
    ];

    protected $casts = [
        'open_hours' => 'array',
        'blocks'     => 'array',
        'gallery'    => 'array',
    ];

    public function node() { return $this->belongsTo(OrgNode::class, 'node_id'); }

    /** สร้างหรือดึง shop QR token */
    public function ensureQrToken(): string
    {
        if (! $this->shop_qr_token) {
            $this->shop_qr_token = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(24));
            $this->save();
        }

        return $this->shop_qr_token;
    }

    /** URL สำหรับ QR ร้านค้า — ลูกค้าสแกนแล้วเปิดหน้าแลกของรางวัล */
    public function shopQrUrl(): string
    {
        return url('/shop-qr/' . $this->ensureQrToken());
    }

    /** ชุดสีตามประเภทธุรกิจ (ใช้เมื่อร้านไม่ได้กำหนดสีเอง) */
    public function themeColors(): array
    {
        $presets = [
            'cafe'       => ['#8B5A2B', '#B8763C'],
            'restaurant' => ['#F04800', '#FF6B2B'],
            'carwash'    => ['#0A7EA4', '#22A7D0'],
            'beauty'     => ['#C2185B', '#E5487F'],
            'pharmacy'   => ['#006018', '#0C8A2C'],
            'retail'     => ['#7A6A00', '#A08C10'],
        ];

        $default = $presets[$this->business_type] ?? $presets['retail'];

        return [
            'primary'   => $this->color_primary ?: $default[0],
            'secondary' => $this->color_secondary ?: $default[1],
        ];
    }
}
