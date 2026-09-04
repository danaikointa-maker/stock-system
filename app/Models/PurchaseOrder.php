<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'org_node_id', 'po_no', 'vendor_name', 'vendor_address',
        'vendor_tax_id', 'vendor_contact', 'order_date', 'expected_date',
        'subtotal', 'discount', 'vat_rate', 'vat_amount',
        'wht_rate', 'wht_amount', 'total', 'net_total',
        'status', 'notes', 'approved_by', 'approved_at', 'created_by',
    ];

    protected $casts = [
        'order_date'    => 'date',
        'expected_date' => 'date',
        'approved_at'   => 'datetime',
        'subtotal'      => 'decimal:2',
        'discount'      => 'decimal:2',
        'vat_rate'      => 'decimal:2',
        'vat_amount'    => 'decimal:2',
        'wht_rate'      => 'decimal:2',
        'wht_amount'    => 'decimal:2',
        'total'         => 'decimal:2',
        'net_total'     => 'decimal:2',
    ];

    public function items(): HasMany { return $this->hasMany(PurchaseOrderItem::class); }
    public function node(): BelongsTo { return $this->belongsTo(OrgNode::class, 'org_node_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function statusBadge(): string
    {
        return match($this->status) {
            'draft'            => 'warn',
            'approved'         => 'info',
            'ordered'          => 'info',
            'partial_received' => 'warn',
            'received'         => 'ok',
            'cancelled'        => 'bad',
            default            => '',
        };
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'draft'            => '📝 ร่าง',
            'approved'         => '✅ อนุมัติ',
            'ordered'          => '📤 ส่งคำสั่งซื้อแล้ว',
            'partial_received' => '📦 รับบางส่วน',
            'received'         => '✅ รับครบแล้ว',
            'cancelled'        => '❌ ยกเลิก',
            default            => $this->status,
        };
    }
}
