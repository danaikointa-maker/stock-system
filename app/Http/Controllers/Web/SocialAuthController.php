<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SocialLink;
use App\Services\SecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * เข้าสู่ระบบด้วย LINE หรือ Google สำหรับ "ลูกค้าปลายทาง"
 *
 * เขียนเรียก OAuth เองโดยไม่พึ่ง Socialite เพื่อลด dependency
 * และควบคุมเรื่องความปลอดภัยได้ละเอียดกว่า
 *
 * มาตรการความปลอดภัย
 *   - state parameter กัน CSRF (บังคับตรวจทุกครั้ง)
 *   - ตรวจ provider ว่าอยู่ในรายการที่อนุญาต
 *   - 1 LINE/Google = 1 บัญชีลูกค้าเท่านั้น
 *   - บันทึกทุกการเข้าสู่ระบบไว้ตรวจสอบ
 */
class SocialAuthController extends Controller
{
    private const SESSION_KEY = 'roamembers.customer_id';
    private const STATE_KEY = 'roamembers.oauth_state';
    private const ALLOWED = ['line', 'google'];

    public function __construct(private SecurityService $security)
    {
    }

    /** พาไปหน้าอนุญาตของผู้ให้บริการ */
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::ALLOWED, true), 404);

        $config = $this->config($provider);

        if (! $config['client_id']) {
            return redirect()->route('scan.form')
                ->withErrors(['phone' => 'ระบบยังไม่ได้เปิดใช้การเข้าสู่ระบบด้วย ' . strtoupper($provider)]);
        }

        // state กัน CSRF — ต้องตรงกันตอน callback
        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);
        $request->session()->put(self::STATE_KEY . '.provider', $provider);

        $params = [
            'response_type' => 'code',
            'client_id'     => $config['client_id'],
            'redirect_uri'  => route('social.callback', $provider),
            'state'         => $state,
            'scope'         => $config['scope'],
        ];

        return redirect()->away($config['auth_url'] . '?' . http_build_query($params));
    }

    /** ผู้ให้บริการส่งกลับมาพร้อม code */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::ALLOWED, true), 404);

        // ตรวจ state ก่อนอย่างอื่นเสมอ
        $expected = $request->session()->pull(self::STATE_KEY);
        $expectedProvider = $request->session()->pull(self::STATE_KEY . '.provider');

        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))
            || $expectedProvider !== $provider) {
            $this->security->log(
                SecurityService::E_DATA_TAMPER,
                "OAuth state ไม่ตรงกัน (provider: {$provider}) — อาจเป็นการโจมตีแบบ CSRF",
                'high',
                ['provider' => $provider],
            );

            return redirect()->route('scan.form')
                ->withErrors(['phone' => 'การเข้าสู่ระบบไม่ปลอดภัย กรุณาลองใหม่']);
        }

        if ($request->query('error') || ! $request->query('code')) {
            return redirect()->route('scan.form')
                ->withErrors(['phone' => 'คุณยกเลิกการเข้าสู่ระบบ']);
        }

        try {
            $profile = $this->fetchProfile($provider, (string) $request->query('code'));
        } catch (\Throwable $e) {
            $this->security->log(
                SecurityService::E_LOGIN_FAILED,
                "ดึงข้อมูลผู้ใช้จาก {$provider} ไม่สำเร็จ: {$e->getMessage()}",
                'medium',
                ['provider' => $provider],
            );

            return redirect()->route('scan.form')
                ->withErrors(['phone' => 'เชื่อมต่อ ' . strtoupper($provider) . ' ไม่สำเร็จ กรุณาลองใหม่']);
        }

        $customer = $this->findOrCreateCustomer($provider, $profile);

        $request->session()->put(self::SESSION_KEY, $customer->id);
        $this->security->logLogin($profile['uid'], true, null, null, 'customer');

        // ยังไม่มีเบอร์ = ต้องขอเพิ่มเพื่อใช้ยืนยันตัวตน
        if (! $customer->phone) {
            return redirect()->route('scan.form')
                ->with('status', 'เข้าสู่ระบบสำเร็จ กรุณาเพิ่มเบอร์โทรเพื่อใช้สะสมแต้ม');
        }

        return redirect()->route('scan.wallet')->with('status', 'เข้าสู่ระบบสำเร็จ');
    }

    /**
     * หาบัญชีเดิมหรือสร้างใหม่
     *
     * กติกา: 1 LINE/Google ผูกได้กับลูกค้า 1 คนเท่านั้น
     */
    private function findOrCreateCustomer(string $provider, array $profile): Customer
    {
        $link = SocialLink::where('provider', $provider)
            ->where('provider_uid', $profile['uid'])
            ->where('owner_type', 'customer')
            ->first();

        if ($link) {
            $customer = Customer::find($link->owner_id);

            if ($customer) {
                $link->update([
                    'display_name' => $profile['name'],
                    'picture_url'  => $profile['picture'],
                    'email'        => $profile['email'],
                ]);

                return $customer;
            }
        }

        // ยังไม่เคยผูก — สร้างบัญชีใหม่ (ยังไม่มีเบอร์ ให้กรอกทีหลัง)
        $customer = Customer::create([
            'name'           => $profile['name'] ?: 'สมาชิกใหม่',
            'phone'          => null,
            'line_user_id'   => $provider === 'line' ? $profile['uid'] : null,
            'points_balance' => 0,
            'status'         => 'active',
        ]);

        SocialLink::create([
            'owner_type'   => 'customer',
            'owner_id'     => $customer->id,
            'provider'     => $provider,
            'provider_uid' => $profile['uid'],
            'display_name' => $profile['name'],
            'picture_url'  => $profile['picture'],
            'email'        => $profile['email'],
            'is_primary'   => true,
            'linked_at'    => now(),
        ]);

        return $customer;
    }

    /** แลก code เป็น access token แล้วดึงโปรไฟล์ */
    private function fetchProfile(string $provider, string $code): array
    {
        $config = $this->config($provider);

        $tokenResponse = Http::asForm()->timeout(15)->post($config['token_url'], [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => route('social.callback', $provider),
            'client_id'     => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ]);

        if ($tokenResponse->failed()) {
            throw new \RuntimeException('แลก token ไม่สำเร็จ');
        }

        $accessToken = $tokenResponse->json('access_token');

        if (! $accessToken) {
            throw new \RuntimeException('ไม่ได้รับ access token');
        }

        $profileResponse = Http::withToken($accessToken)->timeout(15)->get($config['profile_url']);

        if ($profileResponse->failed()) {
            throw new \RuntimeException('ดึงโปรไฟล์ไม่สำเร็จ');
        }

        $data = $profileResponse->json();

        return $provider === 'line'
            ? [
                'uid'     => $data['userId'] ?? '',
                'name'    => $data['displayName'] ?? '',
                'picture' => $data['pictureUrl'] ?? null,
                'email'   => null,
            ]
            : [
                'uid'     => $data['sub'] ?? '',
                'name'    => $data['name'] ?? '',
                'picture' => $data['picture'] ?? null,
                'email'   => $data['email'] ?? null,
            ];
    }

    /** ค่าตั้งของแต่ละผู้ให้บริการ */
    private function config(string $provider): array
    {
        return $provider === 'line'
            ? [
                'client_id'     => config('services.line.client_id'),
                'client_secret' => config('services.line.client_secret'),
                'auth_url'      => 'https://access.line.me/oauth2/v2.1/authorize',
                'token_url'     => 'https://api.line.me/oauth2/v2.1/token',
                'profile_url'   => 'https://api.line.me/v2/profile',
                'scope'         => 'profile openid',
            ]
            : [
                'client_id'     => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'auth_url'      => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url'     => 'https://oauth2.googleapis.com/token',
                'profile_url'   => 'https://openidconnect.googleapis.com/v1/userinfo',
                'scope'         => 'openid profile email',
            ];
    }
}
