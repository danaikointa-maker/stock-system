<?php

namespace App\Policies;

use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\User;

/**
 * สิทธิ์บนใบโอนสินค้า
 *
 * หลักการ: ต้นทางเป็นคนอนุมัติและส่ง / ปลายทางเป็นคนรับ
 * และผู้ใช้ต้องมีหน่วยงานนั้นอยู่ในสายงานที่ตนดูแล
 */
class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Transfer $transfer): bool
    {
        return $user->canAccessNode($transfer->from_node_id)
            || $user->canAccessNode($transfer->to_node_id);
    }

    /** สร้างใบโอนได้ถ้ามีสิทธิ์ส่งของ */
    public function create(User $user): bool
    {
        return $user->hasAbility('ship-stock');
    }

    /** อนุมัติ: ต้องเป็นฝั่งต้นทาง + มีสิทธิ์อนุมัติ + สถานะรออนุมัติ */
    public function approve(User $user, Transfer $transfer): bool
    {
        return $user->hasAbility('approve-transfer')
            && $user->canAccessNode($transfer->from_node_id)
            && $transfer->status === TransferStatus::PendingApprove;
    }

    public function reject(User $user, Transfer $transfer): bool
    {
        return $this->approve($user, $transfer);
    }

    /** ส่งของ: ต้นทาง + มีสิทธิ์ส่ง + อนุมัติแล้ว */
    public function ship(User $user, Transfer $transfer): bool
    {
        return $user->hasAbility('ship-stock')
            && $user->canAccessNode($transfer->from_node_id)
            && $transfer->status === TransferStatus::Approved;
    }

    /** รับของ: ปลายทาง + มีสิทธิ์รับ + ส่งแล้ว */
    public function receive(User $user, Transfer $transfer): bool
    {
        return $user->hasAbility('receive-stock')
            && $user->canAccessNode($transfer->to_node_id)
            && $transfer->status === TransferStatus::Shipped;
    }

    /** ยกเลิกได้ก่อนส่งของเท่านั้น */
    public function cancel(User $user, Transfer $transfer): bool
    {
        return $user->canAccessNode($transfer->from_node_id)
            && $user->hasAbility('ship-stock')
            && in_array($transfer->status, [
                TransferStatus::Draft,
                TransferStatus::PendingApprove,
                TransferStatus::Approved,
            ], true);
    }
}
