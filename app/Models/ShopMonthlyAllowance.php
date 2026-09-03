<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopMonthlyAllowance extends Model
{
    protected $fillable = [
        'subscription_id', 'shop_node_id', 'period_ym', 'limit_points',
        'rollover_points', 'topup_points', 'used_points', 'remaining_points',
        'redemption_count', 'low_alerted_at', 'exhausted_at',
    ];

    protected $casts = [
        'low_alerted_at' => 'datetime',
        'exhausted_at'   => 'datetime',
    ];

    public function subscription() { return $this->belongsTo(ShopSubscription::class, 'subscription_id'); }
    public function shop() { return $this->belongsTo(OrgNode::class, 'shop_node_id'); }

    public function totalAllowance(): int
    {
        return $this->limit_points + $this->rollover_points + $this->topup_points;
    }

    public function usedPercent(): float
    {
        $total = $this->totalAllowance();

        return $total > 0 ? round($this->used_points * 100 / $total, 1) : 0.0;
    }
}
