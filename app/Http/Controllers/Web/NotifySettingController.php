<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\NotificationQueue;
use App\Models\SocialLink;
use App\Models\SystemSetting;
use App\Services\SecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * ตั้งค่าการแจ้งเตือนของผู้ใช้ระบบ
 *
 * ผู้ใช้ระบบผูก LINE ได้หลายไอดี (ไว้แจ้งเตือนหลายคนในร้าน)
 * จำนวนสูงสุดกำหนดรายคนที่ users.max_social_links
 *
 * ต่างจากลูกค้าที่ผูกได้ 1 ไอดีต่อ 1 บัญชีเท่านั้น
 */
class NotifySettingController extends Controller
{
    private const LINK_STATE = 'roamembers.user_link_state';

    public function __construct(private SecurityService $security)
    {
    }

    /** หน้าตั้งค่าการแจ้งเตือน */
    public function index(Request $request): View
    {
        $user = $request->user();

        $links = SocialLink::where('owner_type', 'user')
            ->where('owner_id', $user->id)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        return view('profile.notify', [
            'links'    => $links,
            'maxLinks' => (int) ($user->max_social_links
                ?: SystemSetting::get('max_social_links_default', 5)),
            'recent'   => NotificationQueue::where('recipient_type', 'user')
                ->where('recipient_id', $user->id)
                ->latest()->limit(20)->get(),
        ]);
    }

    /** เริ่มผูก LINE เพิ่ม */
    public function linkLine(Request $request): RedirectResponse
    {
        $user = $request->user();

        $max = (int) ($user->max_social_links
            ?: SystemSetting::get('max_social_links_default', 5));

        $current = SocialLink::where('owner_type', 'user')
            ->where('owner_id', $user->id)
            ->where('provider', 'line')
            ->count();

        if ($current >= $max) {
            return back()->withErrors([
                'link' => "ผูก LINE ได้สูงสุด {$max} ไอดี กรุณาลบไอดีเดิมก่อน",
            ]);
        }

        $config = [
            'client_id' => config('services.line.client_id'),
        ];

        if (! $config['client_id']) {
            return back()->withErrors(['link' => 'ระบบยังไม่ได้เปิดใช้การผูก LINE']);
        }

        // state กัน CSRF — ต้องตรงกันตอน callback
        $state = Str::random(40);
        $request->session()->put(self::LINK_STATE, $state);

        return redirect()->away('https://access.line.me/oauth2/v2.1/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $config['client_id'],
            'redirect_uri'  => route('profile.notify.callback'),
            'state'         => $state,
            'scope'         => 'profile openid',
        ]));
    }

    /** LINE ส่งกลับมา */
    public function linkCallback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull(self::LINK_STATE);

        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            $this->security->log(
                SecurityService::E_DATA_TAMPER,
                'OAuth state ไม่ตรงกันตอนผูก LINE ของผู้ใช้ระบบ',
                'high',
            );

            return redirect()->route('profile.notify')
                ->withErrors(['link' => 'การเชื่อมต่อไม่ปลอดภัย กรุณาลองใหม่']);
        }

        if (! $request->query('code')) {
            return redirect()->route('profile.notify')
                ->withErrors(['link' => 'คุณยกเลิกการเชื่อมต่อ']);
        }

        try {
            $profile = $this->fetchLineProfile((string) $request->query('code'));
        } catch (\Throwable $e) {
            return redirect()->route('profile.notify')
                ->withErrors(['link' => 'เชื่อมต่อ LINE ไม่สำเร็จ: ' . $e->getMessage()]);
        }

        // ไอดีนี้ถูกผูกกับบัญชีอื่นแล้วหรือยัง
        $taken = SocialLink::where('provider', 'line')
            ->where('provider_uid', $profile['uid'])
            ->first();

        if ($taken && ! ($taken->owner_type === 'user' && $taken->owner_id === $request->user()->id)) {
            return redirect()->route('profile.notify')
                ->withErrors(['link' => 'LINE ไอดีนี้ถูกผูกกับบัญชีอื่นแล้ว']);
        }

        $user = $request->user();

        SocialLink::updateOrCreate(
            ['provider' => 'line', 'provider_uid' => $profile['uid']],
            [
                'owner_type'     => 'user',
                'owner_id'       => $user->id,
                'display_name'   => $profile['name'],
                'picture_url'    => $profile['picture'],
                'is_primary'     => ! SocialLink::where('owner_type', 'user')
                    ->where('owner_id', $user->id)->exists(),
                'notify_enabled' => true,
                'linked_at'      => now(),
            ],
        );

        return redirect()->route('profile.notify')->with('status', 'ผูก LINE เรียบร้อย');
    }

    /** เปิด/ปิดการรับแจ้งเตือนของไอดีนั้น */
    public function toggle(Request $request, SocialLink $link): RedirectResponse
    {
        $this->authorizeOwn($request, $link);

        $link->update(['notify_enabled' => ! $link->notify_enabled]);

        return back()->with('status',
            $link->notify_enabled ? 'เปิดรับแจ้งเตือนแล้ว' : 'ปิดรับแจ้งเตือนแล้ว');
    }

    /** ลบการผูก */
    public function unlink(Request $request, SocialLink $link): RedirectResponse
    {
        $this->authorizeOwn($request, $link);

        $link->delete();

        return back()->with('status', 'ยกเลิกการผูกเรียบร้อย');
    }

    // ────────────────────────────────────────────────────────────

    /** ต้องเป็นการผูกของตัวเองเท่านั้น */
    private function authorizeOwn(Request $request, SocialLink $link): void
    {
        if ($link->owner_type !== 'user' || $link->owner_id !== $request->user()->id) {
            $this->security->log(
                SecurityService::E_PERMISSION_DENIED,
                'พยายามแก้ไขการผูกบัญชีของคนอื่น',
                'high',
                ['link_id' => $link->id],
            );

            abort(404);
        }
    }

    private function fetchLineProfile(string $code): array
    {
        $token = \Illuminate\Support\Facades\Http::asForm()->timeout(15)
            ->post('https://api.line.me/oauth2/v2.1/token', [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => route('profile.notify.callback'),
                'client_id'     => config('services.line.client_id'),
                'client_secret' => config('services.line.client_secret'),
            ]);

        if ($token->failed()) {
            throw new \RuntimeException('แลก token ไม่สำเร็จ');
        }

        $profile = \Illuminate\Support\Facades\Http::withToken($token->json('access_token'))
            ->timeout(15)->get('https://api.line.me/v2/profile');

        if ($profile->failed()) {
            throw new \RuntimeException('ดึงโปรไฟล์ไม่สำเร็จ');
        }

        return [
            'uid'     => $profile->json('userId'),
            'name'    => $profile->json('displayName'),
            'picture' => $profile->json('pictureUrl'),
        ];
    }
}
