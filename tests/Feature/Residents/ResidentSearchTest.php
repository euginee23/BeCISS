<?php

use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());

    $address = ['house_number' => null, 'street' => null, 'purok' => 'Purok 1', 'suffix' => null];

    $this->alma = Resident::factory()->create([...$address, 'first_name' => 'Alma', 'middle_name' => 'C.', 'last_name' => 'Abalde']);
    $this->other = Resident::factory()->create([...$address, 'first_name' => 'Alma', 'middle_name' => 'R.', 'last_name' => 'Tutor']);
});

test('the search scope matches every word across the name fields', function (string $term) {
    expect(Resident::query()->search($term)->pluck('id')->all())->toBe([$this->alma->id]);
})->with([
    'first name and middle initial' => 'alma c',
    'with the period' => 'Alma C.',
    'last name first' => 'abalde alma',
    'comma separated' => 'Abalde, Alma',
    'full name' => 'Alma C. Abalde',
]);

test('the residents list finds a resident by first name and middle initial', function () {
    Livewire::test('pages::residents.index')
        ->set('search', 'alma c')
        ->assertSee('Abalde')
        ->assertDontSee('Tutor');
});

test('the resident picker lists matches and binds the chosen resident', function () {
    Livewire::test('resident-picker')
        ->set('search', 'alma c')
        ->assertSee('Alma C. Abalde')
        ->assertDontSee('Tutor')
        ->call('choose', $this->alma->id)
        ->assertSet('value', $this->alma->id)
        ->assertSet('search', '')
        ->call('clear')
        ->assertSet('value', null);
});

test('the resident picker waits for at least two characters', function () {
    Livewire::test('resident-picker')
        ->set('search', 'a')
        ->assertDontSee('Abalde')
        ->assertDontSee('No residents found');
});

test('the request forms use the resident picker', function (string $route) {
    $this->get(route($route))->assertOk()->assertSeeLivewire('resident-picker');
})->with(['certificates.create', 'appointments.create', 'blotters.create']);
