<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AdminAlert;
use App\Models\AuditTrail;
use App\Models\BlockedEntity;
use App\Models\ErrorLog;
use App\Models\LoginAttempt;
use App\Models\SecurityEvent;
use App\Services\SecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ศูนย์ตรวจสอบความปลอดภัย — เฉพาะเจ้าของระบบ
 *
 * รวมหลักฐานทุกอย่างไว้ที่เดียว
 *   - เหตุการณ์ความปลอดภัย (พยายามแฮค/สิทธิ์ไม่พอ/ผิดปกติ)
 *   - แจ้งเตือนที่ต้องจัดการ
 *   - ประวัติการแก้ไขข้อมูล (ใครแก้อะไร ค่าเดิม -> ค่าใหม่)
 *   - ประวัติการเข้าสู่ระบบที่ล้มเหลว
 *   - ข้อผิดพลาดของระบบ
 *   - IP/บัญชี ที่ถูกระงับ
 */
class SecurityLogController extends Controller
{
    public function __construct(private SecurityService $security)
    {
    }

    /** ภาพรวม */
    public function index(Request $request): View
    {
        $this->authorizeOwner();

        return view('admin.security.index', [
            'stats'   => $this->stats(),
            'alerts'  => AdminAlert::unhandled()->latest()->limit(10)->get(),
            'serious' => SecurityEvent::serious()->unreviewed()->latest()->limit(10)->get(),
            'blocked' => BlockedEntity::where('is_active', true)->latest()->limit(10)->get(),
        ]);
    }

    /** เหตุการณ์ความปลอดภัย */
    public function events(Request $request): View
    {
        $this->authorizeOwner();

        $query = SecurityEvent::query()
            ->when($request->query('type'), fn ($q, $t) => $q->where('event_type', $t))
            ->when($request->query('severity'), fn ($q, $s) => $q->where('severity', $s))
            ->when($request->query('ip'), fn ($q, $ip) => $q->where('ip_address', $ip))
            ->when($request->query('unreviewed'), fn ($q) => $q->where('is_reviewed', false));

        return view('admin.security.events', [
            'events' => $query->latest()->paginate(50)->withQueryString(),
            'types'  => SecurityEvent::select('event_type')
                ->groupBy('event_type')->pluck('event_type'),
        ]);
    }

    /** ทำเครื่องหมายว่าตรวจสอบแล้ว */
    public function reviewEvent(Request $request, SecurityEvent $event): RedirectResponse
    {
        $this->authorizeOwner();

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:255'],
        ]);

        $event->update([
            'is_reviewed' => true,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
        ]);

        return back()->with('status', 'บันทึกการตรวจสอบแล้ว');
    }

    /** ประวัติการแก้ไขข้อมูล */
    public function audits(Request $request): View
    {
        $this->authorizeOwner();

        $query = AuditTrail::with('user')
            ->when($request->query('model'), fn ($q, $m) => $q->where('auditable_type', 'like', "%{$m}%"))
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', $a))
            ->when($request->query('user'), fn ($q, $u) => $q->where('user_id', $u));

        return view('admin.security.audits', [
            'audits' => $query->latest()->paginate(50)->withQueryString(),
        ]);
    }

    /** ประวัติการเข้าสู่ระบบ */
    public function logins(Request $request): View
    {
        $this->authorizeOwner();

        $query = LoginAttempt::query()
            ->when($request->query('failed'), fn ($q) => $q->where('succeeded', false))
            ->when($request->query('ip'), fn ($q, $ip) => $q->where('ip_address', $ip));

        return view('admin.security.logins', [
            'logins' => $query->latest()->paginate(50)->withQueryString(),
            // IP ที่ล็อกอินพลาดบ่อยผิดปกติใน 24 ชม.
            'suspects' => LoginAttempt::selectRaw('ip_address, COUNT(*) c')
                ->where('succeeded', false)
                ->where('created_at', '>=', now()->subDay())
                ->groupBy('ip_address')
                ->havingRaw('COUNT(*) >= 5')
                ->orderByDesc('c')
                ->limit(10)
                ->get(),
        ]);
    }

    /** ข้อผิดพลาดของระบบ */
    public function errors(Request $request): View
    {
        $this->authorizeOwner();

        // ห้ามใช้ชื่อ 'errors' เพราะชนกับ MessageBag ของ Laravel
        // ที่ layout ใช้แสดงข้อความ validation
        return view('admin.security.errors', [
            'errorLogs' => ErrorLog::when(
                ! $request->query('all'),
                fn ($q) => $q->where('is_resolved', false),
            )->latest('last_seen_at')->paginate(30)->withQueryString(),
        ]);
    }

    /** ปิดงานแจ้งเตือน */
    public function handleAlert(Request $request, AdminAlert $alert): RedirectResponse
    {
        $this->authorizeOwner();

        $data = $request->validate([
            'status'      => ['required', 'in:acknowledged,resolved,ignored'],
            'handle_note' => ['nullable', 'string', 'max:255'],
        ]);

        $alert->update([
            'status'      => $data['status'],
            'handled_by'  => $request->user()->id,
            'handled_at'  => now(),
            'handle_note' => $data['handle_note'] ?? null,
        ]);

        return back()->with('status', 'อัปเดตสถานะแจ้งเตือนแล้ว');
    }

    /** ระงับ IP ด้วยตนเอง */
    public function block(Request $request): RedirectResponse
    {
        $this->authorizeOwner();

        $data = $request->validate([
            'entity_type'  => ['required', 'in:ip,user,customer,phone,device'],
            'entity_value' => ['required', 'string', 'max:191'],
            'reason'       => ['required', 'string', 'max:255'],
            'minutes'      => ['nullable', 'integer', 'min:1', 'max:525600'],
            'permanent'    => ['nullable', 'boolean'],
        ], [
            'reason.required' => 'กรุณาระบุเหตุผล',
        ]);

        $this->security->block(
            $data['entity_type'],
            $data['entity_value'],
            $data['reason'],
            $data['minutes'] ?? 60,
            (bool) ($data['permanent'] ?? false),
        );

        return back()->with('status', 'ระงับการใช้งานเรียบร้อย');
    }

    /** ปลดระงับ */
    public function unblock(Request $request, BlockedEntity $blocked): RedirectResponse
    {
        $this->authorizeOwner();

        $blocked->update(['is_active' => false]);

        $this->security->log(
            'entity_unblocked',
            "ปลดระงับ {$blocked->entity_type}: {$blocked->entity_value}",
            'info',
            ['entity_id' => $blocked->id],
        );

        return back()->with('status', 'ปลดระงับเรียบร้อย');
    }

    // ────────────────────────────────────────────────────────────

    private function authorizeOwner(): void
    {
        abort_unless(auth()->user()?->hasAbility('view-security'), 403,
            'เฉพาะเจ้าของระบบเท่านั้นที่ดูบันทึกความปลอดภัยได้');
    }

    private function stats(): array
    {
        return [
            'alerts_new'      => AdminAlert::where('status', 'new')->count(),
            'events_serious'  => SecurityEvent::serious()->unreviewed()->count(),
            'events_today'    => SecurityEvent::whereDate('created_at', today())->count(),
            'failed_logins'   => LoginAttempt::where('succeeded', false)
                ->where('created_at', '>=', now()->subDay())->count(),
            'blocked_active'  => BlockedEntity::where('is_active', true)->count(),
            'errors_open'     => ErrorLog::where('is_resolved', false)->count(),
        ];
    }
}
