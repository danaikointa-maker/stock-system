<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Append-only ledger — ห้ามแก้ไข/ลบย้อนหลัง ถ้าผิดให้ลงรายการกลับทาง */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'org_node_id', 'product_id', 'lot_id', 'direction', 'qty',
        'balance_after', 'type', 'ref_type', 'ref_id', 'unit_cost',
        'note', 'created_by',
    ];

    protected $casts = [
        'type'          => MovementType::class,
        'qty'           => 'integer',
        'balance_after' => 'integer',
        'unit_cost'     => 'decimal:2',
        'created_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('stock_movements เป็น append-only แก้ไขไม่ได้'));
        static::deleting(fn () => throw new \LogicException('stock_movements เป็น append-only ลบไม่ได้'));
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'org_node_id');
    }

    /** ผู้ทำรายการ (คอลัมน์จริงชื่อ created_by) */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class, 'lot_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ref(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'ref_type', 'ref_id');
    }
}
