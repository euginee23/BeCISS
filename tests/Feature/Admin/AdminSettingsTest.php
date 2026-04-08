<?php

use App\Models\BarangayProfile;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->staff = User::factory()->staff()->create();
    $this->resident = User::factory()->create();
    Resident::factory()->create(['user_id' => $this->resident->id]);
});

describe('barangay settings page', function () {
    it('can be accessed by admin', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.barangay'))
            ->assertSuccessful();
    });

    it('cannot be accessed by staff', function () {
        $this->actingAs($this->staff)
            ->get(route('admin.settings.barangay'))
            ->assertForbidden();
    });

    it('cannot be accessed by resident', function () {
        $this->actingAs($this->resident)
            ->get(route('admin.settings.barangay'))
            ->assertForbidden();
    });

    it('cannot be accessed by guests', function () {
        $this->get(route('admin.settings.barangay'))
            ->assertRedirect(route('login'));
    });

    it('admin can save barangay settings', function () {
        $this->actingAs($this->admin);

        Livewire::test('pages::admin.settings.barangay')
            ->set('barangayName', 'Updated Barangay')
            ->set('municipality', 'Updated City')
            ->set('province', 'Metro Manila')
            ->call('save')
            ->assertHasNoErrors();

        expect(BarangayProfile::first()->barangay_name)->toBe('Updated Barangay');
    });

    it('barangay name is required', function () {
        $this->actingAs($this->admin);

        Livewire::test('pages::admin.settings.barangay')
            ->set('barangayName', '')
            ->call('save')
            ->assertHasErrors(['barangayName']);
    });
});
