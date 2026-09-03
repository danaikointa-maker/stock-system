<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferItem extends Model
{
    public $timestamps = false;
    protected $fillable = ['transfer_id', 'product_id', 'lot_id', 'qty_requested', 'qty_shipped', 'qty_received', 'unit_price'];
    protected $casts = [
        'qty_requested' => 'integer', 'qty_shipped' => 'integer',
        'qty_received' => 'integer', 'unit_price' => 'decimal:2',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
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
