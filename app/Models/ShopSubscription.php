<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopSubscription extends Model
{
    protected $fillable = [
        'code', 'shop_node_id', 'package_id', 'recruiter_node_id',
        'monthly_point_limit', 'price_paid', 'allow_rollover', 'allow_cash_redeem',
        'commission_amount', 'starts_on', 'ends_on', 'status', 'auto_renew',
        'paid_at', 'payment_ref', 'approved_by', 'cancelled_at', 'cancel_reason', 'note',
    ];

    protected $casts = [
        'starts_on'         => 'date',
        'ends_on'           => 'date',
        'paid_at'           => 'datetime',
        'cancelled_at'      => 'datetime',
        'allow_rollover'    => 'boolean',
        'allow_cash_redeem' => 'boolean',
        'auto_renew'        => 'boolean',
    ];

    public function shop() { return $this->belongsTo(OrgNode::class, 'shop_node_id'); }
    public function package() { return $this->belongsTo(ShopPackage::class, 'package_id'); }
    public function recruiter() { return $this->belongsTo(OrgNode::class, 'recruiter_node_id'); }
    public function allowances() { return $this->hasMany(ShopMonthlyAllowance::class, 'subscription_id'); }

    /** ใช้งานได้จริงไหม (active และยังไม่หมดอายุ) */
    public function isUsable(): bool
    {
        return $this->status === 'active' && $this->ends_on->gte(now()->startOfDay());
    }
}
