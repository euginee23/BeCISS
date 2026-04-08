<?php

use App\Models\Appointment;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->staff = User::factory()->staff()->create();
    $this->residentUser = User::factory()->resident()->create();
    $this->residentRecord = Resident::factory()->create(['user_id' => $this->residentUser->id]);
});

describe('page access', function () {
    it('can be accessed by resident', function () {
        $this->actingAs($this->residentUser)
            ->get(route('resident.appointments.create'))
            ->assertSuccessful();
    });

    it('cannot be accessed by admin', function () {
        $this->actingAs($this->admin)
            ->get(route('resident.appointments.create'))
            ->assertForbidden();
    });

    it('cannot be accessed by staff', function () {
        $this->actingAs($this->staff)
            ->get(route('resident.appointments.create'))
            ->assertForbidden();
    });

    it('cannot be accessed by guests', function () {
        $this->get(route('resident.appointments.create'))
            ->assertRedirect(route('login'));
    });
});

describe('appointment booking', function () {
    it('can book an appointment', function () {
        $resident = $this->residentRecord;

        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.appointments.create')
            ->set('service_type', 'certificate_request')
            ->set('description', 'Need to pick up my clearance')
            ->set('appointment_date', now()->addDays(3)->toDateString())
            ->set('appointment_time', '09:00')
            ->set('duration_minutes', 30)
            ->call('save')
            ->assertRedirect(route('resident.appointments.index'));

        $this->assertDatabaseHas('appointments', [
            'resident_id' => $resident->id,
            'service_type' => 'certificate_request',
            'description' => 'Need to pick up my clearance',
            'status' => 'scheduled',
            'duration_minutes' => 30,
        ]);
    });

    it('generates a reference number', function () {
        $resident = $this->residentRecord;

        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.appointments.create')
            ->set('service_type', 'consultation')
            ->set('description', 'General inquiry')
            ->set('appointment_date', now()->addDays(1)->toDateString())
            ->set('appointment_time', '10:00')
            ->set('duration_minutes', 15)
            ->call('save');

        $appointment = Appointment::where('resident_id', $resident->id)->first();
        expect($appointment->reference_number)->not->toBeEmpty();
    });

    it('saves optional notes', function () {
        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.appointments.create')
            ->set('service_type', 'complaint')
            ->set('description', 'Noise complaint')
            ->set('appointment_date', now()->addDays(2)->toDateString())
            ->set('appointment_time', '14:00')
            ->set('duration_minutes', 45)
            ->set('notes', 'Please prepare mediation room')
            ->call('save');

        $this->assertDatabaseHas('appointments', [
            'notes' => 'Please prepare mediation room',
        ]);
    });
});

describe('validation', function () {
    it('requires all mandatory fields', function () {
        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.appointments.create')
            ->set('service_type', '')
            ->set('description', '')
            ->set('appointment_date', '')
            ->set('appointment_time', '')
            ->call('save')
            ->assertHasErrors(['service_type', 'description', 'appointment_date', 'appointment_time']);
    });

    it('rejects past appointment dates', function () {
        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.appointments.create')
            ->set('service_type', 'consultation')
            ->set('description', 'Test')
            ->set('appointment_date', now()->subDay()->toDateString())
            ->set('appointment_time', '09:00')
            ->set('duration_minutes', 30)
            ->call('save')
            ->assertHasErrors(['appointment_date']);
    });

    it('rejects invalid service types', function () {
        Livewire::actingAs($this->residentUser)
            ->test('pages::resident.appointments.create')
            ->set('service_type', 'nonexistent_type')
            ->set('description', 'Test')
            ->set('appointment_date', now()->addDays(1)->toDateString())
            ->set('appointment_time', '09:00')
            ->call('save')
            ->assertHasErrors(['service_type']);
    });
});
