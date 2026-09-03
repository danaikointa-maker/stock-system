<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAlert extends Model
{
    protected $fillable = [
        'alert_type', 'severity', 'title', 'body', 'data', 'link',
        'sent_line', 'sent_email', 'sent_at', 'status',
        'handled_by', 'handled_at', 'handle_note',
    ];

    protected $casts = [
        'data'       => 'array',
        'sent_line'  => 'boolean',
        'sent_email' => 'boolean',
        'sent_at'    => 'datetime',
        'handled_at' => 'datetime',
    ];

    public function scopeUnhandled($q) { return $q->whereIn('status', ['new', 'acknowledged']); }
}
