<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditTrail extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'auditable_type', 'auditable_id', 'action', 'old_values', 'new_values',
        'changed_fields', 'user_id', 'user_label', 'ip_address', 'route', 'reason',
    ];

    protected $casts = [
        'old_values'     => 'array',
        'new_values'     => 'array',
        'changed_fields' => 'array',
        'created_at'     => 'datetime',
    ];

    public function auditable() { return $this->morphTo(); }
    public function user() { return $this->belongsTo(User::class); }
}
