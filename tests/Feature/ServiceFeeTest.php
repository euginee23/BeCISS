<?php

use App\Models\ServiceFee;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| ServiceFee Model
|--------------------------------------------------------------------------
*/

test('getFee returns correct fee for active service', function () {
    ServiceFee::factory()->create([
        'service_type' => 'test_service',
        'fee' => 75.00,
        'is_active' => true,
    ]);

    expect(ServiceFee::getFee('test_service'))->toBe(75.00);
});

test('getFee returns zero for inactive service', function () {
    ServiceFee::factory()->create([
        'service_type' => 'inactive_service',
        'fee' => 100.00,
        'is_active' => false,
    ]);

    expect(ServiceFee::getFee('inactive_service'))->toBe(0.00);
});

test('getFee returns zero for unknown service type', function () {
    expect(ServiceFee::getFee('nonexistent_service'))->toBe(0.00);
});

test('sync creates all predefined service fee records', function () {
    ServiceFee::sync();

    foreach (array_keys(ServiceFee::CERTIFICATE_SERVICES) as $type) {
        $this->assertDatabaseHas('service_fees', ['service_type' => $type]);
    }

    foreach (array_keys(ServiceFee::BLOTTER_SERVICES) as $type) {
        $this->assertDatabaseHas('service_fees', ['service_type' => $type]);
    }
});

test('sync does not overwrite existing fee amounts', function () {
    ServiceFee::sync();
    ServiceFee::where('service_type', 'barangay_clearance')->update(['fee' => 150.00]);

    ServiceFee::sync();

    expect(ServiceFee::where('service_type', 'barangay_clearance')->value('fee'))->toBe('150.00');
});

/*
|--------------------------------------------------------------------------
| Admin Service Fee Management Page
|--------------------------------------------------------------------------
*/

test('admin can access service fees page', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get(route('admin.settings.service-fees'))
        ->assertSuccessful();
});

test('non-admin cannot access service fees page', function () {
    $user = User::factory()->resident()->create();

    $this->actingAs($user)
        ->get(route('admin.settings.service-fees'))
        ->assertRedirect();
});

test('admin can update a service fee amount', function () {
    $user = User::factory()->admin()->create();
    ServiceFee::sync();
    $fee = ServiceFee::where('service_type', 'barangay_clearance')->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::admin.settings.service-fees')
        ->call('openEditModal', $fee->id)
        ->set('editingFee', '75.00')
        ->call('updateFee');

    $this->assertDatabaseHas('service_fees', [
        'id' => $fee->id,
        'fee' => '75.00',
    ]);
});

test('admin can deactivate a service fee', function () {
    $user = User::factory()->admin()->create();
    ServiceFee::sync();
    $fee = ServiceFee::where('service_type', 'blotter')->firstOrFail();
    $fee->update(['is_active' => true]);

    Livewire::actingAs($user)
        ->test('pages::admin.settings.service-fees')
        ->call('openEditModal', $fee->id)
        ->set('editingIsActive', false)
        ->call('updateFee');

    $this->assertDatabaseHas('service_fees', [
        'id' => $fee->id,
        'is_active' => false,
    ]);
});

test('fee amount is required and must be numeric', function () {
    $user = User::factory()->admin()->create();
    ServiceFee::sync();
    $fee = ServiceFee::where('service_type', 'cedula')->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::admin.settings.service-fees')
        ->call('openEditModal', $fee->id)
        ->set('editingFee', '')
        ->call('updateFee')
        ->assertHasErrors(['editingFee']);
});
