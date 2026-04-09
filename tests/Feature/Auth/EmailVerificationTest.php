<?php

use App\Mail\VerifyEmailCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::emailVerification());
});

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk();
});

test('email can be verified with valid code', function () {
    $user = User::factory()->unverified()->create();

    Cache::put("email_verify_code_{$user->id}", '123456', now()->addMinutes(10));

    $response = $this->actingAs($user)->post(route('verification.code'), [
        'code' => '123456',
    ]);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(config('fortify.home'));
});

test('email is not verified with invalid code', function () {
    $user = User::factory()->unverified()->create();

    Cache::put("email_verify_code_{$user->id}", '123456', now()->addMinutes(10));

    $response = $this->actingAs($user)->post(route('verification.code'), [
        'code' => '999999',
    ]);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    $response->assertSessionHasErrors('code');
});

test('email is not verified with expired code', function () {
    $user = User::factory()->unverified()->create();

    // Don't put anything in cache — simulates expired

    $response = $this->actingAs($user)->post(route('verification.code'), [
        'code' => '123456',
    ]);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    $response->assertSessionHasErrors('code');
});

test('resend verification sends email with code', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('verification.send'));

    Mail::assertSent(VerifyEmailCode::class, function ($mail) use ($user) {
        return $mail->user->id === $user->id && $mail->hasTo($user->email);
    });
});

test('already verified user is redirected', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Cache::put("email_verify_code_{$user->id}", '123456', now()->addMinutes(10));

    $response = $this->actingAs($user)->post(route('verification.code'), [
        'code' => '123456',
    ]);

    $response->assertRedirect(config('fortify.home'));
});

test('verification code must be 6 digits', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->post(route('verification.code'), [
        'code' => '12345',
    ]);

    $response->assertSessionHasErrors('code');
});
