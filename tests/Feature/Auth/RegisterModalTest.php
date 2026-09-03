<?php

use App\Models\User;
use Livewire\Livewire;

test('register modal component renders', function () {
    Livewire::test('auth.register-modal')
        ->assertOk();
});

test('valid registration creates resident user and redirects to dashboard', function () {
    Livewire::test('auth.register-modal')
        ->set('first_name', 'Jane')
        ->set('middle_name', 'Ann')
        ->set('last_name', 'Doe')
        ->set('email', 'jane@example.com')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user = User::where('email', 'jane@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->role)->toBe('resident');
    expect($user->first_name)->toBe('Jane');
    expect($user->last_name)->toBe('Doe');
    expect($user->name)->toBe('Jane Ann Doe');
    expect(auth()->check())->toBeTrue();
});

test('registration capitalizes the name parts', function () {
    Livewire::test('auth.register-modal')
        ->set('first_name', 'jane')
        ->set('last_name', 'dela cruz')
        ->set('email', 'jane2@example.com')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::where('email', 'jane2@example.com')->first();
    expect($user->first_name)->toBe('Jane');
    expect($user->last_name)->toBe('Dela Cruz');
    expect($user->name)->toBe('Jane Dela Cruz');
});

test('first and last name are required', function () {
    Livewire::test('auth.register-modal')
        ->set('first_name', '')
        ->set('last_name', '')
        ->set('email', 'jane@example.com')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('register')
        ->assertHasErrors(['first_name', 'last_name']);
});

test('password confirmation mismatch shows error', function () {
    Livewire::test('auth.register-modal')
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->set('email', 'jane@example.com')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'DifferentPassword!')
        ->call('register')
        ->assertHasErrors(['password']);
});

test('duplicate email shows error', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    Livewire::test('auth.register-modal')
        ->set('first_name', 'Another')
        ->set('last_name', 'User')
        ->set('email', 'existing@example.com')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('register')
        ->assertHasErrors(['email']);
});
