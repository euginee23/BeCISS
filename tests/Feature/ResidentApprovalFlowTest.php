<?php

use App\Models\Resident;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Middleware: EnsureResidentApproved
|--------------------------------------------------------------------------
*/

test('resident without profile can access dashboard', function () {
    $user = User::factory()->resident()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('resident with pending profile is redirected to pending-approval', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->pending()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('pending-approval'));
});

test('resident with approved profile can access dashboard', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('resident with rejected profile can access dashboard', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->rejected()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('admin users bypass resident approval middleware', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('staff users bypass resident approval middleware', function () {
    $user = User::factory()->staff()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Pending Approval Page
|--------------------------------------------------------------------------
*/

test('pending-approval page is accessible to authenticated users', function () {
    $user = User::factory()->resident()->create();

    $this->actingAs($user)
        ->get(route('pending-approval'))
        ->assertOk();
});

test('pending-approval page is not accessible to guests', function () {
    $this->get(route('pending-approval'))
        ->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| Resident Model: Status Helpers
|--------------------------------------------------------------------------
*/

test('resident isPending returns true for pending status', function () {
    $resident = Resident::factory()->pending()->make();

    expect($resident->isPending())->toBeTrue();
    expect($resident->isApproved())->toBeFalse();
    expect($resident->isRejected())->toBeFalse();
});

test('resident isApproved returns true for approved status', function () {
    $resident = Resident::factory()->make();

    expect($resident->isApproved())->toBeTrue();
    expect($resident->isPending())->toBeFalse();
    expect($resident->isRejected())->toBeFalse();
});

test('resident isRejected returns true for rejected status', function () {
    $resident = Resident::factory()->rejected()->make();

    expect($resident->isRejected())->toBeTrue();
    expect($resident->isPending())->toBeFalse();
    expect($resident->isApproved())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Resident Model: Scopes
|--------------------------------------------------------------------------
*/

test('approved scope returns only approved residents', function () {
    $user1 = User::factory()->resident()->create();
    $user2 = User::factory()->resident()->create();
    $user3 = User::factory()->resident()->create();

    Resident::factory()->create(['user_id' => $user1->id]);
    Resident::factory()->pending()->create(['user_id' => $user2->id]);
    Resident::factory()->rejected()->create(['user_id' => $user3->id]);

    expect(Resident::approved()->count())->toBe(1);
});

test('pending scope returns only pending residents', function () {
    $user1 = User::factory()->resident()->create();
    $user2 = User::factory()->resident()->create();

    Resident::factory()->create(['user_id' => $user1->id]);
    Resident::factory()->pending()->create(['user_id' => $user2->id]);

    expect(Resident::pending()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Admin/Staff: Approve & Reject Residents
|--------------------------------------------------------------------------
*/

test('staff can access residents page', function () {
    $user = User::factory()->staff()->create();

    $this->actingAs($user)
        ->get(route('residents.index'))
        ->assertOk();
});

test('admin can access residents page', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get(route('residents.index'))
        ->assertOk();
});

test('resident cannot access residents management page', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('residents.index'))
        ->assertForbidden();
});
