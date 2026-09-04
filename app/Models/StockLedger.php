<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Stock Ledger — IMMUTABLE, APPEND-ONLY
 * ห้ามแก้ไข/ลบทุกกรณี ถ้าผิดพลาดให้ลงรายการกลับทาง (reversal entry)
 */
class StockLedger extends Model
{
    protected $table = 'stock_ledger';
    public $timestamps = false;
    const UPDATED_AT = null;

    protected $fillable = [
        'org_node_id', 'product_id', 'lot_id', 'movement_type', 'direction',
        'qty', 'unit_cost', 'total_cost', 'balance_after',
        'ref_type', 'ref_id', 'journal_entry_ref', 'note', 'created_by', 'created_at',
    ];

    protected $casts = [
        'qty'           => 'integer',
        'unit_cost'     => 'decimal:2',
        'total_cost'    => 'decimal:2',
        'balance_after' => 'integer',
        'created_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('stock_ledger เป็น append-only — แก้ไขไม่ได้ ถ้าผิดพลาดให้ลงรายการกลับทาง'));
        static::deleting(fn () => throw new \LogicException('stock_ledger เป็น append-only — ลบไม่ได้ ถ้าผิดพลาดให้ลงรายการกลับทาง'));
    }

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ref(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'ref_type', 'ref_id');
    }

    public function getMovementLabelAttribute(): string
    {
        return match($this->movement_type) {
            'receipt'       => '📥 รับเข้า',
            'sale'          => '🛒 ขายออก',
            'delivery'      => '🚚 ส่งของออก',
            'transfer_out'  => '⬆️ โอนออก',
            'transfer_in'   => '⬇️ โอนเข้า',
            'return_in'     => '↩️ รับคืน',
            'return_out'    => '↪️ ส่งคืน',
            'adjust_in'     => '🔧 ปรับเพิ่ม',
            'adjust_out'    => '🔧 ปรับลด',
            'damage'        => '💥 เสียหาย',
            'expired'       => '⏰ หมดอายุ',
            'cancel'        => '❌ หักล้าง',
            default         => $this->movement_type,
        };
    }
}
