<?php

use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->staff = User::factory()->staff()->create();
    $this->resident = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $this->resident->id]);
});

describe('residents index', function () {
    it('can be accessed by admin', function () {
        $this->actingAs($this->admin)
            ->get(route('residents.index'))
            ->assertSuccessful();
    });

    it('can be accessed by staff', function () {
        $this->actingAs($this->staff)
            ->get(route('residents.index'))
            ->assertSuccessful();
    });

    it('cannot be accessed by resident users', function () {
        $this->actingAs($this->resident)
            ->get(route('residents.index'))
            ->assertForbidden();
    });

    it('cannot be accessed by guests', function () {
        $this->get(route('residents.index'))
            ->assertRedirect(route('login'));
    });

    it('displays residents in the table', function () {
        $resident = Resident::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('residents.index'))
            ->assertSee($resident->first_name)
            ->assertSee($resident->last_name);
    });
});

describe('residents create', function () {
    it('can be accessed by admin', function () {
        $this->actingAs($this->admin)
            ->get(route('residents.create'))
            ->assertSuccessful();
    });

    it('can be accessed by staff', function () {
        $this->actingAs($this->staff)
            ->get(route('residents.create'))
            ->assertSuccessful();
    });

    it('cannot be accessed by resident users', function () {
        $this->actingAs($this->resident)
            ->get(route('residents.create'))
            ->assertForbidden();
    });

    it('can create a new resident', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::residents.create')
            ->set('first_name', 'Juan')
            ->set('last_name', 'Dela Cruz')
            ->set('birthdate', '1990-05-15')
            ->set('gender', 'male')
            ->set('civil_status', 'single')
            ->set('house_number', '123')
            ->set('street', 'Main Street')
            ->set('purok', 'Purok 2')
            ->set('residency_start_date', now()->subYears(5)->toDateString())
            ->call('save')
            ->assertRedirect(route('residents.index'));

        $this->assertDatabaseHas('residents', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'house_number' => '123',
            'street' => 'Main Street',
            'purok' => 'Purok 2',
        ]);
    });

    it('capitalizes names and street on save', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::residents.create')
            ->set('first_name', 'juan')
            ->set('last_name', 'dela cruz')
            ->set('birthdate', '1990-05-15')
            ->set('gender', 'male')
            ->set('civil_status', 'single')
            ->set('street', 'mabini street')
            ->set('purok', 'Purok 2')
            ->set('residency_start_date', now()->subYears(5)->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('residents', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'street' => 'Mabini Street',
        ]);
    });

    it('validates required fields', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::residents.create')
            ->set('first_name', '')
            ->set('last_name', '')
            ->call('save')
            ->assertHasErrors(['first_name', 'last_name', 'birthdate', 'gender', 'civil_status', 'street', 'purok', 'residency_start_date']);
    });
});

describe('residents show', function () {
    it('can view a resident', function () {
        $resident = Resident::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('residents.show', $resident))
            ->assertSuccessful()
            ->assertSee($resident->full_name);
    });

    it('cannot be accessed by resident users', function () {
        $resident = Resident::factory()->create();

        $this->actingAs($this->resident)
            ->get(route('residents.show', $resident))
            ->assertForbidden();
    });
});

describe('residents edit', function () {
    it('can be accessed by admin', function () {
        $resident = Resident::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('residents.edit', $resident))
            ->assertSuccessful();
    });

    it('can update a resident', function () {
        $resident = Resident::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::residents.edit', ['resident' => $resident])
            ->set('first_name', 'Updated Name')
            ->call('save')
            ->assertRedirect(route('residents.index'));

        $this->assertDatabaseHas('residents', [
            'id' => $resident->id,
            'first_name' => 'Updated Name',
        ]);
    });
});

describe('residents delete', function () {
    it('can delete a resident', function () {
        $resident = Resident::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::residents.index')
            ->call('confirmDelete', $resident->id)
            ->assertSet('showDeleteModal', true)
            ->call('deleteResident')
            ->assertSet('showDeleteModal', false);

        $this->assertSoftDeleted('residents', ['id' => $resident->id]);
    });
});
