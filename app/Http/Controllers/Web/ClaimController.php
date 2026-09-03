<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrgLevel;
use App\Http\Controllers\Controller;
use App\Models\OrgNode;
use App\Models\PointRedemption;
use App\Models\ReimbursementClaim;
use App\Services\ClaimService;
use App\Services\SecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * ใบเบิกเงินคืน — ฝั่งร้านค้า
 *
 * ร้านดูว่ามีงวดไหนยังไม่ได้เบิก สร้างใบเบิก แล้วยื่นให้เจ้าของระบบ
 * เบิกได้เฉพาะงวดที่ผ่านมาแล้ว เพราะงวดปัจจุบันยอดยังเปลี่ยนได้
 */
class ClaimController extends Controller
{
    public function __construct(
        private ClaimService $claims,
        private SecurityService $security,
    ) {
    }

    /** รายการใบเบิกของร้าน */
    public function index(Request $request): View
    {
        $this->authorizeClaim();

        $shop = $this->currentShop();

        return view('claims.index', [
            'shop'      => $shop,
            'claims'    => ReimbursementClaim::where('claimant_node_id', $shop->id)
                ->orderByDesc('period_ym')->paginate(20),
            'unclaimed' => $this->claims->unclaimedPeriods($shop),
            'pending'   => $this->pendingSummary($shop),
        ]);
    }

    /** สร้างใบเบิกจากงวดที่เลือก */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeClaim();

        $data = $request->validate([
            'period_ym' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ], [
            'period_ym.regex' => 'รูปแบบงวดไม่ถูกต้อง',
        ]);

        try {
            $claim = $this->claims->createDraft($this->currentShop(), $data['period_ym']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['period_ym' => $e->getMessage()]);
        }

        return redirect()
            ->route('claims.show', $claim)
            ->with('status', 'สร้างใบเบิกเรียบร้อย ตรวจสอบแล้วกดยื่นได้เลย');
    }

    /** รายละเอียดใบเบิก */
    public function show(Request $request, ReimbursementClaim $claim): View
    {
        $this->authorizeClaim();
        $this->authorizeOwn($claim);

        return view('claims.show', [
            'claim' => $claim,
            'shop'  => $claim->claimant,
            'items' => PointRedemption::where('claim_id', $claim->id)
                ->orderByDesc('redeemed_at')->get(),
        ]);
    }

    /** ยื่นใบเบิก */
    public function submit(Request $request, ReimbursementClaim $claim): RedirectResponse
    {
        $this->authorizeClaim();
        $this->authorizeOwn($claim);

        try {
            $this->claims->submit($claim);
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'ยื่นใบเบิกเรียบร้อย รอเจ้าของระบบอนุมัติ');
    }

    /** ยกเลิกใบร่าง */
    public function destroy(Request $request, ReimbursementClaim $claim): RedirectResponse
    {
        $this->authorizeClaim();
        $this->authorizeOwn($claim);

        try {
            $this->claims->discardDraft($claim);
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('claims.index')->with('status', 'ยกเลิกใบร่างเรียบร้อย');
    }

    // ────────────────────────────────────────────────────────────

    private function authorizeClaim(): void
    {
        abort_unless(auth()->user()?->hasAbility('claim-money'), 403,
            'คุณไม่มีสิทธิ์ยื่นเบิกเงิน');
    }

    /** ใบเบิกต้องเป็นของร้านตัวเองเท่านั้น */
    private function authorizeOwn(ReimbursementClaim $claim): void
    {
        if ($claim->claimant_node_id !== $this->currentShop()->id) {
            $this->security->log(
                SecurityService::E_PERMISSION_DENIED,
                'พยายามเข้าถึงใบเบิกเงินของร้านอื่น',
                'high',
                ['claim_id' => $claim->id, 'owner' => $claim->claimant_node_id],
            );

            abort(404);
        }
    }

    private function currentShop(): OrgNode
    {
        $node = auth()->user()?->node;

        abort_unless($node, 403, 'บัญชีของคุณยังไม่ได้ผูกกับหน่วยงาน');

        $level = $node->level_id instanceof OrgLevel
            ? $node->level_id->value
            : (int) $node->level_id;

        if ($level === OrgLevel::Seller->value && $node->parent_id) {
            return OrgNode::findOrFail($node->parent_id);
        }

        return $node;
    }

    /** ยอดรวมที่ยังไม่ได้เบิกทั้งหมด */
    private function pendingSummary(OrgNode $shop): array
    {
        $rows = PointRedemption::query()
            ->where('accepting_node_id', $shop->id)
            ->where('status', 'confirmed')
            ->whereNull('claim_id')
            ->selectRaw('COUNT(*) c, COALESCE(SUM(points_used),0) p, COALESCE(SUM(cash_value),0) a')
            ->first();

        return [
            'count'  => (int) ($rows->c ?? 0),
            'points' => (int) ($rows->p ?? 0),
            'amount' => (float) ($rows->a ?? 0),
        ];
    }
}
