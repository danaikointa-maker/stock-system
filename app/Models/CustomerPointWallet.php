<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * กระเป๋าแต้มของลูกค้า — แยกตามร้านผู้ออกแต้ม
 * ลูกค้า 1 คนมีได้หลายกระเป๋า
 */
class CustomerPointWallet extends Model
{
    protected $fillable = [
        'customer_id', 'issuer_node_id', 'balance',
        'lifetime_earned', 'lifetime_used', 'last_activity_at',
    ];

    protected $casts = ['last_activity_at' => 'datetime'];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function issuer() { return $this->belongsTo(OrgNode::class, 'issuer_node_id'); }
    public function lots() { return $this->hasMany(PointLot::class, 'wallet_id'); }

    /** ผลรวมล็อตต้องเท่ากับ balance เสมอ ใช้ตรวจความถูกต้อง */
    public function recalculated(): int
    {
        return (int) $this->lots()->where('is_expired', false)->sum('points_left');
    }
}
