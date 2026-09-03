<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * รีเซตวงเงินรายเดือนของทุกร้าน
 *
 * ตั้งให้รันทุกวันที่ 1 เวลา 00:05 น.
 *   $schedule->command('roamembers:reset-allowances')->monthlyOn(1, '00:05');
 *
 * ปลอดภัยถ้ารันซ้ำ — ร้านที่มีวงเงินของเดือนนั้นแล้วจะถูกข้าม
 */
class ResetMonthlyAllowances extends Command
{
    protected $signature = 'roamembers:reset-allowances {--period= : งวดที่ต้องการ (Y-m) ไม่ระบุ = เดือนปัจจุบัน}';

    protected $description = 'เปิดวงเงินรับแลกแต้มรายเดือนให้ร้านที่สมาชิกยังใช้งานได้';

    public function handle(SubscriptionService $subs): int
    {
        $period = $this->option('period') ?: now()->format('Y-m');

        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('รูปแบบงวดไม่ถูกต้อง ต้องเป็น YYYY-MM');

            return self::FAILURE;
        }

        $this->info("กำลังรีเซตวงเงินงวด {$period} ...");

        $result = $subs->resetMonthly($period);

        $this->line("  เปิดวงเงินให้ร้าน : {$result['opened']} ร้าน");
        $this->line("  ปิดสมาชิกหมดอายุ : {$result['expired']} ร้าน");
        $this->info('เสร็จเรียบร้อย');

        return self::SUCCESS;
    }
}
