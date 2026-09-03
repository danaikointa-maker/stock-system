<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('dashboard') : view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login'    => ['required', 'string'],   // อีเมลหรือเบอร์โทร
            'password' => ['required', 'string'],
        ], [], ['login' => 'อีเมล/เบอร์โทร', 'password' => 'รหัสผ่าน']);

        $throttleKey = strtolower($data['login']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'login' => 'พยายามเข้าสู่ระบบมากเกินไป กรุณารออีก '
                    . RateLimiter::availableIn($throttleKey) . ' วินาที',
            ]);
        }

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $ok = Auth::attempt(
            [$field => $data['login'], 'password' => $data['password'], 'is_active' => 1],
            $request->boolean('remember')
        );

        if (! $ok) {
            RateLimiter::hit($throttleKey, 300);

            throw ValidationException::withMessages([
                'login' => 'อีเมล/เบอร์โทร หรือรหัสผ่านไม่ถูกต้อง หรือบัญชีถูกระงับ',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'ออกจากระบบเรียบร้อย');
    }

    /** เปลี่ยนรหัสผ่านตัวเอง */
    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'เปลี่ยนรหัสผ่านเรียบร้อย');
    }
}
