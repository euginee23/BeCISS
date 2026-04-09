<?php

use App\Mail\AppointmentCancelled;
use App\Mail\AppointmentCompleted;
use App\Mail\AppointmentConfirmed;
use App\Mail\AppointmentScheduled;
use App\Models\Appointment;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('admin scheduling an appointment sends email and notification to resident', function () {
    Mail::fake();
    Notification::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $residentUser = User::factory()->create(['role' => 'resident']);
    $resident = Resident::factory()->create(['user_id' => $residentUser->id]);

    Livewire::actingAs($admin)
        ->test('pages::appointments.create')
        ->set('resident_id', $resident->id)
        ->set('service_type', array_key_first(Appointment::SERVICE_TYPES))
        ->set('description', 'Test appointment')
        ->set('appointment_date', now()->addDays(3)->toDateString())
        ->set('appointment_time', '10:00')
        ->set('duration_minutes', 30)
        ->call('save');

    Mail::assertSent(AppointmentScheduled::class, fn ($mail) => $mail->hasTo($residentUser->email));
    Notification::assertSentTo($residentUser, \App\Notifications\ResidentNotification::class);
});

test('confirming an appointment sends confirmation email', function () {
    Mail::fake();

    $user = User::factory()->create(['role' => 'admin']);
    $residentUser = User::factory()->create(['role' => 'resident']);
    $resident = Resident::factory()->create(['user_id' => $residentUser->id]);
    $appointment = Appointment::factory()->create(['resident_id' => $resident->id]);

    Livewire::actingAs($user)
        ->test('pages::appointments.show', ['appointment' => $appointment])
        ->call('confirmAppointment');

    Mail::assertSent(AppointmentConfirmed::class, function ($mail) use ($residentUser) {
        return $mail->hasTo($residentUser->email);
    });
});

test('cancelling an appointment sends cancellation email', function () {
    Mail::fake();

    $user = User::factory()->create(['role' => 'admin']);
    $residentUser = User::factory()->create(['role' => 'resident']);
    $resident = Resident::factory()->create(['user_id' => $residentUser->id]);
    $appointment = Appointment::factory()->create(['resident_id' => $resident->id]);

    Livewire::actingAs($user)
        ->test('pages::appointments.show', ['appointment' => $appointment])
        ->set('cancellationReason', 'Unable to attend')
        ->call('cancelAppointment');

    Mail::assertSent(AppointmentCancelled::class, function ($mail) use ($residentUser) {
        return $mail->hasTo($residentUser->email);
    });
});

test('completing an appointment sends completion email', function () {
    Mail::fake();

    $user = User::factory()->create(['role' => 'admin']);
    $residentUser = User::factory()->create(['role' => 'resident']);
    $resident = Resident::factory()->create(['user_id' => $residentUser->id]);
    $appointment = Appointment::factory()->confirmed()->create([
        'resident_id' => $resident->id,
        'handled_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::appointments.show', ['appointment' => $appointment])
        ->set('completionNotes', 'All done')
        ->call('completeAppointment');

    Mail::assertSent(AppointmentCompleted::class, function ($mail) use ($residentUser) {
        return $mail->hasTo($residentUser->email);
    });
});

test('no email sent when resident has no linked user', function () {
    Mail::fake();

    $user = User::factory()->create(['role' => 'admin']);
    $resident = Resident::factory()->create(['user_id' => null]);
    $appointment = Appointment::factory()->create(['resident_id' => $resident->id]);

    Livewire::actingAs($user)
        ->test('pages::appointments.show', ['appointment' => $appointment])
        ->call('confirmAppointment');

    Mail::assertNothingSent();
});
