<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** หน้าแรกหลัง login — เนื้อหาปรับตามระดับชั้นของผู้ใช้ */
class DashboardController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $node = $user->node;

        abort_if(! $node, 403, 'บัญชีนี้ยังไม่ได้ผูกกับหน่วยงาน กรุณาติดต่อผู้ดูแลระบบ');

        return view('dashboard.index', [
            'user'         => $user,
            'node'         => $node,
            'kpi'          => $this->reports->dashboardKpi($user),
            'lowStock'     => $this->reports->lowStock($user, 8),
            'pendingIn'    => $this->reports->pendingIncoming($user),
            'pendingOut'   => $this->reports->pendingApproval($user),
            'salesTrend'   => $this->reports->salesTrend($user, 14),
            'childSummary' => $this->reports->childNodePerformance($user),
        ]);
    }
}
