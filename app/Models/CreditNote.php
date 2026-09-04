<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditNote extends Model
{
    protected $fillable = [
        'org_node_id', 'doc_no', 'type', 'reason',
        'invoice_id', 'delivery_note_id', 'sale_id',
        'customer_name', 'subtotal', 'vat_amount', 'total_amount',
        'status', 'posted_to_accounting', 'note', 'created_by',
    ];

    protected $casts = [
        'subtotal'           => 'decimal:2',
        'vat_amount'         => 'decimal:2',
        'total_amount'       => 'decimal:2',
        'posted_to_accounting' => 'boolean',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'org_node_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditItem::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'return'     => '↩️ คืนสินค้า',
            'discount'   => '💰 ส่วนลด',
            'cancel'     => '❌ ยกเลิก',
            'adjustment' => '🔧 ปรับปรุง',
            default      => $this->type,
        };
    }
}
