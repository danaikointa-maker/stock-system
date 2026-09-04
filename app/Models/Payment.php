<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2'];

    public function node(): BelongsTo { return $this->belongsTo(OrgNode::class, 'org_node_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function withholdingTax(): HasOne { return $this->hasOne(WithholdingTax::class); }

    public function methodLabel(): string
    {
        return match($this->method) {
            'cash' => 'เงินสด',
            'bank_transfer' => 'โอนเงิน',
            'promptpay' => 'พร้อมเพย์',
            'cheque' => 'เช็ค',
        };
    }
}
