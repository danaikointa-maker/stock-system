<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditItem extends Model
{
    protected $fillable = [
        'credit_note_id', 'product_id', 'lot_id', 'qty',
        'unit_cost', 'unit_price', 'line_total', 'note',
    ];

    protected $casts = [
        'qty'        => 'integer',
        'unit_cost'  => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
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
