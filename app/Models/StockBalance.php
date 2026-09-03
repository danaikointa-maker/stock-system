<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    public const UPDATED_AT = 'updated_at';
    public const CREATED_AT = null;

    protected $fillable = [
        'org_node_id', 'product_id', 'lot_id',
        'qty_on_hand', 'qty_reserved', 'qty_in_transit', 'reorder_point',
    ];

    protected $casts = [
        'qty_on_hand'    => 'integer',
        'qty_reserved'   => 'integer',
        'qty_in_transit' => 'integer',
        'reorder_point'  => 'integer',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'org_node_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class, 'lot_id');
    }

    /** จำนวนที่ขาย/โอนออกได้จริง */
    public function getAvailableAttribute(): int
    {
        return $this->qty_on_hand - $this->qty_reserved;
    }

    public function scopeLowStock($q)
    {
        return $q->whereColumn('qty_on_hand', '<=', 'reorder_point')
                 ->where('reorder_point', '>', 0);
    }
}
