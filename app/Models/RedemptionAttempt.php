<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** บันทึกทุกความพยายามแลก ทั้งสำเร็จและถูกปฏิเสธ */
class RedemptionAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'customer_id', 'shop_node_id', 'points_requested',
        'reward_name', 'result', 'detail',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function scopeFailed($q) { return $q->where('result', '!=', 'ok'); }
}
