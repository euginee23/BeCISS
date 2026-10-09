<?php

use App\Models\Appointment;
use App\Models\Resident;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('is available to admins only', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.services'))
        ->assertSuccessful()
        ->assertSee('Mediation/Settlement');

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.settings.services'))
        ->assertForbidden();
});

it('creates a service that residents can book', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.settings.services')
        ->call('openCreateModal')
        ->set('name', 'Barangay ID Application')
        ->set('requirements', '1x1 photo')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('services', ['slug' => 'barangay_id_application', 'is_bookable' => true]);

    $residentUser = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $residentUser->id]);

    Livewire::actingAs($residentUser)
        ->test('pages::resident.appointments.create')
        ->set('service_type', 'barangay_id_application')
        ->set('description', 'Apply for a barangay ID.')
        ->set('appointment_date', now()->addDay()->toDateString())
        ->set('appointment_time', '09:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(Appointment::sole()->service_type_label)->toBe('Barangay ID Application');
});

it('hides inactive and non-bookable services from booking', function () {
    Service::factory()->inactive()->create(['slug' => 'retired_service']);
    Service::factory()->notBookable()->create(['slug' => 'walk_in_only']);

    $residentUser = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $residentUser->id]);

    foreach (['retired_service', 'walk_in_only'] as $slug) {
        Livewire::actingAs($residentUser)
            ->test('pages::resident.appointments.create')
            ->set('service_type', $slug)
            ->set('description', 'Test booking.')
            ->set('appointment_date', now()->addDay()->toDateString())
            ->set('appointment_time', '09:00')
            ->call('save')
            ->assertHasErrors(['service_type']);
    }
});

it('protects system services', function () {
    $service = Service::where('slug', Service::CERTIFICATE_VISIT)->first();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.settings.services')
        ->call('delete', $service->id)
        ->assertHasErrors(['delete'])
        ->call('openEditModal', $service->id)
        ->set('is_active', false)
        ->set('is_bookable', false)
        ->call('save');

    expect($service->fresh())
        ->trashed()->toBeFalse()
        ->is_active->toBeTrue()
        ->is_bookable->toBeTrue();
});

it('keeps labels for appointments of deleted services', function () {
    $service = Service::factory()->create(['slug' => 'old_program', 'name' => 'Old Program']);
    $appointment = Appointment::factory()->create(['service_type' => 'old_program']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.settings.services')
        ->call('delete', $service->id);

    $this->assertSoftDeleted($service);
    expect($appointment->fresh()->service_type_label)->toBe('Old Program');
});

it('lists website services and certificate types on the home page', function () {
    Service::factory()->create(['name' => 'Senior Citizen Assistance']);
    Service::factory()->create(['name' => 'Internal Only', 'show_on_website' => false]);

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('Senior Citizen Assistance')
        ->assertDontSee('Internal Only')
        ->assertSee('Barangay Clearance');
});
