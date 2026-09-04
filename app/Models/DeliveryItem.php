<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryItem extends Model
{
    protected $fillable = [
        'delivery_note_id', 'product_id', 'lot_id', 'qty',
        'unit_cost', 'unit_price', 'line_total', 'returned', 'returned_qty', 'note',
    ];

    protected $casts = [
        'qty'          => 'integer',
        'unit_cost'    => 'decimal:2',
        'unit_price'   => 'decimal:2',
        'line_total'   => 'decimal:2',
        'returned'     => 'boolean',
        'returned_qty' => 'integer',
    ];

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class, 'lot_id');
    }
}
