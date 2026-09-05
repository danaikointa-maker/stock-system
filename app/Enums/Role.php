<?php

namespace App\Enums;

/**
 * บทบาทผู้ใช้ ผูกกับระดับชั้นในสายงาน
 *
 * แนวคิด: สิทธิ์ = บทบาท (ทำอะไรได้) x ขอบเขตข้อมูล (ทำกับใครได้)
 * ขอบเขตข้อมูลมาจาก OrgNode subtree ของผู้ใช้เสมอ
 */
enum Role: string
{
    case SystemAdmin     = 'system_admin';     // เจ้าของระบบ
    case WarehouseAdmin  = 'warehouse_admin';  // คลังใหญ่ / คลังย่อย
    case AgentUser       = 'agent_user';       // ตัวแทนขาย
    case ShopUser        = 'shop_user';        // ร้านค้า
    case SellerUser      = 'seller_user';      // ผู้ขาย
    case Viewer          = 'viewer';           // ดูอย่างเดียว

    public function label(): string
    {
        return match ($this) {
            self::SystemAdmin    => 'ผู้ดูแลระบบ',
            self::WarehouseAdmin => 'ผู้จัดการคลัง',
            self::AgentUser      => 'ตัวแทนขาย',
            self::ShopUser       => 'เจ้าของร้านค้า',
            self::SellerUser     => 'ผู้ขาย',
            self::Viewer         => 'ผู้ดูข้อมูล',
        };
    }

    /** บทบาทเริ่มต้นที่เหมาะกับแต่ละระดับชั้น */
    public static function defaultForLevel(OrgLevel $level): self
    {
        return match ($level) {
            OrgLevel::SystemOwner                        => self::SystemAdmin,
            OrgLevel::MainWarehouse, OrgLevel::SubWarehouse => self::WarehouseAdmin,
            OrgLevel::Agent                              => self::AgentUser,
            OrgLevel::Shop                               => self::ShopUser,
            OrgLevel::Seller                             => self::SellerUser,
        };
    }

    /**
     * ความสามารถของแต่ละบทบาท
     *
     * manage-members : เพิ่ม/แก้/ระงับ ผู้ใช้ในสายงานตัวเอง
     * manage-nodes   : เปิดหน่วยงานลูกใหม่
     * approve-transfer : อนุมัติใบโอน
     * ship-stock     : ส่งของออก
     * receive-stock  : รับของเข้า
     * sell           : เปิดบิลขาย
     * view-reports   : ดูรายงาน
     * adjust-stock   : ปรับยอดสต๊อก
     * accept-redeem  : รับแลกแต้มจากลูกค้าที่หน้าร้าน
     * manage-shop    : ตั้งค่าหน้าร้านของตัวเอง (โลโก้/ธีม/ของรางวัล)
     * claim-money    : ยื่นเบิกเงินคืนจากเจ้าของระบบ
     * manage-packages: สร้าง/แก้แพ็กเกจสมาชิก (เจ้าของระบบเท่านั้น)
     * approve-claim  : อนุมัติจ่ายเงินให้ร้าน (เจ้าของระบบเท่านั้น)
     * manage-subscriptions : กรอกใบสมัครให้ร้าน (ตัวแทนขึ้นไป)
     * view-security  : ดู log ความปลอดภัย (เจ้าของระบบเท่านั้น)
     */
    public function abilities(): array
    {
        return match ($this) {
            self::SystemAdmin => [
                'manage-members', 'manage-nodes', 'approve-transfer', 'ship-stock',
                'receive-stock', 'sell', 'view-reports', 'adjust-stock', 'manage-products',
                'accept-redeem', 'manage-shop', 'claim-money', 'manage-packages',
                'approve-claim', 'manage-subscriptions', 'view-security',
                // บัญชี: ทุกเอกสาร + งบการเงิน + ลงบัญชีแยก
                'create-quotation', 'create-invoice', 'create-receipt', 'create-delivery',
                'create-payment', 'create-purchase-order', 'create-credit-note', 'create-tax-invoice',
                'view-financial-statements', 'manage-journals', 'view-ar-report',
            ],
            self::WarehouseAdmin => [
                'manage-members', 'manage-nodes', 'approve-transfer', 'ship-stock',
                'receive-stock', 'view-reports', 'adjust-stock',
                'manage-subscriptions',
                // บัญชี: จัดซื้อ + จ่าย + ส่งของ
                'create-delivery', 'create-payment', 'create-purchase-order',
            ],
            self::AgentUser => [
                'manage-members', 'manage-nodes', 'approve-transfer', 'ship-stock',
                'receive-stock', 'view-reports',
                'manage-subscriptions',
                // บัญชี: เสนอราคา + บิลเรียกเก็บ + ส่งของ + รับเงิน + ลดหนี้
                'create-quotation', 'create-invoice', 'create-receipt', 'create-delivery',
                'create-credit-note', 'create-tax-invoice',
                'view-ar-report',
            ],
            self::ShopUser => [
                'manage-members', 'manage-nodes', 'ship-stock', 'receive-stock',
                'sell', 'view-reports',
                'accept-redeem', 'manage-shop', 'claim-money',
                // บัญชี: รับเงินจากลูกค้า
                'create-receipt',
            ],
            self::SellerUser => ['receive-stock', 'sell', 'view-reports', 'accept-redeem'],
            self::Viewer     => ['view-reports'],
        };
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities(), true);
    }
}
