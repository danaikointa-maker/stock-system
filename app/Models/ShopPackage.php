<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopPackage extends Model
{
    protected $fillable = [
        'code', 'name', 'tagline', 'duration_months', 'monthly_point_limit',
        'price', 'allow_rollover', 'allow_cash_redeem', 'agent_commission_pct',
        'sort_order', 'is_active', 'note', 'created_by',
    ];

    protected $casts = [
        'allow_rollover'    => 'boolean',
        'allow_cash_redeem' => 'boolean',
        'is_active'         => 'boolean',
        'price'             => 'decimal:2',
    ];

    public function scopeActive($q) { return $q->where('is_active', true)->orderBy('sort_order'); }

    /** คอมมิชชั่นที่ตัวแทนจะได้ */
    public function commissionFor(?float $price = null): float
    {
        return round(($price ?? (float) $this->price) * (float) $this->agent_commission_pct / 100, 2);
    }
}
