<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ForgotPasswordCodeController extends Controller
{
    public function showRequestForm(): View
    {
        return view('pages::auth.forgot-password');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withInput()->withErrors(['email' => 'No account found with that email address.']);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("password_reset_code_{$request->email}", $code, now()->addMinutes(10));
        Mail::to($request->email)->send(new PasswordResetCode($request->email, $code));

        return redirect()->route('password.verify-code', ['email' => $request->email])
            ->with('status', 'A reset code has been sent to your email address.');
    }

    public function showVerifyForm(Request $request): View
    {
        return view('pages::auth.forgot-password-verify', [
            'email' => $request->query('email', ''),
        ]);
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $cached = Cache::get("password_reset_code_{$request->email}");

        if (! $cached || $cached !== $request->code) {
            return back()->withInput()->withErrors(['code' => 'The code is invalid or has expired.']);
        }

        Cache::forget("password_reset_code_{$request->email}");

        session()->put('password_reset_verified_email', $request->email);

        return redirect()->route('password.reset-form');
    }

    public function showResetForm(): View|RedirectResponse
    {
        if (! session()->has('password_reset_verified_email')) {
            return redirect()->route('password.request');
        }

        return view('pages::auth.forgot-password-reset');
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        if (! session()->has('password_reset_verified_email')) {
            return redirect()->route('password.request');
        }

        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = session()->pull('password_reset_verified_email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'Unable to find user.']);
        }

        $user->forceFill([
            'password' => $request->password,
        ])->save();

        return redirect()->route('home')
            ->with('status', 'Your password has been reset successfully.');
    }
}
