<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrgLevel;
use App\Http\Controllers\Controller;
use App\Models\OrgNode;
use App\Models\ShopPackage;
use App\Models\ShopSubscription;
use App\Services\SecurityService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * สมัครสมาชิกให้ร้านค้า — ตัวแทนขึ้นไปเป็นคนกรอก
 *
 * ตัวแทนเห็นเฉพาะร้านในสายงานตัวเอง
 * เจ้าของระบบเห็นทั้งหมด
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subs,
        private SecurityService $security,
    ) {
    }

    /** รายการสมาชิกร้านในสายงาน */
    public function index(Request $request): View
    {
        $this->authorizeManage();

        $user = $request->user();
        $visible = $user->visibleNodeIds();

        $query = ShopSubscription::with(['shop', 'package', 'recruiter'])
            ->whereIn('shop_node_id', $visible)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), function ($q, $term) {
                $q->whereHas('shop', fn ($w) => $w->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%"));
            });

        return view('subscriptions.index', [
            'subs'     => $query->orderByDesc('created_at')->paginate(25)->withQueryString(),
            'summary'  => $this->summary($visible),
            'expiring' => ShopSubscription::with('shop')
                ->whereIn('shop_node_id', $visible)
                ->where('status', 'active')
                ->whereBetween('ends_on', [now(), now()->addDays(30)])
                ->orderBy('ends_on')
                ->get(),
        ]);
    }

    /** ฟอร์มสมัครใหม่ */
    public function create(Request $request): View
    {
        $this->authorizeManage();

        return view('subscriptions.create', [
            'packages' => ShopPackage::active()->get(),
            'shops'    => $this->selectableShops($request->user()),
        ]);
    }

    /** บันทึกใบสมัคร */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'shop_node_id' => ['required', 'integer', 'exists:org_nodes,id'],
            'package_id'   => ['required', 'integer', 'exists:shop_packages,id'],
            'starts_on'    => ['nullable', 'date'],
            'auto_renew'   => ['nullable', 'boolean'],
            'note'         => ['nullable', 'string', 'max:1000'],
        ], [
            'shop_node_id.required' => 'กรุณาเลือกร้านค้า',
            'package_id.required'   => 'กรุณาเลือกแพ็กเกจ',
        ]);

        $shop = OrgNode::findOrFail($data['shop_node_id']);
        $user = $request->user();

        // ร้านต้องอยู่ในสายงานที่ตัวเองดูแล
        if (! $user->canAccessNode($shop->id)) {
            $this->security->log(
                SecurityService::E_PERMISSION_DENIED,
                'พยายามสมัครสมาชิกให้ร้านนอกสายงานตัวเอง',
                'high',
                ['shop_node_id' => $shop->id],
            );

            abort(403, 'ร้านนี้ไม่ได้อยู่ในสายงานของคุณ');
        }

        // ต้องเป็นร้านค้าจริง ไม่ใช่คลังหรือตัวแทน
        if ($this->levelOf($shop) !== OrgLevel::Shop->value) {
            return back()
                ->withErrors(['shop_node_id' => 'สมัครสมาชิกได้เฉพาะหน่วยงานระดับร้านค้าเท่านั้น'])
                ->withInput();
        }

        try {
            $sub = $this->subs->subscribe(
                shop: $shop,
                package: ShopPackage::findOrFail($data['package_id']),
                recruiter: $user->node,
                startsOn: $data['starts_on'] ?? null,
                autoRenew: (bool) ($data['auto_renew'] ?? false),
                note: $data['note'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['shop_node_id' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('subscriptions.show', $sub)
            ->with('status', 'สร้างใบสมัครเรียบร้อย');
    }

    /** รายละเอียดใบสมัคร */
    public function show(Request $request, ShopSubscription $subscription): View
    {
        $this->authorizeManage();
        $this->authorizeScope($request, $subscription);

        return view('subscriptions.show', [
            'sub'        => $subscription->load(['shop', 'package', 'recruiter']),
            'allowances' => $subscription->allowances()->orderByDesc('period_ym')->limit(12)->get(),
        ]);
    }

    /** ยืนยันการชำระเงิน */
    public function confirmPayment(Request $request, ShopSubscription $subscription): RedirectResponse
    {
        $this->authorizeManage();
        $this->authorizeScope($request, $subscription);

        $data = $request->validate([
            'payment_ref' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $this->subs->confirmPayment(
                $subscription,
                $request->user()->id,
                $data['payment_ref'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'ยืนยันการชำระเงินแล้ว ร้านเริ่มรับแลกแต้มได้ทันที');
    }

    /** ยกเลิกสมาชิก */
    public function cancel(Request $request, ShopSubscription $subscription): RedirectResponse
    {
        $this->authorizeManage();
        $this->authorizeScope($request, $subscription);

        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:255'],
        ], [
            'cancel_reason.required' => 'กรุณาระบุเหตุผลที่ยกเลิก',
        ]);

        try {
            $this->subs->cancel($subscription, $data['cancel_reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'ยกเลิกสมาชิกเรียบร้อย');
    }

    /** ต่ออายุ */
    public function renew(Request $request, ShopSubscription $subscription): RedirectResponse
    {
        $this->authorizeManage();
        $this->authorizeScope($request, $subscription);

        $data = $request->validate([
            'package_id' => ['nullable', 'integer', 'exists:shop_packages,id'],
        ]);

        try {
            $new = $this->subs->renew(
                $subscription,
                isset($data['package_id']) ? ShopPackage::find($data['package_id']) : null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()
            ->route('subscriptions.show', $new)
            ->with('status', 'ต่ออายุเรียบร้อย');
    }

    // ────────────────────────────────────────────────────────────

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->hasAbility('manage-subscriptions'), 403,
            'คุณไม่มีสิทธิ์จัดการสมาชิกร้านค้า');
    }

    /** ใบสมัครต้องอยู่ในสายงานที่ตัวเองดูแล */
    private function authorizeScope(Request $request, ShopSubscription $sub): void
    {
        if (! $request->user()->canAccessNode($sub->shop_node_id)) {
            $this->security->log(
                SecurityService::E_PERMISSION_DENIED,
                'พยายามเข้าถึงใบสมัครนอกสายงานตัวเอง',
                'high',
                ['subscription_id' => $sub->id],
            );

            abort(404);
        }
    }

    /** ร้านที่เลือกได้ — เฉพาะระดับร้านค้าในสายงานตัวเอง ที่ยังไม่มีสมาชิก */
    private function selectableShops($user)
    {
        $taken = ShopSubscription::whereIn('status', ['active', 'pending_payment'])
            ->pluck('shop_node_id');

        return OrgNode::whereIn('id', $user->visibleNodeIds())
            ->where('level_id', OrgLevel::Shop->value)
            ->whereNotIn('id', $taken)
            ->orderBy('name')
            ->get();
    }

    /** level_id ถูก cast เป็น enum จึงต้องอ่าน ->value */
    private function levelOf(OrgNode $node): int
    {
        return $node->level_id instanceof OrgLevel
            ? $node->level_id->value
            : (int) $node->level_id;
    }

    private function summary(array $visible): array
    {
        $rows = ShopSubscription::whereIn('shop_node_id', $visible)
            ->selectRaw('status, COUNT(*) c, COALESCE(SUM(price_paid),0) revenue, COALESCE(SUM(commission_amount),0) comm')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'active'      => (int) ($rows['active']->c ?? 0),
            'pending'     => (int) ($rows['pending_payment']->c ?? 0),
            'revenue'     => (float) ($rows['active']->revenue ?? 0),
            'commission'  => (float) ($rows['active']->comm ?? 0),
        ];
    }
}
