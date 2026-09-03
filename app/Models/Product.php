<?php

namespace App\Models;

use App\Enums\OrgLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku', 'barcode', 'name', 'category_id', 'unit_id', 'pack_size',
        'cost_price', 'retail_price', 'points_per_unit', 'track_serial',
        'has_expiry', 'image_url', 'status',
    ];

    protected $casts = [
        'cost_price'      => 'decimal:2',
        'retail_price'    => 'decimal:2',
        'points_per_unit' => 'integer',
        'pack_size'       => 'integer',
        'track_serial'    => 'boolean',
        'has_expiry'      => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(ProductLot::class);
    }

    public function levelPrices(): HasMany
    {
        return $this->hasMany(ProductLevelPrice::class);
    }

    public function qrcodes(): HasMany
    {
        return $this->hasMany(ProductQrcode::class);
    }

    /** ราคาที่ระดับชั้นนี้ซื้อเข้า ณ วันนี้ */
    public function priceForLevel(OrgLevel $level, int $qty = 1): float
    {
        $today = now()->toDateString();

        $price = $this->levelPrices()
            ->where('level_id', $level->value)
            ->where('min_qty', '<=', $qty)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->orderByDesc('min_qty')
            ->value('price');

        return (float) ($price ?? $this->retail_price);
    }
}
