<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductLot extends Model
{
    protected $fillable = ['product_id', 'lot_no', 'mfg_date', 'expiry_date', 'qty_produced'];
    protected $casts = ['mfg_date' => 'date', 'expiry_date' => 'date', 'qty_produced' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function qrcodes(): HasMany
    {
        return $this->hasMany(ProductQrcode::class, 'lot_id');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }
}
