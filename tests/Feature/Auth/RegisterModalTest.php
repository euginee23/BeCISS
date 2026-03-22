<?php

use App\Models\User;
use Livewire\Livewire;

test('register modal component renders', function () {
    Livewire::test('auth.register-modal')
        ->assertOk();
});

test('valid registration creates resident user and redirects to dashboard', function () {
    Livewire::test('auth.register-modal')
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user = User::where('email', 'jane@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->role)->toBe('resident');
    expect(auth()->check())->toBeTrue();
});

test('password confirmation mismatch shows error', function () {
    Livewire::test('auth.register-modal')
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'DifferentPassword!')
        ->call('register')
        ->assertHasErrors(['password']);
});

test('duplicate email shows error', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    Livewire::test('auth.register-modal')
        ->set('name', 'Another User')
        ->set('email', 'existing@example.com')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('register')
        ->assertHasErrors(['email']);
});
