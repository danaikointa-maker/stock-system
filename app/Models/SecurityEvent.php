<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'event_type', 'severity', 'actor_type', 'actor_id', 'actor_label',
        'route', 'method', 'ip_address', 'user_agent', 'target_type', 'target_id',
        'message', 'context', 'is_reviewed', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'context'     => 'array',
        'is_reviewed' => 'boolean',
        'reviewed_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    public function scopeUnreviewed($q) { return $q->where('is_reviewed', false); }
    public function scopeSerious($q) { return $q->whereIn('severity', ['high', 'critical']); }
}
