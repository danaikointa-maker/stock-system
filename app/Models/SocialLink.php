<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ผูกบัญชี LINE / Google
 * ผู้ซื้อ: 1 ไอดีต่อ 1 บัญชี | ผู้ใช้ระบบ: หลายไอดี (จำกัดด้วย users.max_social_links)
 */
class SocialLink extends Model
{
    protected $fillable = [
        'owner_type', 'owner_id', 'provider', 'provider_uid', 'display_name',
        'picture_url', 'email', 'is_primary', 'notify_enabled', 'linked_at',
    ];

    protected $casts = [
        'is_primary'     => 'boolean',
        'notify_enabled' => 'boolean',
        'linked_at'      => 'datetime',
    ];

    public function scopeFor($q, string $type, int $id)
    {
        return $q->where('owner_type', $type)->where('owner_id', $id);
    }
}
