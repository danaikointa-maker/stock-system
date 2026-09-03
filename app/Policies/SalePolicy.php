<?php

namespace App\Policies;

use App\Enums\OrgLevel;
use App\Models\OrgNode;
use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->canAccessNode($sale->org_node_id);
    }

    /**
     * เปิดบิลขายได้ถ้ามีสิทธิ์ sell และในสายงานมีหน่วยงานที่ขายได้อย่างน้อยหนึ่งแห่ง
     *
     * ผู้ดูแลระบบจึงเปิดบิลแทนร้านค้าได้ (กรณีช่วยแก้ปัญหาหน้างาน)
     * ส่วนคลัง/ตัวแทนไม่มีสิทธิ์ sell อยู่แล้วจึงถูกปฏิเสธตั้งแต่เงื่อนไขแรก
     */
    public function create(User $user): bool
    {
        if (! $user->hasAbility('sell')) {
            return false;
        }

        if ($user->level()?->canSellToCustomer()) {
            return true;
        }

        return OrgNode::whereIn('id', $user->visibleNodeIds())
            ->whereIn('level_id', [OrgLevel::Shop->value, OrgLevel::Seller->value])
            ->where('status', 'active')
            ->exists();
    }

    /** ขายในนามหน่วยงานนั้นได้ไหม */
    public function sellAs(User $user, OrgNode $node): bool
    {
        return $user->hasAbility('sell')
            && $user->canAccessNode($node->id)
            && $node->level_id->canSellToCustomer()
            && $node->status === 'active';
    }

    /** ยกเลิกบิล — คืนของเข้าสต๊อก */
    public function void(User $user, Sale $sale): bool
    {
        return $user->hasAbility('sell')
            && $user->canAccessNode($sale->org_node_id)
            && $sale->status === 'completed';
    }
}
