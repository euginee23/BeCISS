<?php

use App\Models\Appointment;
use App\Models\Resident;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

test('staff can type the service when booking under other', function () {
    $resident = Resident::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::appointments.create')
        ->set('resident_id', $resident->id)
        ->set('service_type', Service::OTHER)
        ->set('other_service', '  Water connection request  ')
        ->set('description', 'Resident needs help with a water connection.')
        ->set('appointment_date', now()->addDay()->toDateString())
        ->set('appointment_time', '09:00')
        ->call('save')
        ->assertHasNoErrors();

    $appointment = Appointment::sole();

    expect($appointment->service_type)->toBe(Service::OTHER)
        ->and($appointment->other_service)->toBe('Water connection request')
        ->and($appointment->service_type_label)->toBe('Water connection request');
});

test('the typed service is required when booking under other', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::appointments.create')
        ->set('resident_id', Resident::factory()->create()->id)
        ->set('service_type', Service::OTHER)
        ->set('description', 'Something else entirely.')
        ->set('appointment_date', now()->addDay()->toDateString())
        ->set('appointment_time', '09:00')
        ->call('save')
        ->assertHasErrors(['other_service' => 'required_if']);
});

test('the typed service is discarded for any other service', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::appointments.create')
        ->set('resident_id', Resident::factory()->create()->id)
        ->set('service_type', 'consultation')
        ->set('other_service', 'Leftover text')
        ->set('description', 'A regular consultation.')
        ->set('appointment_date', now()->addDay()->toDateString())
        ->set('appointment_time', '09:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(Appointment::sole()->other_service)->toBeNull();
});

test('editing an other appointment keeps and updates the typed service', function () {
    $appointment = Appointment::factory()->create([
        'service_type' => Service::OTHER,
        'other_service' => 'Tree cutting permit',
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::appointments.edit', ['appointment' => $appointment])
        ->assertSet('other_service', 'Tree cutting permit')
        ->set('other_service', 'Tree trimming permit')
        ->call('save')
        ->assertHasNoErrors();

    expect($appointment->refresh()->service_type_label)->toBe('Tree trimming permit');
});

test('a resident can type the service when booking under other', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::resident.appointments.create')
        ->set('service_type', Service::OTHER)
        ->set('other_service', 'Street light repair')
        ->set('description', 'The street light near our house is broken.')
        ->set('appointment_date', now()->addDay()->toDateString())
        ->set('appointment_time', '10:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(Appointment::sole()->service_type_label)->toBe('Street light repair');
});

test('an other appointment without a typed service still reads as other', function () {
    $appointment = Appointment::factory()->create(['service_type' => Service::OTHER, 'other_service' => null]);

    expect($appointment->service_type_label)->toBe('Other');
});

test('appointments can be searched by the typed service', function () {
    Appointment::factory()->create(['service_type' => Service::OTHER, 'other_service' => 'Fence dispute survey']);
    Appointment::factory()->create(['service_type' => 'consultation', 'description' => 'Unrelated visit']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::appointments.index')
        ->set('search', 'Fence dispute')
        ->assertSee('Fence dispute survey')
        ->assertDontSee('Unrelated visit');
});
