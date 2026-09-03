<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'doc_no', 'org_node_id', 'seller_user_id', 'customer_id', 'sold_at',
        'subtotal', 'discount', 'total', 'payment_method', 'status',
    ];

    protected $casts = [
        'sold_at' => 'datetime', 'subtotal' => 'decimal:2',
        'discount' => 'decimal:2', 'total' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'org_node_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public static function nextDocNo(): string
    {
        $prefix = 'SAL-' . now()->format('Ym') . '-';
        $last = static::where('doc_no', 'like', $prefix . '%')->orderByDesc('id')->value('doc_no');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }
}
