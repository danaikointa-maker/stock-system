<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class WorkflowController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $role = $user->role;

        // กำหนดว่า role ไหนเห็นแท็บไหน
        $visibleTabs = match (true) {
            $role->can('manage-packages') => ['overview', 'admin', 'warehouse', 'agent', 'shop', 'seller', 'customer'],
            $role->can('adjust-stock')    => ['overview', 'warehouse', 'customer'],
            $role->can('approve-transfer')=> ['overview', 'agent', 'customer'],
            $role->can('manage-shop')     => ['overview', 'shop', 'customer'],
            $role->can('sell')            => ['overview', 'seller', 'customer'],
            $role->can('view-reports')    => ['overview'],
            default                       => ['overview'],
        };

        // แท็บเริ่มต้น (เปิดหน้าแรกที่เห็น)
        $defaultTab = match (true) {
            $role->can('manage-packages') => 'admin',
            $role->can('adjust-stock')    => 'warehouse',
            $role->can('approve-transfer')=> 'agent',
            $role->can('manage-shop')     => 'shop',
            $role->can('sell')            => 'seller',
            default                       => 'overview',
        };

        return view('workflow.index', [
            'visibleTabs' => $visibleTabs,
            'defaultTab'  => $defaultTab,
        ]);
    }
}
