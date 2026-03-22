<?php

use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('login modal component renders', function () {
    Livewire::test('auth.login-modal')
        ->assertOk();
});

test('valid credentials authenticate and redirect to dashboard', function () {
    Livewire::test('auth.login-modal')
        ->set('email', $this->user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(auth()->check())->toBeTrue();
});

test('invalid password shows error', function () {
    Livewire::test('auth.login-modal')
        ->set('email', $this->user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);

    expect(auth()->check())->toBeFalse();
});

test('missing email shows validation error', function () {
    Livewire::test('auth.login-modal')
        ->set('email', '')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email']);
});

test('user with two factor is redirected to two factor challenge', function () {
    $user = User::factory()->withTwoFactor()->create();

    Livewire::test('auth.login-modal')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('two-factor.login'));

    expect(auth()->check())->toBeFalse();
    expect(session()->get('login.id'))->toBe($user->id);
});
