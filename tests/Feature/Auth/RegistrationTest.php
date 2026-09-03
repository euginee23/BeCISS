<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('registration screen redirects to home', function () {
    $response = $this->get(route('register'));

    $response->assertRedirect(route('home'));
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'John',
        'middle_name' => 'Reyes',
        'last_name' => 'Doe',
        'suffix' => 'Jr.',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'first_name' => 'John',
        'middle_name' => 'Reyes',
        'last_name' => 'Doe',
        'suffix' => 'Jr.',
        'name' => 'John Reyes Doe Jr.',
    ]);
});
