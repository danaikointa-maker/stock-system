<?php

namespace App\Enums;

enum OrgLevel: int
{
    case SystemOwner   = 1;  // เจ้าของระบบ
    case MainWarehouse = 2;  // คลังใหญ่
    case SubWarehouse  = 3;  // คลังย่อย
    case Agent         = 4;  // ตัวแทนขาย
    case Shop          = 5;  // ร้านค้า
    case Seller        = 6;  // ผู้ขาย

    public function label(): string
    {
        return match ($this) {
            self::SystemOwner   => 'เจ้าของระบบ',
            self::MainWarehouse => 'คลังใหญ่',
            self::SubWarehouse  => 'คลังย่อย',
            self::Agent         => 'ตัวแทนขาย',
            self::Shop          => 'ร้านค้า',
            self::Seller        => 'ผู้ขาย',
        };
    }

    /** ระดับที่ขายให้ลูกค้าปลายทางได้ */
    public function canSellToCustomer(): bool
    {
        return in_array($this, [self::Shop, self::Seller], true);
    }

    public function child(): ?self
    {
        return self::tryFrom($this->value + 1);
    }
}
