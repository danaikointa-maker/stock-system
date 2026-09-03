<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Services\PointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** จัดการลูกค้า คะแนนสะสม ของรางวัล และคำขอแลก — ต้องมี ability `view-reports` ขึ้นไป */
class CustomerController extends Controller
{
    public function __construct(private PointService $points) {}

    public function index(Request $request): View
    {
        $this->authorize('view-reports');

        $q = trim((string) $request->query('q', ''));

        return view('customers.index', [
            'q'         => $q,
            'customers' => Customer::when($q !== '', fn ($b) => $b->where(fn ($w) => $w
                    ->where('phone', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")))
                ->orderByDesc('points_balance')
                ->paginate(25)
                ->withQueryString(),
            'totals'    => [
                'customers' => Customer::count(),
                'points'    => (int) Customer::sum('points_balance'),
                'blocked'   => Customer::where('status', 'blocked')->count(),
            ],
        ]);
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view-reports');

        return view('customers.show', [
            'customer' => $customer,
            'history'  => $customer->pointTransactions()->latest('id')->limit(50)->get(),
            'redeems'  => $customer->rewardRedemptions()->with('reward')->latest('id')->get(),
            'audit'    => $customer->recalculatedBalance(),
        ]);
    }

    /** ระงับ / ปลดระงับลูกค้าที่ส่อโกงคะแนน */
    public function toggle(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('manage-members');

        $customer->update([
            'status' => $customer->isBlocked() ? 'active' : 'blocked',
        ]);

        return back()->with('ok', $customer->isBlocked()
            ? "ระงับการรับคะแนนของ {$customer->phone} แล้ว"
            : "ปลดระงับ {$customer->phone} แล้ว");
    }

    /** ปรับคะแนนด้วยมือ (กรณีเคลมหรือแก้ข้อผิดพลาด) */
    public function adjustPoints(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('manage-members');

        $data = $request->validate([
            'points' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'note'   => ['required', 'string', 'max:255'],
        ], [
            'points.not_in' => 'จำนวนคะแนนต้องไม่เป็นศูนย์',
            'note.required' => 'ต้องระบุเหตุผลในการปรับคะแนนเพื่อการตรวจสอบย้อนหลัง',
        ]);

        try {
            if ($data['points'] > 0) {
                $this->points->earn(
                    customer: $customer, points: $data['points'],
                    type: 'adjust', note: $data['note'],
                );
            } else {
                $this->points->deduct(
                    customer: $customer, points: abs($data['points']),
                    type: 'adjust', note: $data['note'],
                );
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['points' => $e->getMessage()]);
        }

        return back()->with('ok', 'ปรับคะแนนเรียบร้อย');
    }

    /** รายการของรางวัล */
    public function rewards(): View
    {
        $this->authorize('view-reports');

        return view('customers.rewards', [
            'rewards' => Reward::orderByDesc('status')->orderBy('points_cost')->get(),
            'pending' => RewardRedemption::with(['customer', 'reward'])
                ->where('status', 'pending')->latest('id')->get(),
            'done'    => RewardRedemption::with(['customer', 'reward'])
                ->where('status', '!=', 'pending')->latest('id')->limit(20)->get(),
        ]);
    }

    public function storeReward(Request $request): RedirectResponse
    {
        $this->authorize('manage-products');

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'points_cost' => ['required', 'integer', 'min:1'],
            'stock_qty'   => ['required', 'integer', 'min:0'],
            'status'      => ['required', 'in:active,inactive'],
        ]);

        Reward::create($data);

        return back()->with('ok', "เพิ่มของรางวัล \"{$data['name']}\" เรียบร้อย");
    }

    public function updateReward(Request $request, Reward $reward): RedirectResponse
    {
        $this->authorize('manage-products');

        $reward->update($request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'points_cost' => ['required', 'integer', 'min:1'],
            'stock_qty'   => ['required', 'integer', 'min:0'],
            'status'      => ['required', 'in:active,inactive'],
        ]));

        return back()->with('ok', 'บันทึกของรางวัลเรียบร้อย');
    }

    /** อัปเดตสถานะการจัดส่งของรางวัล */
    public function shipRedemption(Request $request, RewardRedemption $redemption): RedirectResponse
    {
        $this->authorize('manage-products');

        $data = $request->validate([
            'status' => ['required', 'in:shipped,rejected'],
        ]);

        if ($redemption->status !== 'pending') {
            return back()->withErrors(['redeem' => 'รายการนี้ดำเนินการไปแล้ว']);
        }

        // ยกเลิก = คืนคะแนนให้ลูกค้า + คืนของรางวัลเข้าสต๊อก
        if ($data['status'] === 'rejected') {
            $this->points->earn(
                customer: $redemption->customer,
                points: $redemption->points_used,
                type: 'adjust',
                refType: RewardRedemption::class,
                refId: $redemption->id,
                note: 'คืนคะแนนจากการยกเลิกแลกของรางวัล',
            );
            $redemption->reward()->increment('stock_qty');
        }

        $redemption->update(['status' => $data['status']]);

        return back()->with('ok', $data['status'] === 'shipped'
            ? 'บันทึกการจัดส่งเรียบร้อย'
            : 'ยกเลิกและคืนคะแนนให้ลูกค้าเรียบร้อย');
    }
}
