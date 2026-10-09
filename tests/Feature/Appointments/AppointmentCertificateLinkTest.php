<?php

use App\Models\Appointment;
use App\Models\Certificate;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();

    $this->residentUser = User::factory()->resident()->create();
    $this->resident = Resident::factory()->create(['user_id' => $this->residentUser->id]);
    $this->admin = User::factory()->admin()->create();
});

function bookVisitFor(Certificate $certificate, User $as)
{
    return Livewire::withQueryParams(['certificate' => $certificate->id])
        ->actingAs($as)
        ->test('pages::resident.appointments.create')
        ->set('appointment_date', now()->addDay()->toDateString())
        ->set('appointment_time', '09:00');
}

it('books a visit linked to a certificate awaiting payment', function () {
    $certificate = Certificate::factory()->awaitingPayment()->create(['resident_id' => $this->resident->id]);

    bookVisitFor($certificate, $this->residentUser)
        ->assertSet('service_type', 'certificate_request')
        ->assertSee($certificate->certificate_number)
        ->call('save')
        ->assertHasNoErrors();

    $appointment = Appointment::sole();

    expect($appointment->certificate_id)->toBe($certificate->id)
        ->and($appointment->service_type)->toBe('certificate_request')
        ->and($certificate->fresh()->activeAppointment()->id)->toBe($appointment->id);
});

it('ignores certificates that belong to someone else', function () {
    $certificate = Certificate::factory()->awaitingPayment()->create();

    Livewire::withQueryParams(['certificate' => $certificate->id])
        ->actingAs($this->residentUser)
        ->test('pages::resident.appointments.create')
        ->assertSet('certificate_id', null);
});

it('does not book a second visit for the same certificate', function () {
    $certificate = Certificate::factory()->awaitingPayment()->create(['resident_id' => $this->resident->id]);
    Appointment::factory()->create([
        'resident_id' => $this->resident->id,
        'certificate_id' => $certificate->id,
        'service_type' => 'certificate_request',
    ]);

    Livewire::withQueryParams(['certificate' => $certificate->id])
        ->actingAs($this->residentUser)
        ->test('pages::resident.appointments.create')
        ->assertSet('certificate_id', null);
});

it('lets staff take payment from the appointment page', function () {
    $certificate = Certificate::factory()->awaitingPayment()->create(['resident_id' => $this->resident->id]);
    $appointment = Appointment::factory()->confirmed()->create([
        'resident_id' => $this->resident->id,
        'certificate_id' => $certificate->id,
        'service_type' => 'certificate_request',
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::appointments.show', ['appointment' => $appointment])
        ->assertSee($certificate->certificate_number)
        ->call('openPaymentModal')
        ->set('orNumber', 'OR-555')
        ->call('recordCertificatePayment')
        ->assertHasNoErrors();

    expect($certificate->fresh())
        ->status->toBe('processing')
        ->or_number->toBe('OR-555');
});

it('completes the visit when the certificate is released from it', function () {
    $certificate = Certificate::factory()->readyForPickup()->create(['resident_id' => $this->resident->id]);
    $appointment = Appointment::factory()->confirmed()->create([
        'resident_id' => $this->resident->id,
        'certificate_id' => $certificate->id,
        'service_type' => 'certificate_request',
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::appointments.show', ['appointment' => $appointment])
        ->call('releaseCertificate');

    expect($certificate->fresh()->status)->toBe('completed')
        ->and($appointment->fresh()->status)->toBe('completed');
});

it('notifies the resident when staff cancel from the list', function () {
    $appointment = Appointment::factory()->create(['resident_id' => $this->resident->id]);

    Livewire::actingAs($this->admin)
        ->test('pages::appointments.index')
        ->call('confirmCancel', $appointment->id)
        ->set('cancellationReason', 'Office closed')
        ->call('cancelAppointment');

    expect($appointment->fresh()->status)->toBe('cancelled')
        ->and($this->residentUser->notifications()->where('data->type', 'appointment_cancelled')->exists())->toBeTrue();
});
