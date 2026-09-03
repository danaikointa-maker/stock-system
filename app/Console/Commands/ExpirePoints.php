<?php

namespace App\Console\Commands;

use App\Services\PointEarningService;
use Illuminate\Console\Command;

/**
 * ตัดแต้มลูกค้าที่หมดอายุแล้ว
 *
 * ตั้งให้รันทุกวันตอนตี 1
 *   $schedule->command('roamembers:expire-points')->dailyAt('01:00');
 */
class ExpirePoints extends Command
{
    protected $signature = 'roamembers:expire-points';

    protected $description = 'ตัดแต้มลูกค้าที่หมดอายุแล้วออกจากกระเป๋า';

    public function handle(PointEarningService $points): int
    {
        $this->info('กำลังตรวจแต้มที่หมดอายุ ...');

        $expired = $points->expireOverdue();

        $this->line("  ตัดแต้มที่หมดอายุ : " . number_format($expired) . " แต้ม");
        $this->info('เสร็จเรียบร้อย');

        return self::SUCCESS;
    }
}
