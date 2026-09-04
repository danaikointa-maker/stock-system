<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryNote extends Model
{
    protected $fillable = [
        'org_node_id', 'doc_no', 'sale_id', 'invoice_id',
        'customer_name', 'delivery_address', 'recipient_name', 'recipient_phone',
        'status', 'total_qty', 'total_amount', 'shipped_at', 'delivered_at',
        'tracking_no', 'carrier', 'note', 'created_by',
    ];

    protected $casts = [
        'total_qty'    => 'integer',
        'total_amount' => 'decimal:2',
        'shipped_at'   => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'org_node_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft'     => '📝 ร่าง',
            'ready'     => '📦 พร้อมส่ง',
            'shipped'   => '🚚 ส่งแล้ว',
            'delivered' => '✅ ถึงแล้ว',
            'returned'  => '↩️ คืน',
            'cancelled' => '❌ ยกเลิก',
            default     => $this->status,
        };
    }
}
