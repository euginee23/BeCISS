<?php

use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

test('resident profile section is visible for resident users with profile', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Resident profile');
});

test('resident profile section is hidden for admin users', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('Resident profile');
});

test('resident profile section is hidden for resident without profile record', function () {
    $user = User::factory()->resident()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('Resident profile');
});

test('resident can update their address and personal info', function () {
    $user = User::factory()->resident()->create();
    $resident = Resident::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('address', '123 Main Street')
        ->set('purok', 'Purok 3')
        ->set('birthdate', '1990-05-15')
        ->set('gender', 'male')
        ->set('civil_status', 'single')
        ->set('contact_number', '+63 912 345 6789')
        ->set('occupation', 'Engineer')
        ->set('monthly_income', 25000)
        ->set('years_of_residency', 10)
        ->set('is_voter', true)
        ->call('updateResidentProfile');

    $response->assertHasNoErrors();

    $resident->refresh();

    expect($resident->address)->toBe('123 Main Street');
    expect($resident->purok)->toBe('Purok 3');
    expect($resident->gender)->toBe('male');
    expect($resident->civil_status)->toBe('single');
    expect($resident->occupation)->toBe('Engineer');
    expect((float) $resident->monthly_income)->toBe(25000.00);
    expect($resident->years_of_residency)->toBe(10);
    expect($resident->is_voter)->toBeTrue();
});

test('resident profile requires address, birthdate, gender, and civil status', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('address', '')
        ->set('birthdate', '')
        ->set('gender', '')
        ->set('civil_status', '')
        ->call('updateResidentProfile');

    $response->assertHasErrors(['address', 'birthdate', 'gender', 'civil_status']);
});

test('resident profile validates gender values', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('gender', 'invalid')
        ->call('updateResidentProfile');

    $response->assertHasErrors(['gender']);
});

test('resident profile validates civil status values', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('civil_status', 'invalid')
        ->call('updateResidentProfile');

    $response->assertHasErrors(['civil_status']);
});
