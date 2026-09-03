<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    protected $fillable = [
        'fingerprint', 'level', 'exception_class', 'message', 'file', 'line',
        'stack_trace', 'route', 'method', 'input', 'user_id', 'ip_address',
        'occurrence_count', 'first_seen_at', 'last_seen_at', 'is_resolved',
    ];

    protected $casts = [
        'input'         => 'array',
        'is_resolved'   => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at'  => 'datetime',
    ];
}
