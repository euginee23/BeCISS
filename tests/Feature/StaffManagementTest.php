<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Staff Management - Access Control
|--------------------------------------------------------------------------
*/

test('admin can view staff index', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('staff.index'))
        ->assertSuccessful();
});

test('staff cannot view staff index', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('staff.index'))
        ->assertForbidden();
});

test('resident cannot view staff index', function () {
    $resident = User::factory()->create();

    $this->actingAs($resident)
        ->get(route('staff.index'))
        ->assertRedirect();
});

test('admin can view staff create page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('staff.create'))
        ->assertSuccessful();
});

test('admin can view staff edit page', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $this->actingAs($admin)
        ->get(route('staff.edit', $staff))
        ->assertSuccessful();
});

/*
|--------------------------------------------------------------------------
| Staff Management - Create
|--------------------------------------------------------------------------
*/

test('admin can create a staff member', function () {
    $admin = User::factory()->admin()->create();

    Livewire\Livewire::actingAs($admin)
        ->test('pages::staff.create')
        ->set('name', 'John Staff')
        ->set('email', 'john@example.com')
        ->set('permissions', ['residents', 'certificates'])
        ->call('save')
        ->assertRedirect(route('staff.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'role' => 'staff',
    ]);

    $newStaff = User::where('email', 'john@example.com')->first();
    expect($newStaff->permissions)->toBe(['residents', 'certificates']);
    expect($newStaff->email_verified_at)->not->toBeNull();
});

test('admin can create an admin user', function () {
    $admin = User::factory()->admin()->create();

    Livewire\Livewire::actingAs($admin)
        ->test('pages::staff.create')
        ->set('name', 'New Admin')
        ->set('email', 'newadmin@example.com')
        ->set('isAdmin', true)
        ->call('save')
        ->assertRedirect(route('staff.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'newadmin@example.com',
        'role' => 'admin',
    ]);

    $newAdmin = User::where('email', 'newadmin@example.com')->first();
    expect($newAdmin->permissions)->toBe(User::STAFF_RESOURCES);
});

test('admin toggle checks all permissions', function () {
    $admin = User::factory()->admin()->create();

    Livewire\Livewire::actingAs($admin)
        ->test('pages::staff.create')
        ->set('isAdmin', true)
        ->assertSet('permissions', User::STAFF_RESOURCES);
});

test('create staff requires name and email', function () {
    $admin = User::factory()->admin()->create();

    Livewire\Livewire::actingAs($admin)
        ->test('pages::staff.create')
        ->set('name', '')
        ->set('email', '')
        ->call('save')
        ->assertHasErrors(['name', 'email']);
});

test('create staff requires unique email', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire\Livewire::actingAs($admin)
        ->test('pages::staff.create')
        ->set('name', 'Test')
        ->set('email', 'taken@example.com')
        ->set('permissions', ['residents'])
        ->call('save')
        ->assertHasErrors(['email']);
});

test('create staff requires permissions when not admin', function () {
    $admin = User::factory()->admin()->create();

    Livewire\Livewire::actingAs($admin)
        ->test('pages::staff.create')
        ->set('name', 'Test Staff')
        ->set('email', 'noperms@example.com')
        ->set('permissions', [])
        ->call('save')
        ->assertHasErrors(['permissions']);
});

/*
|--------------------------------------------------------------------------
| Staff Management - Edit
|--------------------------------------------------------------------------
*/

test('admin can update a staff member', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff(['residents'])->create();

    Livewire\Livewire::actingAs($admin)
        ->test('pages::staff.edit', ['user' => $staff])
        ->set('name', 'Updated Name')
        ->set('permissions', ['residents', 'certificates', 'appointments'])
        ->call('save')
        ->assertRedirect(route('staff.index'));

    $staff->refresh();
    expect($staff->name)->toBe('Updated Name');
    expect($staff->permissions)->toBe(['residents', 'certificates', 'appointments']);
});

test('admin can promote staff to admin', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff(['residents'])->create();

    Livewire\Livewire::actingAs($admin)
        ->test('pages::staff.edit', ['user' => $staff])
        ->set('isAdmin', true)
        ->call('save')
        ->assertRedirect(route('staff.index'));

    $staff->refresh();
    expect($staff->role)->toBe('admin');
    expect($staff->permissions)->toBe(User::STAFF_RESOURCES);
});

test('admin cannot change own role', function () {
    $admin = User::factory()->admin()->create();

    $component = Livewire\Livewire::actingAs($admin)
        ->test('pages::staff.edit', ['user' => $admin]);

    expect($component->get('isSelf'))->toBeTrue();
});

test('edit staff rejects resident users', function () {
    $admin = User::factory()->admin()->create();
    $resident = User::factory()->create();

    Livewire\Livewire::actingAs($admin)
        ->test('pages::staff.edit', ['user' => $resident])
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Staff Management - Delete
|--------------------------------------------------------------------------
*/

test('admin can delete a staff member', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    Livewire\Livewire::actingAs($admin)
        ->test('pages::staff.index')
        ->call('confirmDelete', $staff->id)
        ->assertSet('showDeleteModal', true)
        ->call('deleteStaff');

    $this->assertDatabaseMissing('users', ['id' => $staff->id]);
});

test('admin cannot delete themselves', function () {
    $admin = User::factory()->admin()->create();

    Livewire\Livewire::actingAs($admin)
        ->test('pages::staff.index')
        ->call('confirmDelete', $admin->id)
        ->assertSet('showDeleteModal', false);
});

/*
|--------------------------------------------------------------------------
| Permission Middleware
|--------------------------------------------------------------------------
*/

test('staff with residents permission can access residents', function () {
    $staff = User::factory()->staff(['residents'])->create();

    $this->actingAs($staff)
        ->get(route('residents.index'))
        ->assertSuccessful();
});

test('staff without residents permission cannot access residents', function () {
    $staff = User::factory()->staff(['certificates'])->create();

    $this->actingAs($staff)
        ->get(route('residents.index'))
        ->assertForbidden();
});

test('staff with certificates permission can access certificates', function () {
    $staff = User::factory()->staff(['certificates'])->create();

    $this->actingAs($staff)
        ->get(route('certificates.index'))
        ->assertSuccessful();
});

test('staff without certificates permission cannot access certificates', function () {
    $staff = User::factory()->staff(['residents'])->create();

    $this->actingAs($staff)
        ->get(route('certificates.index'))
        ->assertForbidden();
});

test('admin bypasses all permission checks', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('residents.index'))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->get(route('certificates.index'))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->get(route('appointments.index'))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->get(route('blotters.index'))
        ->assertSuccessful();
});

/*
|--------------------------------------------------------------------------
| User Model - hasPermission
|--------------------------------------------------------------------------
*/

test('admin hasPermission always returns true', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->hasPermission('residents'))->toBeTrue();
    expect($admin->hasPermission('certificates'))->toBeTrue();
    expect($admin->hasPermission('anything'))->toBeTrue();
});

test('staff hasPermission checks permissions array', function () {
    $staff = User::factory()->staff(['residents', 'blotters'])->create();

    expect($staff->hasPermission('residents'))->toBeTrue();
    expect($staff->hasPermission('blotters'))->toBeTrue();
    expect($staff->hasPermission('certificates'))->toBeFalse();
    expect($staff->hasPermission('appointments'))->toBeFalse();
});

test('staff with null permissions has no access', function () {
    $staff = User::factory()->create(['role' => 'staff', 'permissions' => null]);

    expect($staff->hasPermission('residents'))->toBeFalse();
});
