<?php

namespace App\Policies;

use App\Models\OrgNode;
use App\Models\User;

class OrgNodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, OrgNode $node): bool
    {
        return $user->canAccessNode($node->id);
    }

    /** สร้างหน่วยงานลูกได้ ถ้ามีสิทธิ์และยังไม่ถึงระดับล่างสุด */
    public function create(User $user): bool
    {
        return $user->hasAbility('manage-nodes')
            && $user->level()?->child() !== null;
    }

    public function update(User $user, OrgNode $node): bool
    {
        if (! $user->hasAbility('manage-nodes')) {
            return false;
        }

        // แก้ได้เฉพาะหน่วยงานลูกหลาน ไม่ใช่ของตัวเอง (ยกเว้น system admin)
        return $user->isSystemAdmin()
            || ($user->canAccessNode($node->id) && $node->id !== $user->org_node_id);
    }

    public function delete(User $user, OrgNode $node): bool
    {
        return $this->update($user, $node) && $node->children()->doesntExist();
    }
}
