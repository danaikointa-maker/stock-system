<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ของรางวัล/บริการที่แต่ละร้านตั้งเอง
 * แสดงบนหน้าร้านสาธารณะและใช้เป็นตัวเลือกที่เคาน์เตอร์
 */
class ShopReward extends Model
{
    protected $fillable = [
        'shop_node_id', 'name', 'description', 'reward_type', 'points_cost',
        'cash_value', 'image_path', 'icon', 'product_id', 'qty_per_redeem',
        'stock_limit', 'redeemed_count', 'limit_per_customer',
        'sort_order', 'is_active', 'starts_on', 'ends_on',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'starts_on'  => 'date',
        'ends_on'    => 'date',
        'cash_value' => 'decimal:2',
    ];

    public function shop() { return $this->belongsTo(OrgNode::class, 'shop_node_id'); }
    public function product() { return $this->belongsTo(Product::class); }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('starts_on')->orWhereDate('starts_on', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_on')->orWhereDate('ends_on', '>=', now()))
            ->orderBy('sort_order');
    }

    /** เหลือให้แลกอีกกี่ชิ้น (null = ไม่จำกัด) */
    public function stockLeft(): ?int
    {
        return $this->stock_limit === null
            ? null
            : max(0, (int) $this->stock_limit - (int) $this->redeemed_count);
    }

    public function isAvailable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $left = $this->stockLeft();

        return $left === null || $left > 0;
    }

    /** อีโมจิเริ่มต้นตามประเภท ใช้เมื่อร้านไม่ได้อัปโหลดรูป */
    public function displayIcon(): string
    {
        if ($this->icon) {
            return $this->icon;
        }

        return match ($this->reward_type) {
            'goods'    => '🛍️',
            'service'  => '🔧',
            'discount' => '🎫',
            'cash'     => '💵',
            default    => '🎁',
        };
    }
}
