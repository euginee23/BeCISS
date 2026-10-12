<?php

use App\Models\Appointment;
use App\Models\Certificate;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->resident = Resident::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user);
});

test('the dashboard cards link to the matching lists', function () {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('resident.certificates.index', ['status' => 'in_progress']), false)
        ->assertSee(route('resident.appointments.index', ['tab' => 'today']), false);
});

test('the in progress filter shows every certificate still being worked on', function () {
    foreach (['pending' => 'CERT-PEND', 'awaiting_payment' => 'CERT-PAY', 'processing' => 'CERT-PROC', 'completed' => 'CERT-DONE'] as $status => $number) {
        Certificate::factory()->create([
            'resident_id' => $this->resident->id,
            'status' => $status,
            'certificate_number' => $number,
        ]);
    }

    Livewire::withQueryParams(['status' => 'in_progress'])
        ->test('pages::resident.certificates.index')
        ->assertSee(['CERT-PEND', 'CERT-PAY', 'CERT-PROC'])
        ->assertDontSee('CERT-DONE');
});

test('the today tab shows only appointments scheduled for today', function () {
    Appointment::factory()->today()->create(['resident_id' => $this->resident->id, 'reference_number' => 'REF-TODAY']);
    Appointment::factory()->create([
        'resident_id' => $this->resident->id,
        'reference_number' => 'REF-LATER',
        'appointment_date' => now()->addDays(3)->toDateString(),
    ]);

    Livewire::withQueryParams(['tab' => 'today'])
        ->test('pages::resident.appointments.index')
        ->assertSee('REF-TODAY')
        ->assertDontSee('REF-LATER');
});
