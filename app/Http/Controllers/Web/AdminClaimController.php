<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PointRedemption;
use App\Models\ReimbursementClaim;
use App\Services\ClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * อนุมัติและจ่ายเงินใบเบิก — เฉพาะเจ้าของระบบ
 *
 * เจ้าของระบบเป็นผู้จ่ายเงินให้ร้านโดยตรง
 * สายงานเหนือร้านเห็นรายงานได้ แต่ไม่ใช่ผู้จ่าย
 */
class AdminClaimController extends Controller
{
    public function __construct(private ClaimService $claims)
    {
    }

    /** รายการใบเบิกทั้งระบบ */
    public function index(Request $request): View
    {
        $this->authorizeAdmin();

        $query = ReimbursementClaim::query()
            ->with('claimant')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('period'), fn ($q, $p) => $q->where('period_ym', $p))
            ->when($request->query('shop'), function ($q, $shop) {
                $q->whereHas('claimant', fn ($w) => $w->where('name', 'like', "%{$shop}%"));
            });

        // เรียงให้ใบที่ต้องดำเนินการขึ้นก่อน
        // ใช้ CASE แทน FIELD() เพราะ FIELD เป็นฟังก์ชันเฉพาะ MySQL
        // (SQLite ที่ใช้ตอนเทสต์ไม่มีฟังก์ชันนี้)
        $priority = "CASE status
            WHEN 'submitted' THEN 1
            WHEN 'approved'  THEN 2
            WHEN 'draft'     THEN 3
            WHEN 'paid'      THEN 4
            ELSE 5 END";

        return view('admin.claims.index', [
            'claims'  => $query->orderByRaw($priority)
                ->orderByDesc('submitted_at')
                ->paginate(25)
                ->withQueryString(),
            'summary' => $this->summary(),
        ]);
    }

    /** รายละเอียดใบเบิก */
    public function show(Request $request, ReimbursementClaim $claim): View
    {
        $this->authorizeAdmin();

        return view('admin.claims.show', [
            'claim' => $claim->load('claimant'),
            'items' => PointRedemption::where('claim_id', $claim->id)
                ->orderByDesc('redeemed_at')->get(),
            'upline' => $this->uplineChain($claim),
        ]);
    }

    /** อนุมัติ */
    public function approve(Request $request, ReimbursementClaim $claim): RedirectResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->claims->approve($claim, $request->user()->id, $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'อนุมัติใบเบิกเรียบร้อย');
    }

    /** บันทึกการจ่ายเงิน */
    public function pay(Request $request, ReimbursementClaim $claim): RedirectResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'payment_method' => ['required', 'in:transfer,cash,credit'],
            'payment_ref'    => ['nullable', 'string', 'max:120'],
        ], [
            'payment_method.required' => 'กรุณาเลือกวิธีการจ่าย',
        ]);

        try {
            $this->claims->markPaid(
                $claim,
                $request->user()->id,
                $data['payment_method'],
                $data['payment_ref'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'บันทึกการจ่ายเงินเรียบร้อย');
    }

    /** ปฏิเสธ */
    public function reject(Request $request, ReimbursementClaim $claim): RedirectResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'reject_reason' => ['required', 'string', 'max:255'],
        ], [
            'reject_reason.required' => 'กรุณาระบุเหตุผลที่ปฏิเสธ',
        ]);

        try {
            $this->claims->reject($claim, $request->user()->id, $data['reject_reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'ปฏิเสธใบเบิกแล้ว รายการถูกปลดให้ยื่นใหม่ได้');
    }

    // ────────────────────────────────────────────────────────────

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->hasAbility('approve-claim'), 403,
            'เฉพาะเจ้าของระบบเท่านั้นที่อนุมัติใบเบิกได้');
    }

    /** สรุปยอดทั้งระบบ */
    private function summary(): array
    {
        $rows = ReimbursementClaim::query()
            ->selectRaw('status, COUNT(*) c, COALESCE(SUM(total_amount),0) a')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'submitted_count'  => (int) ($rows['submitted']->c ?? 0),
            'submitted_amount' => (float) ($rows['submitted']->a ?? 0),
            'approved_count'   => (int) ($rows['approved']->c ?? 0),
            'approved_amount'  => (float) ($rows['approved']->a ?? 0),
            'paid_amount'      => (float) ($rows['paid']->a ?? 0),
        ];
    }

    /**
     * สายงานเหนือร้าน — ใช้แสดงว่าร้านนี้อยู่ใต้ใคร
     *
     * org_nodes.path เก็บเส้นทางของพ่อแม่ เช่น /1/2/3/4/
     * แยกออกมาเพื่อดึงชื่อทีละชั้น
     */
    private function uplineChain(ReimbursementClaim $claim): array
    {
        $node = $claim->claimant;

        if (! $node || ! $node->path) {
            return [];
        }

        $ids = array_filter(explode('/', $node->path));

        if ($ids === []) {
            return [];
        }

        return DB::table('org_nodes')
            ->whereIn('id', $ids)
            ->orderBy('level_id')
            ->pluck('name')
            ->all();
    }
}
