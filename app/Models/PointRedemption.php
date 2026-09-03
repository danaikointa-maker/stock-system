<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointRedemption extends Model
{
    protected $fillable = [
        'code', 'customer_id', 'issuer_node_id', 'accepting_node_id', 'allowance_id',
        'redeem_type', 'reward_id', 'reward_name', 'points_used', 'point_value',
        'cash_value', 'status', 'claim_id', 'redeemed_at', 'confirmed_by', 'note',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
        'point_value' => 'decimal:4',
        'cash_value'  => 'decimal:2',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function issuer() { return $this->belongsTo(OrgNode::class, 'issuer_node_id'); }
    public function shop() { return $this->belongsTo(OrgNode::class, 'accepting_node_id'); }
    public function items() { return $this->hasMany(RedemptionItem::class, 'redemption_id'); }
}
