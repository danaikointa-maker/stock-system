<?php

namespace App\Models;

use App\Enums\OrgLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductLevelPrice extends Model
{
    protected $fillable = ['product_id', 'level_id', 'price', 'min_qty', 'effective_from', 'effective_to'];
    protected $casts = [
        'level_id' => OrgLevel::class,
        'price' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
