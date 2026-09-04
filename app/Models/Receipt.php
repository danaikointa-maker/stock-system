<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['receipt_date' => 'date', 'amount' => 'decimal:2'];

    public function node(): BelongsTo { return $this->belongsTo(OrgNode::class, 'org_node_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function methodLabel(): string
    {
        return match($this->method) {
            'cash' => 'เงินสด',
            'bank_transfer' => 'โอนเงิน',
            'promptpay' => 'พร้อมเพย์',
            'cheque' => 'เช็ค',
            'credit_card' => 'บัตรเครดิต',
        };
    }
}
