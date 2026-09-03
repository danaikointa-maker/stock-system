<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only ledger ของคะแนน */
class PointTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'customer_id', 'type', 'points', 'balance_after',
        'ref_type', 'ref_id', 'expires_at', 'note',
    ];
    protected $casts = ['points' => 'integer', 'balance_after' => 'integer', 'expires_at' => 'date', 'created_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
