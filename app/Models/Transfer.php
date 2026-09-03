<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfer extends Model
{
    protected $fillable = [
        'doc_no', 'from_node_id', 'to_node_id', 'type', 'status',
        'total_qty', 'total_amount', 'requested_by', 'approved_by',
        'approved_at', 'shipped_at', 'received_by', 'received_at', 'note',
    ];

    protected $casts = [
        'status'       => TransferStatus::class,
        'total_amount' => 'decimal:2',
        'approved_at'  => 'datetime',
        'shipped_at'   => 'datetime',
        'received_at'  => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'from_node_id');
    }

    public function toNode(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'to_node_id');
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class, 'ref_id')
            ->where('ref_type', self::class);
    }

    public static function nextDocNo(): string
    {
        $prefix = 'TRF-' . now()->year . '-';
        $last = static::where('doc_no', 'like', $prefix . '%')
            ->orderByDesc('id')->value('doc_no');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }
}
