<?php

use App\Models\Appointment;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $resident = Resident::factory()->create(['user_id' => User::factory()->resident()->create()->id]);
    $this->appointment = Appointment::factory()->confirmed()->create(['resident_id' => $resident->id]);
});

/*
|--------------------------------------------------------------------------
| Mark Complete
|--------------------------------------------------------------------------
*/

test('admin can mark appointment as complete', function () {
    Mail::fake();

    $this->actingAs($this->admin);

    Livewire::test('pages::appointments.show', ['appointment' => $this->appointment])
        ->call('openCompleteModal')
        ->assertSet('showCompleteModal', true)
        ->set('completionNotes', 'Appointment completed successfully.')
        ->call('completeAppointment')
        ->assertSet('showCompleteModal', false);

    expect($this->appointment->fresh()->status)->toBe('completed');
    expect($this->appointment->fresh()->completed_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Cancel Appointment
|--------------------------------------------------------------------------
*/

test('admin can cancel an appointment', function () {
    Mail::fake();

    $this->actingAs($this->admin);

    Livewire::test('pages::appointments.show', ['appointment' => $this->appointment])
        ->call('openCancelModal')
        ->assertSet('showCancelModal', true)
        ->set('cancellationReason', 'Schedule conflict.')
        ->call('cancelAppointment')
        ->assertSet('showCancelModal', false);

    expect($this->appointment->fresh()->status)->toBe('cancelled');
    expect($this->appointment->fresh()->cancelled_at)->not->toBeNull();
    expect($this->appointment->fresh()->cancellation_reason)->toBe('Schedule conflict.');
});

/*
|--------------------------------------------------------------------------
| No Show
|--------------------------------------------------------------------------
*/

test('admin can mark appointment as no show', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::appointments.show', ['appointment' => $this->appointment])
        ->call('markNoShow');

    expect($this->appointment->fresh()->status)->toBe('no_show');
});
