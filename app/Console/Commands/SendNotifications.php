<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * ส่งแจ้งเตือนที่รออยู่ในคิว
 *
 * ตั้งให้รันทุก 1 นาที
 *   $schedule->command('roamembers:send-notifications')->everyMinute()->withoutOverlapping();
 *
 * withoutOverlapping สำคัญมาก กันไม่ให้สองรอบทำงานทับกันจนส่งซ้ำ
 */
class SendNotifications extends Command
{
    protected $signature = 'roamembers:send-notifications {--limit=50 : จำนวนสูงสุดต่อรอบ}';

    protected $description = 'ส่งแจ้งเตือน LINE และอีเมลที่รออยู่ในคิว';

    public function handle(NotificationService $notify): int
    {
        $limit = (int) $this->option('limit');
        $result = $notify->dispatchPending($limit);

        if ($result['sent'] + $result['failed'] === 0) {
            $this->line('ไม่มีรายการรอส่ง');

            return self::SUCCESS;
        }

        $this->info("ส่งสำเร็จ {$result['sent']} · ล้มเหลว {$result['failed']} · ข้าม {$result['skipped']}");

        return self::SUCCESS;
    }
}
