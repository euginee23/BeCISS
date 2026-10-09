<?php

use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    $this->cashier = User::factory()->staff()->create(['name' => 'Cashier One']);
    $this->otherCashier = User::factory()->staff()->create(['name' => 'Cashier Two']);
    $this->admin = User::factory()->admin()->create();
});

it('is only available to staff with the payments permission', function () {
    $this->actingAs($this->admin)->get(route('reports.collections'))->assertSuccessful();
    $this->actingAs($this->cashier)->get(route('reports.collections'))->assertSuccessful();

    $this->actingAs(User::factory()->staff(['certificates'])->create())
        ->get(route('reports.collections'))
        ->assertForbidden();

    $resident = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $resident->id]);

    $this->actingAs($resident)
        ->get(route('reports.collections'))
        ->assertForbidden();
});

it('lists the payments received in the period with totals', function () {
    Certificate::factory()->barangayClearance()->create()->recordPayment('OR-100', $this->cashier);
    Certificate::factory()->residency()->create()->recordPayment('OR-101', $this->otherCashier);
    Blotter::factory()->create(['fee' => 50])->recordPayment('OR-102', $this->cashier);

    $this->travel(-3)->days();
    Certificate::factory()->barangayClearance()->create()->recordPayment('OR-090', $this->cashier);
    $this->travelBack();

    $component = Livewire::actingAs($this->admin)->test('pages::reports.collections');

    $component->assertSee('OR-100')
        ->assertSee('OR-101')
        ->assertSee('OR-102')
        ->assertDontSee('OR-090');

    expect($component->instance()->grandTotal)->toBe(130.0)
        ->and($component->instance()->totalsByService['Barangay Clearance'])->toBe(['count' => 1, 'amount' => 50.0])
        ->and($component->instance()->totalsByService['Blotter Report'])->toBe(['count' => 1, 'amount' => 50.0])
        ->and($component->instance()->totalsByCashier['Cashier One'])->toBe(['count' => 2, 'amount' => 100.0]);

    $component->call('setRange', 'month');

    expect($component->instance()->grandTotal)->toBe(now()->day > 3 ? 180.0 : 130.0);
});

it('filters by cashier and service', function () {
    Certificate::factory()->barangayClearance()->create()->recordPayment('OR-200', $this->cashier);
    Certificate::factory()->residency()->create()->recordPayment('OR-201', $this->otherCashier);
    Blotter::factory()->create(['fee' => 50])->recordPayment('OR-202', $this->cashier);

    $orNumbers = fn ($component) => $component->instance()->payments->pluck('or_number')->all();

    $component = Livewire::actingAs($this->admin)->test('pages::reports.collections');

    expect($orNumbers($component->set('cashier', (string) $this->otherCashier->id)))->toBe(['OR-201'])
        ->and($orNumbers($component->set('cashier', '')->set('source', 'blotter')))->toBe(['OR-202'])
        ->and($orNumbers($component->set('source', 'barangay_clearance')))->toBe(['OR-200']);
});

it('exports the listing as csv', function () {
    Certificate::factory()->barangayClearance()->create()->recordPayment('OR-300', $this->cashier);

    $response = Livewire::actingAs($this->admin)
        ->test('pages::reports.collections')
        ->instance()
        ->export();

    expect($response)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('OR No.')
        ->toContain('OR-300')
        ->toContain('Cashier One')
        ->toContain('TOTAL');
});
