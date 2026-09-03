<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'login_input', 'guard', 'succeeded', 'failure_reason', 'user_id',
        'ip_address', 'user_agent', 'country', 'is_suspicious',
    ];

    protected $casts = [
        'succeeded'     => 'boolean',
        'is_suspicious' => 'boolean',
        'created_at'    => 'datetime',
    ];
}
