<?php

namespace App\Exceptions;

use Exception;

/**
 * ข้อผิดพลาดของการแลกแต้ม
 * $reason ใช้แยกประเภทเพื่อบันทึกสถิติและแสดงข้อความที่เหมาะสม
 */
class RedemptionException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $reason = 'blocked',
    ) {
        parent::__construct($message);
    }
}
