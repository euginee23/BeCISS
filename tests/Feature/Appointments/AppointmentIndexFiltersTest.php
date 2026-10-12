<?php

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-14 09:00:00');

    $this->actingAs(User::factory()->admin()->create());

    foreach ([
        'REF-TODAY' => '2026-10-14',
        'REF-WEEK' => '2026-10-17',
        'REF-MONTH' => '2026-10-28',
        'REF-YEAR' => '2026-03-02',
        'REF-OLD' => '2025-12-30',
    ] as $reference => $date) {
        Appointment::factory()->create([
            'reference_number' => $reference,
            'appointment_date' => $date,
            'service_type' => 'consultation',
        ]);
    }
});

test('the period filter narrows appointments to the chosen range', function (string $period, array $visible) {
    $component = Livewire::test('pages::appointments.index')->set('period', $period);

    foreach (['REF-TODAY', 'REF-WEEK', 'REF-MONTH', 'REF-YEAR', 'REF-OLD'] as $reference) {
        in_array($reference, $visible, true)
            ? $component->assertSee($reference)
            : $component->assertDontSee($reference);
    }
})->with([
    'all dates' => ['', ['REF-TODAY', 'REF-WEEK', 'REF-MONTH', 'REF-YEAR', 'REF-OLD']],
    'today' => ['today', ['REF-TODAY']],
    'this week' => ['week', ['REF-TODAY', 'REF-WEEK']],
    'this month' => ['month', ['REF-TODAY', 'REF-WEEK', 'REF-MONTH']],
    'this year' => ['year', ['REF-TODAY', 'REF-WEEK', 'REF-MONTH', 'REF-YEAR']],
]);

test('the custom period filters between the chosen dates', function () {
    Livewire::test('pages::appointments.index')
        ->set('period', 'custom')
        ->set('from', '2025-12-01')
        ->set('to', '2026-03-31')
        ->assertSee(['REF-YEAR', 'REF-OLD'])
        ->assertDontSee(['REF-TODAY', 'REF-WEEK', 'REF-MONTH']);
});

test('the custom period ignores invalid dates', function () {
    Livewire::test('pages::appointments.index')
        ->set('period', 'custom')
        ->set('from', 'not-a-date')
        ->set('to', '2026-10-14')
        ->assertSee(['REF-TODAY', 'REF-YEAR', 'REF-OLD'])
        ->assertDontSee(['REF-WEEK', 'REF-MONTH']);
});

test('the service filter shows only appointments for that service', function () {
    $service = Service::factory()->create(['slug' => 'water_permit', 'name' => 'Water Permit']);

    Appointment::factory()->create([
        'reference_number' => 'REF-PERMIT',
        'service_type' => $service->slug,
    ]);

    Livewire::test('pages::appointments.index')
        ->assertSee('Water Permit')
        ->set('service', 'water_permit')
        ->assertSee('REF-PERMIT')
        ->assertDontSee(['REF-TODAY', 'REF-OLD']);
});

test('searching does not bypass the other filters', function () {
    Appointment::factory()->cancelled()->create([
        'reference_number' => 'REF-SEARCH-CANCELLED',
        'service_type' => 'consultation',
    ]);

    Livewire::test('pages::appointments.index')
        ->set('status', 'scheduled')
        ->set('search', 'REF-SEARCH')
        ->assertDontSee('REF-SEARCH-CANCELLED');
});
