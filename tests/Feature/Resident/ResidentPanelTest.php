<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->staff = User::factory()->staff()->create();
    $this->resident = User::factory()->create();
});

describe('my certificates page', function () {
    it('can be accessed by resident', function () {
        $this->actingAs($this->resident)
            ->get(route('resident.certificates.index'))
            ->assertSuccessful();
    });

    it('cannot be accessed by admin', function () {
        $this->actingAs($this->admin)
            ->get(route('resident.certificates.index'))
            ->assertForbidden();
    });

    it('cannot be accessed by staff', function () {
        $this->actingAs($this->staff)
            ->get(route('resident.certificates.index'))
            ->assertForbidden();
    });

    it('cannot be accessed by guests', function () {
        $this->get(route('resident.certificates.index'))
            ->assertRedirect(route('login'));
    });
});

describe('my appointments page', function () {
    it('can be accessed by resident', function () {
        $this->actingAs($this->resident)
            ->get(route('resident.appointments.index'))
            ->assertSuccessful();
    });

    it('cannot be accessed by admin', function () {
        $this->actingAs($this->admin)
            ->get(route('resident.appointments.index'))
            ->assertForbidden();
    });

    it('cannot be accessed by staff', function () {
        $this->actingAs($this->staff)
            ->get(route('resident.appointments.index'))
            ->assertForbidden();
    });

    it('cannot be accessed by guests', function () {
        $this->get(route('resident.appointments.index'))
            ->assertRedirect(route('login'));
    });
});
