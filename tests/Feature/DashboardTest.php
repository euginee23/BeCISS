<?php

use App\Models\Resident;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the admin dashboard no longer shows quick access actions', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('New Resident')
        ->assertDontSee('New Appointment');
});

test('the resident dashboard no longer shows quick access actions', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->for($user)->create();

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('View &amp; request certificates', false)
        ->assertDontSee('Schedule &amp; manage appointments', false);
});
