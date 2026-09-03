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
        ->set('house_number', '123')
        ->set('street', 'Main Street')
        ->set('purok', 'Purok 3')
        ->set('residency_start_date', now()->subYears(10)->toDateString())
        ->set('birthdate', '1990-05-15')
        ->set('gender', 'male')
        ->set('civil_status', 'single')
        ->set('contact_number', '+63 912 345 6789')
        ->set('occupation', 'Engineer')
        ->set('monthly_income', 25000)
        ->set('is_voter', true)
        ->call('updateResidentProfile');

    $response->assertHasNoErrors();

    $resident->refresh();

    expect($resident->house_number)->toBe('123');
    expect($resident->street)->toBe('Main Street');
    expect($resident->purok)->toBe('Purok 3');
    expect($resident->address)->toBe('123, Main Street, Purok 3');
    expect($resident->gender)->toBe('male');
    expect($resident->civil_status)->toBe('single');
    expect($resident->occupation)->toBe('Engineer');
    expect((float) $resident->monthly_income)->toBe(25000.00);
    expect($resident->years_of_residency)->toBe(10);
    expect($resident->is_voter)->toBeTrue();
});

test('resident profile requires street, purok, residency date, birthdate, gender, and civil status', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('street', '')
        ->set('purok', '')
        ->set('residency_start_date', '')
        ->set('birthdate', '')
        ->set('gender', '')
        ->set('civil_status', '')
        ->call('updateResidentProfile');

    $response->assertHasErrors(['street', 'purok', 'residency_start_date', 'birthdate', 'gender', 'civil_status']);
});

test('resident profile rejects a purok outside the configured list', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('purok', 'Purok 99')
        ->call('updateResidentProfile')
        ->assertHasErrors(['purok']);
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
