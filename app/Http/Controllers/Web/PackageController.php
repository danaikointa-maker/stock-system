<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ShopPackage;
use App\Models\ShopSubscription;
use App\Models\SystemSetting;
use App\Services\SecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * จัดการแพ็กเกจสมาชิก และค่าของแต้ม — เฉพาะเจ้าของระบบ
 *
 * แพ็กเกจที่ถูกใช้งานอยู่แล้วแก้ได้ แต่ไม่กระทบสัญญาเดิม
 * เพราะใบสมัครล็อกค่าไว้ตอนสมัคร
 */
class PackageController extends Controller
{
    public function __construct(private SecurityService $security)
    {
    }

    public function index(Request $request): View
    {
        $this->authorizeOwner();

        return view('admin.packages.index', [
            'packages'   => ShopPackage::orderBy('sort_order')->get(),
            'usage'      => ShopSubscription::selectRaw('package_id, COUNT(*) c')
                ->groupBy('package_id')->pluck('c', 'package_id'),
            'pointValue' => (float) SystemSetting::get('point_value_baht', 0.25),
            'settings'   => SystemSetting::where('owner_only', true)->orderBy('skey')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeOwner();

        $data = $this->validatePackage($request);
        $data['created_by'] = $request->user()->id;

        ShopPackage::create($data);

        return back()->with('status', 'เพิ่มแพ็กเกจเรียบร้อย');
    }

    public function update(Request $request, ShopPackage $package): RedirectResponse
    {
        $this->authorizeOwner();

        $package->update($this->validatePackage($request, $package->id));

        return back()->with('status', 'แก้ไขแพ็กเกจเรียบร้อย (สัญญาเดิมของร้านไม่เปลี่ยน)');
    }

    public function toggle(Request $request, ShopPackage $package): RedirectResponse
    {
        $this->authorizeOwner();

        $package->update(['is_active' => ! $package->is_active]);

        return back()->with('status', $package->is_active ? 'เปิดใช้แพ็กเกจแล้ว' : 'ปิดแพ็กเกจแล้ว');
    }

    /**
     * เปลี่ยนค่าของ 1 แต้ม
     *
     * เรื่องนี้กระทบเงินทั้งระบบ จึงบันทึกประวัติทุกครั้ง
     * และไม่ย้อนหลังกับใบเบิกที่ออกไปแล้ว เพราะใบเบิกล็อก point_value ไว้
     */
    public function updatePointValue(Request $request): RedirectResponse
    {
        $this->authorizeOwner();

        $data = $request->validate([
            'point_value_baht' => ['required', 'numeric', 'min:0.0001', 'max:1000'],
            'reason'           => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'กรุณาระบุเหตุผลที่เปลี่ยนค่าแต้ม',
        ]);

        $old = (float) SystemSetting::get('point_value_baht', 0.25);
        $new = (float) $data['point_value_baht'];

        if (abs($old - $new) < 0.00001) {
            return back()->withErrors(['point_value_baht' => 'ค่าใหม่เหมือนค่าเดิม']);
        }

        DB::transaction(function () use ($old, $new, $data, $request) {
            SystemSetting::put('point_value_baht', (string) $new);

            DB::table('point_value_history')->insert([
                'old_value'    => $old,
                'new_value'    => $new,
                'reason'       => $data['reason'],
                'effective_at' => now(),
                'changed_by'   => $request->user()->id,
                'created_at'   => now(),
            ]);
        });

        $this->security->log(
            SecurityService::E_SETTING_CHANGED,
            "เปลี่ยนค่าแต้มจาก {$old} เป็น {$new} บาท",
            'high',
            ['old' => $old, 'new' => $new, 'reason' => $data['reason']],
        );

        return back()->with('status', 'เปลี่ยนค่าแต้มเรียบร้อย บันทึกประวัติแล้ว');
    }

    /** แก้ค่าตั้งค่าทั่วไป */
    public function updateSetting(Request $request): RedirectResponse
    {
        $this->authorizeOwner();

        $data = $request->validate([
            'skey'   => ['required', 'string', 'exists:system_settings,skey'],
            'svalue' => ['required', 'string', 'max:255'],
        ]);

        // ค่าแต้มต้องใช้ช่องทางที่บันทึกประวัติเท่านั้น
        if ($data['skey'] === 'point_value_baht') {
            return back()->withErrors(['svalue' => 'ค่าแต้มต้องแก้ผ่านฟอร์มเฉพาะที่บันทึกประวัติ']);
        }

        $old = SystemSetting::where('skey', $data['skey'])->value('svalue');
        SystemSetting::put($data['skey'], $data['svalue']);

        $this->security->log(
            SecurityService::E_SETTING_CHANGED,
            "แก้ค่าตั้งค่า {$data['skey']} จาก {$old} เป็น {$data['svalue']}",
            'medium',
            ['key' => $data['skey'], 'old' => $old, 'new' => $data['svalue']],
        );

        return back()->with('status', 'บันทึกค่าตั้งค่าเรียบร้อย');
    }

    private function authorizeOwner(): void
    {
        abort_unless(auth()->user()?->hasAbility('manage-packages'), 403,
            'เฉพาะเจ้าของระบบเท่านั้น');
    }

    private function validatePackage(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'                 => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9\-_]+$/',
                'unique:shop_packages,code' . ($ignoreId ? ",{$ignoreId}" : ''),
            ],
            'name'                 => ['required', 'string', 'max:120'],
            'tagline'              => ['nullable', 'string', 'max:200'],
            'duration_months'      => ['required', 'integer', 'min:1', 'max:120'],
            'monthly_point_limit'  => ['required', 'integer', 'min:0', 'max:100000000'],
            'price'                => ['required', 'numeric', 'min:0', 'max:10000000'],
            'allow_rollover'       => ['nullable', 'boolean'],
            'allow_cash_redeem'    => ['nullable', 'boolean'],
            'agent_commission_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'sort_order'           => ['nullable', 'integer', 'min:0', 'max:999'],
            'note'                 => ['nullable', 'string', 'max:1000'],
        ], [
            'code.regex'    => 'รหัสแพ็กเกจใช้ได้เฉพาะ A-Z 0-9 - _ เท่านั้น',
            'code.unique'   => 'รหัสแพ็กเกจนี้มีอยู่แล้ว',
            'name.required' => 'กรุณาระบุชื่อแพ็กเกจ',
        ]);
    }
}
