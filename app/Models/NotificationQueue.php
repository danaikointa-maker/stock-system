<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * คิวแจ้งเตือน LINE / Email
 *
 * ไม่ส่งทันทีตอนสร้าง เพราะ API ภายนอกอาจช้าหรือล่ม
 * ตัวส่งคือ command roamembers:send-notifications
 */
class NotificationQueue extends Model
{
    protected $table = 'notification_queue';

    protected $fillable = [
        'channel', 'recipient_type', 'recipient_id', 'destination',
        'template', 'subject', 'body', 'payload',
        'status', 'attempts', 'max_attempts', 'scheduled_at', 'sent_at',
        'error_message', 'provider_message_id', 'ref_type', 'ref_id',
    ];

    protected $casts = [
        'payload'      => 'array',
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
    ];

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    /** ลองส่งใหม่ได้ไหม */
    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->attempts < $this->max_attempts;
    }
}
