<?php

use App\Models\BarangayOfficial;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->staff = User::factory()->staff()->create();
    $this->resident = User::factory()->create();
});

describe('officials index', function () {
    it('can be accessed by admin', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.officials.index'))
            ->assertSuccessful();
    });

    it('cannot be accessed by staff', function () {
        $this->actingAs($this->staff)
            ->get(route('admin.officials.index'))
            ->assertForbidden();
    });

    it('cannot be accessed by resident', function () {
        $this->actingAs($this->resident)
            ->get(route('admin.officials.index'))
            ->assertForbidden();
    });

    it('cannot be accessed by guests', function () {
        $this->get(route('admin.officials.index'))
            ->assertRedirect(route('login'));
    });
});

describe('officials management', function () {
    it('admin can create an official', function () {
        $this->actingAs($this->admin);

        Livewire::test('pages::admin.officials.index')
            ->call('openCreateModal')
            ->set('name', 'New Official')
            ->set('position', 'Kagawad')
            ->set('committee', 'Health')
            ->call('save')
            ->assertHasNoErrors();

        expect(BarangayOfficial::where('name', 'New Official')->exists())->toBeTrue();
    });

    it('admin can update an official', function () {
        $official = BarangayOfficial::factory()->create(['name' => 'Old Name']);
        $this->actingAs($this->admin);

        Livewire::test('pages::admin.officials.index')
            ->call('openEditModal', $official->id)
            ->set('name', 'Updated Name')
            ->call('save')
            ->assertHasNoErrors();

        expect($official->refresh()->name)->toBe('Updated Name');
    });

    it('admin can delete an official', function () {
        $official = BarangayOfficial::factory()->create();
        $this->actingAs($this->admin);

        Livewire::test('pages::admin.officials.index')
            ->call('confirmDelete', $official->id)
            ->call('deleteOfficial')
            ->assertHasNoErrors();

        expect(BarangayOfficial::find($official->id))->toBeNull();
    });

    it('name and position are required', function () {
        $this->actingAs($this->admin);

        Livewire::test('pages::admin.officials.index')
            ->call('openCreateModal')
            ->set('name', '')
            ->set('position', '')
            ->call('save')
            ->assertHasErrors(['name', 'position']);
    });
});
