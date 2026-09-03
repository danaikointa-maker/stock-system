<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityRule extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'threshold', 'window_minutes',
        'action', 'block_minutes', 'severity', 'is_enabled',
    ];

    protected $casts = ['is_enabled' => 'boolean'];
}
