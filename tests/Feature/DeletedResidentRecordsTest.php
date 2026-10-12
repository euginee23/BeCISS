<?php

use App\Models\Appointment;
use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->resident = Resident::factory()->create(['first_name' => 'Deleted', 'last_name' => 'Person']);
});

test('appointments of a deleted resident still list with the resident name', function () {
    Appointment::factory()->create(['resident_id' => $this->resident->id, 'status' => 'scheduled']);
    $this->resident->delete();

    Livewire::test('pages::appointments.index')
        ->set('status', 'scheduled')
        ->assertOk()
        ->assertSee('Deleted Person');
});

test('blotters of a deleted resident still list with the resident name', function () {
    Blotter::factory()->create(['resident_id' => $this->resident->id]);
    $this->resident->delete();

    Livewire::test('pages::blotters.index')
        ->assertOk()
        ->assertSee('Deleted Person');
});

test('certificates of a deleted resident still list with the resident name', function () {
    Certificate::factory()->create(['resident_id' => $this->resident->id]);
    $this->resident->delete();

    Livewire::test('pages::certificates.index')
        ->assertOk()
        ->assertSee('Deleted Person');
});
