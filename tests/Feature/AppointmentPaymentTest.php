<?php

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceFee;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();

    ServiceFee::sync();
    ServiceFee::where('service_type', 'blotter')->update(['fee' => 500]);

    $this->admin = User::factory()->admin()->create();
});

test('completing a complaint/blotter appointment records the blotter fee', function () {
    $appointment = Appointment::factory()->confirmed()->create(['service_type' => Service::COMPLAINT]);

    Livewire::actingAs($this->admin)
        ->test('pages::appointments.show', ['appointment' => $appointment])
        ->assertSee('Collect on completion')
        ->call('openCompleteModal')
        ->set('orNumber', ' OR-9001 ')
        ->call('completeAppointment')
        ->assertHasNoErrors()
        ->assertSet('showCompleteModal', false);

    $appointment->refresh();

    expect($appointment->status)->toBe('completed')
        ->and($appointment->is_paid)->toBeTrue()
        ->and($appointment->or_number)->toBe('OR-9001')
        ->and((float) $appointment->fee)->toBe(500.0);

    $payment = Payment::sole();

    expect((float) $payment->amount)->toBe(500.0)
        ->and($payment->payable->is($appointment))->toBeTrue()
        ->and($payment->service_label)->toBe('Complaint/Blotter')
        ->and($payment->reference_number)->toBe($appointment->reference_number);

    expect(ActivityLog::where('module', 'appointments')->where('action', 'paid')->exists())->toBeTrue();
});

test('an OR number is required to complete an appointment with a fee', function () {
    $appointment = Appointment::factory()->confirmed()->create(['service_type' => Service::COMPLAINT]);

    Livewire::actingAs($this->admin)
        ->test('pages::appointments.show', ['appointment' => $appointment])
        ->call('openCompleteModal')
        ->call('completeAppointment')
        ->assertHasErrors(['orNumber' => 'required']);

    expect($appointment->refresh()->status)->toBe('confirmed')
        ->and(Payment::count())->toBe(0);
});

test('a used OR number is rejected and the appointment stays open', function () {
    Payment::factory()->create(['or_number' => 'OR-9002']);
    $appointment = Appointment::factory()->confirmed()->create(['service_type' => Service::COMPLAINT]);

    Livewire::actingAs($this->admin)
        ->test('pages::appointments.show', ['appointment' => $appointment])
        ->set('orNumber', 'OR-9002')
        ->call('completeAppointment')
        ->assertHasErrors(['orNumber']);

    expect($appointment->refresh()->status)->toBe('confirmed')
        ->and($appointment->is_paid)->toBeFalse();
});

test('other services charge their own service fee', function () {
    Service::where('slug', 'mediation')->update(['fee' => 150]);
    $appointment = Appointment::factory()->confirmed()->create(['service_type' => 'mediation']);

    Livewire::actingAs($this->admin)
        ->test('pages::appointments.show', ['appointment' => $appointment])
        ->set('orNumber', 'OR-9003')
        ->call('completeAppointment')
        ->assertHasNoErrors();

    expect((float) Payment::sole()->amount)->toBe(150.0);
});

test('free services complete without an OR number or payment', function () {
    $appointment = Appointment::factory()->confirmed()->create(['service_type' => 'consultation']);

    Livewire::actingAs($this->admin)
        ->test('pages::appointments.show', ['appointment' => $appointment])
        ->call('completeAppointment')
        ->assertHasNoErrors();

    expect($appointment->refresh()->status)->toBe('completed')
        ->and($appointment->is_paid)->toBeFalse()
        ->and(Payment::count())->toBe(0);
});

test('certificate visits are not charged on top of the certificate fee', function () {
    Service::where('slug', Service::CERTIFICATE_VISIT)->update(['fee' => 100]);
    $appointment = Appointment::factory()->confirmed()->create(['service_type' => Service::CERTIFICATE_VISIT]);

    expect($appointment->serviceFee())->toBe(0.0);
});

test('appointment fees show in the reports collections', function () {
    $appointment = Appointment::factory()->confirmed()->create(['service_type' => Service::COMPLAINT]);

    Livewire::actingAs($this->admin)
        ->test('pages::appointments.show', ['appointment' => $appointment])
        ->set('orNumber', 'OR-9004')
        ->call('completeAppointment');

    $revenue = Livewire::actingAs($this->admin)
        ->test('pages::reports.index')
        ->instance()
        ->revenue;

    expect($revenue['appointments'])->toBe(500.0)
        ->and($revenue['receipts'])->toBe(1);

    Livewire::actingAs($this->admin)
        ->test('pages::reports.collections')
        ->set('source', 'appointment')
        ->assertSee('OR-9004')
        ->assertSee($appointment->reference_number);
});
