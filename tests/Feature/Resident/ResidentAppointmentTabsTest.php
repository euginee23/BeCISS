<?php

use App\Models\Appointment;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->resident()->create();
    $this->resident = Resident::factory()->create(['user_id' => $this->user->id]);
});

function residentAppointment(Resident $resident, string $reference, string $date, string $status = 'scheduled'): Appointment
{
    $appointment = Appointment::factory()->create([
        'resident_id' => $resident->id,
        'reference_number' => $reference,
        'status' => $status,
    ]);

    // Bypass the booking window so past dates can be set up.
    $appointment->forceFill(['appointment_date' => $date])->save();

    return $appointment;
}

test('a scheduled appointment whose date has passed shows under past', function () {
    residentAppointment($this->resident, 'APT-PAST-SCHEDULED', now()->subDays(3)->toDateString());
    residentAppointment($this->resident, 'APT-PAST-CONFIRMED', now()->subDay()->toDateString(), 'confirmed');

    Livewire::actingAs($this->user)
        ->test('pages::resident.appointments.index')
        ->set('tab', 'past')
        ->assertSee('APT-PAST-SCHEDULED')
        ->assertSee('APT-PAST-CONFIRMED')
        ->set('tab', 'upcoming')
        ->assertDontSee('APT-PAST-SCHEDULED')
        ->assertDontSee('APT-PAST-CONFIRMED');
});

test('every appointment appears under at least one tab', function () {
    $references = [
        residentAppointment($this->resident, 'APT-T-1', now()->subDays(5)->toDateString())->reference_number,
        residentAppointment($this->resident, 'APT-T-2', now()->toDateString())->reference_number,
        residentAppointment($this->resident, 'APT-T-3', now()->addDays(2)->toDateString(), 'confirmed')->reference_number,
        residentAppointment($this->resident, 'APT-T-4', now()->addDays(2)->toDateString(), 'completed')->reference_number,
        residentAppointment($this->resident, 'APT-T-5', now()->subDays(2)->toDateString(), 'cancelled')->reference_number,
    ];

    $component = Livewire::actingAs($this->user)->test('pages::resident.appointments.index');

    $seen = collect(['today', 'upcoming', 'past'])
        ->flatMap(fn (string $tab) => $component->set('tab', $tab)->get('appointments')->pluck('reference_number'))
        ->unique();

    expect($seen->sort()->values()->all())->toBe($references);
});

test('upcoming still lists open appointments from today on', function () {
    residentAppointment($this->resident, 'APT-UP-TODAY', now()->toDateString());
    residentAppointment($this->resident, 'APT-UP-LATER', now()->addDays(3)->toDateString());

    Livewire::actingAs($this->user)
        ->test('pages::resident.appointments.index')
        ->assertSet('tab', 'upcoming')
        ->assertSee('APT-UP-TODAY')
        ->assertSee('APT-UP-LATER');
});

test('a resident cannot cancel an appointment whose date has passed', function () {
    $appointment = residentAppointment($this->resident, 'APT-OLD', now()->subDays(2)->toDateString());

    Livewire::actingAs($this->user)
        ->test('pages::resident.appointments.index')
        ->set('tab', 'past')
        ->assertDontSeeHtml('wire:click="confirmCancel('.$appointment->id.')"')
        ->call('confirmCancel', $appointment->id)
        ->set('cancellationReason', 'Trying to cancel an old visit.')
        ->call('cancelAppointment');

    expect($appointment->refresh()->status)->toBe('scheduled');
});

test('a resident can still cancel an upcoming appointment', function () {
    $appointment = residentAppointment($this->resident, 'APT-NEXT', now()->addDay()->toDateString());

    Livewire::actingAs($this->user)
        ->test('pages::resident.appointments.index')
        ->call('confirmCancel', $appointment->id)
        ->set('cancellationReason', 'I can no longer make it that day.')
        ->call('cancelAppointment')
        ->assertHasNoErrors();

    expect($appointment->refresh()->status)->toBe('cancelled');
});
