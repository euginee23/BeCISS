<?php

use App\Models\Blotter;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Blotter Number Generation
|--------------------------------------------------------------------------
*/

test('blotter number is generated correctly', function () {
    $number = Blotter::generateBlotterNumber();

    expect($number)->toMatch('/^BLT-\d{4}-\d{5}$/');
});

test('blotter number increments sequentially', function () {
    $year = date('Y');

    Blotter::factory()->create(['blotter_number' => "BLT-{$year}-00099"]);

    $next = Blotter::generateBlotterNumber();

    expect($next)->toBe("BLT-{$year}-00100");
});

/*
|--------------------------------------------------------------------------
| Route Access
|--------------------------------------------------------------------------
*/

test('admin can access blotter index', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get(route('blotters.index'))
        ->assertSuccessful();
});

test('staff can access blotter index', function () {
    $user = User::factory()->staff()->create();

    $this->actingAs($user)
        ->get(route('blotters.index'))
        ->assertSuccessful();
});

test('admin sees the resident name for each blotter in the index', function () {
    $user = User::factory()->admin()->create();

    $resident = Resident::factory()->create([
        'first_name' => 'Sample',
        'last_name' => 'Complainant',
        'middle_name' => null,
        'suffix' => null,
    ]);

    Blotter::factory()->for($resident)->create();

    $this->actingAs($user)
        ->get(route('blotters.index'))
        ->assertSuccessful()
        ->assertSeeText('Sample Complainant');
});

test('resident cannot access admin blotter index', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id, 'status' => 'approved']);

    $this->actingAs($user)
        ->get(route('blotters.index'))
        ->assertForbidden();
});

test('admin can access blotter create page', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get(route('blotters.create'))
        ->assertSuccessful();
});

test('admin can access blotter show page', function () {
    $user = User::factory()->admin()->create();
    $blotter = Blotter::factory()->create();

    $this->actingAs($user)
        ->get(route('blotters.show', $blotter))
        ->assertSuccessful();
});

test('admin can access blotter edit page for pending blotter', function () {
    $user = User::factory()->admin()->create();
    $blotter = Blotter::factory()->create(['status' => 'pending']);

    $this->actingAs($user)
        ->get(route('blotters.edit', $blotter))
        ->assertSuccessful();
});

/*
|--------------------------------------------------------------------------
| Resident Blotter Pages
|--------------------------------------------------------------------------
*/

test('resident can access their blotters index', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id, 'status' => 'approved']);

    $this->actingAs($user)
        ->get(route('resident.blotters.index'))
        ->assertSuccessful();
});

test('resident can access blotter create page', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id, 'status' => 'approved']);

    $this->actingAs($user)
        ->get(route('resident.blotters.create'))
        ->assertSuccessful();
});

/*
|--------------------------------------------------------------------------
| Blotter Model
|--------------------------------------------------------------------------
*/

test('blotter has correct type label', function () {
    $blotter = Blotter::factory()->create(['incident_type' => 'theft']);

    expect($blotter->type_label)->toBe('Theft');
});

test('blotter has correct status label', function () {
    $blotter = Blotter::factory()->create(['status' => 'ready_for_pickup']);

    expect($blotter->status_label)->toBe('Ready for Pickup');
});

test('blotter has correct status color', function () {
    $blotter = Blotter::factory()->create(['status' => 'pending']);
    expect($blotter->status_color)->toBe('amber');

    $blotter = Blotter::factory()->create(['status' => 'completed']);
    expect($blotter->status_color)->toBe('green');
});

test('blotter belongs to a resident', function () {
    $resident = Resident::factory()->create();
    $blotter = Blotter::factory()->create(['resident_id' => $resident->id]);

    expect($blotter->resident->id)->toBe($resident->id);
});

test('resident has many blotters', function () {
    $resident = Resident::factory()->create();
    Blotter::factory()->count(3)->create(['resident_id' => $resident->id]);

    expect($resident->blotters)->toHaveCount(3);
});

/*
|--------------------------------------------------------------------------
| Registered Complainant Required
|--------------------------------------------------------------------------
*/

test('a blotter must belong to a registered resident', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::blotters.create')
        ->set('resident_id', null)
        ->set('incident_type', array_key_first(Blotter::TYPES))
        ->set('incident_datetime', now()->subDay()->format('Y-m-d\TH:i'))
        ->set('narrative', 'A sufficiently detailed narrative of the incident.')
        ->call('save')
        ->assertHasErrors(['resident_id']);
});

test('every blotter is linked to a resident', function () {
    $blotter = Blotter::factory()->create();

    expect($blotter->resident_id)->not->toBeNull();
});

test('blotter type label shows custom text for other type', function () {
    $blotter = Blotter::factory()->create([
        'incident_type' => 'other',
        'incident_type_other' => 'Illegal Parking',
    ]);

    expect($blotter->type_label)->toBe('Illegal Parking');
});

test('blotter type label falls back to Other when no custom text', function () {
    $blotter = Blotter::factory()->create([
        'incident_type' => 'other',
        'incident_type_other' => null,
    ]);

    expect($blotter->type_label)->toBe('Other');
});

test('blotter show page renders the resident name', function () {
    $user = User::factory()->admin()->create();

    $resident = Resident::factory()->create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => null,
        'suffix' => null,
    ]);

    $blotter = Blotter::factory()->for($resident)->create();

    $this->actingAs($user)
        ->get(route('blotters.show', $blotter))
        ->assertSuccessful()
        ->assertSeeText('Maria Santos');
});
