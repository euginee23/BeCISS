<?php

use App\Models\Appointment;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\ResidentNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function bookableResident(): User
{
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id]);

    return $user;
}

test('a resident cannot book more than 7 days ahead', function () {
    Livewire::actingAs(bookableResident())
        ->test('pages::resident.appointments.create')
        ->set('service_type', 'consultation')
        ->set('description', 'Need to consult about a barangay matter.')
        ->set('appointment_date', now()->addDays(8)->toDateString())
        ->set('appointment_time', '09:00')
        ->call('save')
        ->assertHasErrors(['appointment_date']);
});

test('a resident can book exactly 7 days ahead', function () {
    Livewire::actingAs(bookableResident())
        ->test('pages::resident.appointments.create')
        ->set('service_type', 'consultation')
        ->set('description', 'Need to consult about a barangay matter.')
        ->set('appointment_date', now()->addDays(7)->toDateString())
        ->set('appointment_time', '09:00')
        ->call('save')
        ->assertHasNoErrors();
});

test('staff cannot book more than 7 days ahead either', function () {
    $resident = Resident::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::appointments.create')
        ->set('resident_id', $resident->id)
        ->set('service_type', 'consultation')
        ->set('description', 'Follow-up consultation for the resident.')
        ->set('appointment_date', now()->addDays(9)->toDateString())
        ->set('appointment_time', '10:00')
        ->call('save')
        ->assertHasErrors(['appointment_date']);
});

test('an appointment already booked beyond the window stays editable', function () {
    $appointment = Appointment::factory()->create();

    // Force it past the window, bypassing the form.
    $appointment->forceFill(['appointment_date' => now()->addDays(30)->toDateString()])->save();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::appointments.edit', ['appointment' => $appointment->fresh()])
        ->set('description', 'Updated description for this appointment.')
        ->call('save')
        ->assertHasNoErrors();
});

test('building permit is no longer a bookable service type', function () {
    expect(Appointment::SERVICE_TYPES)->not->toHaveKey('building_permit')
        ->and(Appointment::SERVICE_TYPES)->toHaveKey('business_permit');

    Livewire::actingAs(bookableResident())
        ->test('pages::resident.appointments.create')
        ->set('service_type', 'building_permit')
        ->set('description', 'Requesting a building permit appointment.')
        ->set('appointment_date', now()->addDay()->toDateString())
        ->set('appointment_time', '09:00')
        ->call('save')
        ->assertHasErrors(['service_type']);
});

test('staff are notified when a resident books an appointment', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();
    $otherStaff = User::factory()->staff(['residents'])->create();

    Livewire::actingAs(bookableResident())
        ->test('pages::resident.appointments.create')
        ->set('service_type', 'consultation')
        ->set('description', 'Need to consult about a barangay matter.')
        ->set('appointment_date', now()->addDay()->toDateString())
        ->set('appointment_time', '09:00')
        ->call('save')
        ->assertHasNoErrors();

    foreach ([$admin, $staff] as $recipient) {
        Notification::assertSentTo($recipient, ResidentNotification::class, function (ResidentNotification $n) {
            return $n->type === 'appointment_requested';
        });
    }

    // Staff without the appointments permission are left out.
    Notification::assertNotSentTo($otherStaff, ResidentNotification::class);
});

test('the appointment queue lists the earliest booking first', function () {
    $admin = User::factory()->admin()->create();

    $second = Appointment::factory()->create(['created_at' => now()->subMinutes(5)]);
    $first = Appointment::factory()->create(['created_at' => now()->subHour()]);

    Livewire::actingAs($admin)
        ->test('pages::appointments.index')
        ->assertSeeInOrder([$first->reference_number, $second->reference_number]);
});
