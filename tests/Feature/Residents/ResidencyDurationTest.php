<?php

use App\Models\Resident;

test('years of residency is derived from the registration date', function () {
    $resident = Resident::factory()->create([
        'residency_start_date' => now()->subYears(7)->subMonths(2)->toDateString(),
    ]);

    expect($resident->years_of_residency)->toBe(7);
});

test('years of residency increments on its own as time passes', function () {
    $resident = Resident::factory()->create([
        'residency_start_date' => now()->subYears(3)->toDateString(),
    ]);

    expect($resident->years_of_residency)->toBe(3);

    $this->travel(1)->years();

    expect($resident->fresh()->years_of_residency)->toBe(4);
});

test('years of residency is null when no registration date is recorded', function () {
    $resident = Resident::factory()->create(['residency_start_date' => null]);

    expect($resident->years_of_residency)->toBeNull();
});

test('address is composed from house number, street and purok', function () {
    $resident = Resident::factory()->create([
        'house_number' => '12',
        'street' => 'Mabini Street',
        'purok' => 'Purok 3',
    ]);

    expect($resident->address)->toBe('12, Mabini Street, Purok 3');
});

test('address skips a missing house number', function () {
    $resident = Resident::factory()->create([
        'house_number' => null,
        'street' => 'Mabini Street',
        'purok' => 'Purok 3',
    ]);

    expect($resident->address)->toBe('Mabini Street, Purok 3');
});

test('purok number strips the purok prefix for document placeholders', function () {
    $resident = Resident::factory()->create(['purok' => 'Purok 4']);

    expect($resident->purok_number)->toBe('4');
});
