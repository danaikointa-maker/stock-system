<?php

namespace App\Providers;

use App\Models\OrgNode;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\Transfer;
use App\Models\User;
use App\Policies\OrgNodePolicy;
use App\Policies\SalePolicy;
use App\Policies\StockPolicy;
use App\Policies\TransferPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class         => UserPolicy::class,
        OrgNode::class      => OrgNodePolicy::class,
        Transfer::class     => TransferPolicy::class,
        Sale::class         => SalePolicy::class,
        StockBalance::class => StockPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Gate สำหรับความสามารถราย ability เช่น @can('ship-stock')
        foreach ([
            'manage-members', 'manage-nodes', 'approve-transfer', 'ship-stock',
            'receive-stock', 'sell', 'view-reports', 'adjust-stock', 'manage-products',
            'accept-redeem', 'manage-shop', 'claim-money', 'manage-packages',
            'approve-claim', 'manage-subscriptions', 'view-security',
        ] as $ability) {
            Gate::define($ability, fn (User $user) => $user->hasAbility($ability));
        }
    }
}
