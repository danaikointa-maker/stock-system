<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedEntity extends Model
{
    protected $fillable = [
        'entity_type', 'entity_value', 'reason', 'block_type',
        'blocked_until', 'hit_count', 'blocked_by', 'is_active',
    ];

    protected $casts = [
        'blocked_until' => 'datetime',
        'is_active'     => 'boolean',
    ];
}
