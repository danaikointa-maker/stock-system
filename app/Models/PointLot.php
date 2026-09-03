<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ล็อตแต้มแบบ FIFO — ใช้ของเก่า/ใกล้หมดอายุก่อน */
class PointLot extends Model
{
    protected $fillable = [
        'wallet_id', 'points_in', 'points_left', 'earned_at',
        'expires_at', 'source_type', 'source_id', 'is_expired',
    ];

    protected $casts = [
        'earned_at'  => 'datetime',
        'expires_at' => 'datetime',
        'is_expired' => 'boolean',
    ];

    public function wallet() { return $this->belongsTo(CustomerPointWallet::class, 'wallet_id'); }

    public function isExpired(): bool
    {
        return $this->is_expired || $this->expires_at->isPast();
    }
}
