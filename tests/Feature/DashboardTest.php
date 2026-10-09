<?php

use App\Models\Certificate;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the admin dashboard no longer shows quick access actions', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('New Resident')
        ->assertDontSee('New Appointment');
});

test('the resident dashboard no longer shows quick access actions', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->for($user)->create();

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('View &amp; request certificates', false)
        ->assertDontSee('Schedule &amp; manage appointments', false);
});

test('cashiers see collection widgets with today and month totals', function () {
    $cashier = User::factory()->staff()->create();
    Certificate::factory()->barangayClearance()->create()->recordPayment('OR-1', $cashier);
    Certificate::factory()->awaitingPayment()->create();

    $component = Livewire::actingAs($cashier)->test('pages::dashboard');

    $component->assertSee('Collected Today')->assertSee('Awaiting Payment');

    expect($component->instance()->collections)
        ->today->toBe(50.0)
        ->month->toBe(50.0)
        ->awaiting_count->toBe(1);
});

test('staff without the payments permission do not see collections', function () {
    $this->actingAs(User::factory()->staff(['residents'])->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Collected Today')
        ->assertDontSee(route('certificates.index'))
        ->assertSee('Population');
});

test('dashboard demographics count approved residents by sector', function () {
    Resident::factory()->senior()->create(['gender' => 'female', 'is_voter' => true]);
    Resident::factory()->adult()->pwd()->create(['gender' => 'male', 'is_voter' => false]);
    Resident::factory()->adult()->pending()->create();

    $demographics = Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::dashboard')
        ->instance()
        ->demographics;

    expect($demographics)
        ->total->toBe(2)
        ->seniors->toBe(1)
        ->pwd->toBe(1)
        ->voters->toBe(1)
        ->and(array_sum($demographics['by_purok']['counts']))->toBe(2);
});

test('dashboard trends cover the last twelve months', function () {
    Certificate::factory()->create();

    $trends = Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::dashboard')
        ->instance()
        ->monthlyTrends;

    expect($trends['labels'])->toHaveCount(12)
        ->and(end($trends['labels']))->toBe(now()->format('M Y'))
        ->and(end($trends['requests']))->toBe(1);
});
