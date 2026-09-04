<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithholdingTax extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'issue_date' => 'date',
        'income_amount' => 'decimal:2',
        'wht_rate' => 'decimal:2',
        'wht_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function node(): BelongsTo { return $this->belongsTo(OrgNode::class, 'org_node_id'); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public static function commonRates(): array
    {
        return [
            '1' => '1% — ค่าขนส่ง',
            '2' => '2% — ค่าโฆษณา',
            '3' => '3% — บริการทั่วไป, ค่าเช่า',
            '5' => '5% — ค่าเช่าอสังหาฯ',
            '15' => '15% — ค่าลิขสิทธิ์',
        ];
    }
}
