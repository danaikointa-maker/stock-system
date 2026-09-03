<?php

namespace App\Policies;

use App\Models\OrgNode;
use App\Models\StockBalance;
use App\Models\User;

class StockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, StockBalance $balance): bool
    {
        return $user->canAccessNode($balance->org_node_id);
    }

    /** ปรับยอดสต๊อกจากการนับ */
    public function adjust(User $user, OrgNode $node): bool
    {
        return $user->hasAbility('adjust-stock')
            && $user->canAccessNode($node->id);
    }
}
