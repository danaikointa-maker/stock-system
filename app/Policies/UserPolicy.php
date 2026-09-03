<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /** หน้ารายชื่อสมาชิกเป็นหน้าบริหาร — ต้องมีสิทธิ์ manage-members เท่านั้น */
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('manage-members');
    }

    public function view(User $user, User $target): bool
    {
        return $user->id === $target->id || $user->canAccessNode($target->org_node_id);
    }

    public function create(User $user): bool
    {
        return $user->hasAbility('manage-members');
    }

    public function update(User $user, User $target): bool
    {
        return $user->canManageUser($target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->canManageUser($target);
    }

    /** เปลี่ยนบทบาท — ให้สิทธิ์สูงกว่าตัวเองไม่ได้ */
    public function changeRole(User $user, User $target): bool
    {
        return $user->canManageUser($target);
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $user->canManageUser($target);
    }
}
