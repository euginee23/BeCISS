<?php

use App\Models\ActivityLog;
use App\Models\Blotter;
use App\Models\Payment;
use App\Models\ServiceFee;
use App\Models\User;
use Livewire\Livewire;

test('a cashier can record a blotter payment before release', function () {
    $blotter = Blotter::factory()->readyForPickup()->create(['fee' => 250]);

    Livewire::actingAs(User::factory()->staff(['blotters', 'payments'])->create())
        ->test('pages::blotters.show', ['blotter' => $blotter])
        ->assertSee('Record Payment')
        ->call('openPaymentModal')
        ->set('orNumber', ' OR-7001 ')
        ->call('recordPayment')
        ->assertHasNoErrors()
        ->assertSet('showPaymentModal', false);

    $blotter->refresh();

    expect($blotter->is_paid)->toBeTrue()
        ->and($blotter->or_number)->toBe('OR-7001')
        ->and($blotter->status)->toBe('ready_for_pickup');

    $payment = Payment::sole();

    expect((float) $payment->amount)->toBe(250.0)
        ->and($payment->payable->is($blotter))->toBeTrue();

    expect(ActivityLog::where('module', 'blotters')->where('action', 'paid')->exists())->toBeTrue();
});

test('a blotter paid ahead is released without asking for a receipt again', function () {
    $blotter = Blotter::factory()->readyForPickup()->create(['fee' => 250]);
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::blotters.show', ['blotter' => $blotter])
        ->set('orNumber', 'OR-7002')
        ->call('recordPayment')
        ->set('orNumber', '')
        ->call('completeBlotter')
        ->assertHasNoErrors();

    expect($blotter->refresh()->status)->toBe('completed')
        ->and(Payment::count())->toBe(1);
});

test('blotter payments show in the reports collections', function () {
    $blotter = Blotter::factory()->processing()->create(['fee' => 250]);
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::blotters.show', ['blotter' => $blotter])
        ->set('orNumber', 'OR-7003')
        ->call('recordPayment')
        ->assertHasNoErrors();

    $revenue = Livewire::actingAs($admin)->test('pages::reports.index')->get('revenue');

    expect($revenue['blotters'])->toBe(250.0);
});

test('staff without the payments permission cannot record a blotter payment', function () {
    $blotter = Blotter::factory()->readyForPickup()->create(['fee' => 250]);

    Livewire::actingAs(User::factory()->staff(['blotters'])->create())
        ->test('pages::blotters.show', ['blotter' => $blotter])
        ->assertDontSee('Record Payment')
        ->set('orNumber', 'OR-7004')
        ->call('recordPayment')
        ->assertForbidden();

    expect(Payment::count())->toBe(0);
});

test('a blotter payment needs an unused OR number', function () {
    $taken = Blotter::factory()->completed()->create(['is_paid' => true, 'or_number' => 'OR-7005']);
    Payment::factory()->create(['payable_type' => Blotter::class, 'payable_id' => $taken->id, 'or_number' => 'OR-7005']);
    $blotter = Blotter::factory()->readyForPickup()->create(['fee' => 250]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::blotters.show', ['blotter' => $blotter])
        ->set('orNumber', 'OR-7005')
        ->call('recordPayment')
        ->assertHasErrors(['orNumber']);

    expect($blotter->refresh()->is_paid)->toBeFalse();
});

test('a blotter cannot be paid twice or while still pending', function (string $status, bool $isPaid) {
    $blotter = Blotter::factory()->create(['status' => $status, 'fee' => 250, 'is_paid' => $isPaid]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::blotters.show', ['blotter' => $blotter])
        ->set('orNumber', 'OR-7006')
        ->call('recordPayment')
        ->assertHasErrors(['orNumber']);

    expect(Payment::count())->toBe(0);
})->with([
    'already paid' => ['ready_for_pickup', true],
    'pending' => ['pending', false],
    'rejected' => ['rejected', false],
]);

test('the blotters list flags unpaid fees', function () {
    Blotter::factory()->readyForPickup()->create(['fee' => 250, 'blotter_number' => 'BLT-2026-90001']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::blotters.index')
        ->assertSee('BLT-2026-90001')
        ->assertSee('Unpaid');
});

test('staff granted service fees can open and update the page', function () {
    ServiceFee::sync();
    $fee = ServiceFee::where('service_type', 'blotter')->firstOrFail();
    $secretary = User::factory()->staff(['blotters', 'service_fees'])->create();

    $this->actingAs($secretary)
        ->get(route('admin.settings.service-fees'))
        ->assertSuccessful();

    $this->actingAs($secretary)
        ->get(route('dashboard'))
        ->assertSee(route('admin.settings.service-fees'));

    Livewire::actingAs($secretary)
        ->test('pages::admin.settings.service-fees')
        ->call('openEditModal', $fee->id)
        ->set('editingFee', '300')
        ->call('updateFee')
        ->assertHasNoErrors();

    expect((float) $fee->refresh()->fee)->toBe(300.0)
        ->and(ActivityLog::where('module', 'service_fees')->where('user_id', $secretary->id)->exists())->toBeTrue();
});

test('staff without service fees cannot open the page', function () {
    $this->actingAs(User::factory()->staff(['blotters'])->create())
        ->get(route('admin.settings.service-fees'))
        ->assertForbidden();
});

test('admins can grant the service fees permission', function () {
    $staff = User::factory()->staff(['blotters'])->create();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::staff.edit', ['user' => $staff])
        ->set('permissions', ['blotters', 'service_fees'])
        ->call('save')
        ->assertHasNoErrors();

    expect($staff->refresh()->hasPermission('service_fees'))->toBeTrue();
});
