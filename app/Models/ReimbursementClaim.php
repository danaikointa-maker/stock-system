<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ใบเบิกเงิน — ร้านเบิกจากเจ้าของระบบโดยตรง */
class ReimbursementClaim extends Model
{
    protected $fillable = [
        'code', 'claimant_node_id', 'period_ym', 'total_points', 'point_value',
        'total_amount', 'entry_count', 'status', 'submitted_at', 'approved_at',
        'approved_by', 'paid_at', 'payment_method', 'payment_ref', 'reject_reason', 'note',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
        'paid_at'      => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function claimant() { return $this->belongsTo(OrgNode::class, 'claimant_node_id'); }
}
