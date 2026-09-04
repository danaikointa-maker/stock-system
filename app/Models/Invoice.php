<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function node(): BelongsTo { return $this->belongsTo(OrgNode::class, 'org_node_id'); }
    public function customerNode(): BelongsTo { return $this->belongsTo(OrgNode::class, 'customer_node_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function receipts(): HasMany { return $this->hasMany(Receipt::class); }
    public function taxInvoice(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(TaxInvoice::class); }

    public function statusLabel(): string
    {
        return match($this->status) {
            'draft' => 'ร่าง',
            'issued' => 'ออกบิลแล้ว',
            'partial' => 'ชำระบางส่วน',
            'paid' => 'ชำระแล้ว',
            'overdue' => 'เกินกำหนด',
            'void' => 'ยกเลิก',
        };
    }

    public function statusBadge(): string
    {
        return match($this->status) {
            'draft' => 'b-gray',
            'issued' => 'b-blue',
            'partial' => 'b-amber',
            'paid' => 'b-green',
            'overdue' => 'b-red',
            'void' => 'b-red',
        };
    }

    public function recalc(): void
    {
        $this->subtotal = $this->items()->sum('amount');
        $this->vat_amount = $this->subtotal * ($this->vat_rate / 100);
        $this->total = $this->subtotal + $this->vat_amount;
        $this->paid_amount = $this->receipts()->sum('amount');
        $this->balance = $this->total - $this->paid_amount;

        if ($this->balance <= 0) {
            $this->status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partial';
        } elseif ($this->due_date->isPast() && $this->status !== 'void') {
            $this->status = 'overdue';
        } elseif ($this->status === 'draft') {
            $this->status = 'issued';
        }
    }
}
