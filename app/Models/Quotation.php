<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    protected $fillable = [
        'org_node_id', 'doc_no', 'customer_name', 'customer_address',
        'customer_tax_id', 'customer_contact', 'issue_date', 'valid_until',
        'subtotal', 'discount', 'vat_rate', 'vat_amount', 'total',
        'status', 'notes', 'terms', 'converted_invoice_id', 'created_by',
    ];

    protected $casts = [
        'issue_date'   => 'date',
        'valid_until'  => 'date',
        'subtotal'     => 'decimal:2',
        'discount'     => 'decimal:2',
        'vat_rate'     => 'decimal:2',
        'vat_amount'   => 'decimal:2',
        'total'        => 'decimal:2',
    ];

    public function items(): HasMany { return $this->hasMany(QuotationItem::class); }
    public function node(): BelongsTo { return $this->belongsTo(OrgNode::class, 'org_node_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function convertedInvoice(): BelongsTo { return $this->belongsTo(Invoice::class, 'converted_invoice_id'); }

    public function statusBadge(): string
    {
        return match($this->status) {
            'draft'     => 'warn',
            'sent'      => 'info',
            'accepted'  => 'ok',
            'rejected'  => 'bad',
            'expired'   => 'bad',
            'converted' => 'ok',
            default     => '',
        };
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'draft'     => '📝 ร่าง',
            'sent'      => '📤 ส่งแล้ว',
            'accepted'  => '✅ ลูกค้าตกลง',
            'rejected'  => '❌ ปฏิเสธ',
            'expired'   => '⏰ หมดอายุ',
            'converted' => '🔄 แปลงเป็นบิลแล้ว',
            default     => $this->status,
        };
    }
}
