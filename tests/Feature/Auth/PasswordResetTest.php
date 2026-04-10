<?php

use App\Mail\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('forgot password screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset code can be requested', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Mail::assertSent(PasswordResetCode::class, function ($mail) use ($user) {
        return $mail->email === $user->email && $mail->hasTo($user->email);
    });

    expect(Cache::has("password_reset_code_{$user->email}"))->toBeTrue();
});

test('code verification screen can be rendered', function () {
    $response = $this->get(route('password.verify-code', ['email' => 'test@example.com']));

    $response->assertOk();
});

test('password can be reset with valid code', function () {
    Mail::fake();

    $user = User::factory()->create();

    // Step 1: Request code
    $this->post(route('password.email'), ['email' => $user->email]);

    $code = Cache::get("password_reset_code_{$user->email}");
    expect($code)->not->toBeNull();

    // Step 2: Verify code
    $response = $this->post(route('password.verify-code.store'), [
        'email' => $user->email,
        'code' => $code,
    ]);

    $response->assertRedirect(route('password.reset-form'));

    // Step 3: Reset password
    $response = $this->post(route('password.reset-update'), [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));
});

test('invalid code shows error', function () {
    $user = User::factory()->create();

    Cache::put("password_reset_code_{$user->email}", '123456', now()->addMinutes(10));

    $response = $this->post(route('password.verify-code.store'), [
        'email' => $user->email,
        'code' => '999999',
    ]);

    $response->assertSessionHasErrors('code');
});

test('expired code shows error', function () {
    $user = User::factory()->create();

    // Don't put anything in cache — simulates expired

    $response = $this->post(route('password.verify-code.store'), [
        'email' => $user->email,
        'code' => '123456',
    ]);

    $response->assertSessionHasErrors('code');
});

test('reset form requires verified code session', function () {
    $response = $this->get(route('password.reset-form'));

    $response->assertRedirect(route('password.request'));
});

test('reset password requires verified code session', function () {
    $response = $this->post(route('password.reset-update'), [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect(route('password.request'));
});

test('non-existent email shows error', function () {
    $response = $this->post(route('password.email'), ['email' => 'notfound@example.com']);

    $response->assertSessionHasErrors(['email']);
});
