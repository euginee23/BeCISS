<?php

use App\Models\Certificate;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

test('admins and staff can open the reports page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('reports.index'))
        ->assertOk();

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('reports.index'))
        ->assertOk();
});

test('residents cannot open the reports page', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertForbidden();
});

/**
 * The certificate number is derived from the last saved row, so a batched
 * create() would hand every model the same number. Create them one by one.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeCertificates(int $count, array $attributes = []): void
{
    for ($i = 0; $i < $count; $i++) {
        Certificate::factory()->create($attributes);
    }
}

test('the summary counts only records inside the date range', function () {
    makeCertificates(3, ['created_at' => now()->subDays(2)]);
    makeCertificates(2, ['created_at' => now()->subMonths(3)]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::reports.index')
        ->set('from', now()->subWeek()->toDateString())
        ->set('to', now()->toDateString());

    expect($component->instance()->summary['Certificate requests'])->toBe(3);

    $component->set('from', now()->subMonths(6)->toDateString());

    expect($component->instance()->summary['Certificate requests'])->toBe(5);
});

test('collections total only paid records', function () {
    Certificate::factory()->create(['fee' => 100, 'is_paid' => true, 'or_number' => 'OR-1']);
    Certificate::factory()->create(['fee' => 50, 'is_paid' => true, 'or_number' => 'OR-2']);
    Certificate::factory()->create(['fee' => 75, 'is_paid' => false, 'or_number' => null]);

    $revenue = Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::reports.index')
        ->set('from', now()->subWeek()->toDateString())
        ->set('to', now()->toDateString())
        ->instance()
        ->revenue;

    expect($revenue['certificates'])->toBe(150.0)
        ->and($revenue['outstanding'])->toBe(75.0)
        ->and($revenue['receipts'])->toBe(2);
});

test('certificates are broken down by type', function () {
    makeCertificates(2, ['type' => 'barangay_clearance']);
    Certificate::factory()->create(['type' => 'certificate_of_indigency']);

    $rows = Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::reports.index')
        ->set('from', now()->subWeek()->toDateString())
        ->set('to', now()->toDateString())
        ->instance()
        ->sections['certificates-by-type']['rows'];

    expect($rows['Barangay Clearance'])->toBe(2)
        ->and($rows['Certificate of Indigency'])->toBe(1);
});

test('the export action returns a csv with a header row', function () {
    makeCertificates(2, ['type' => 'barangay_clearance']);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::reports.index')
        ->set('from', now()->subWeek()->toDateString())
        ->set('to', now()->toDateString())
        ->instance();

    $response = $component->export('certificates-by-type');

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('content-type'))->toContain('text/csv');

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('Type,Total')
        ->and($csv)->toContain('"Barangay Clearance",2');
});

test('an unknown export section is rejected', function () {
    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::reports.index')
        ->instance();

    $component->export('not-a-real-section');
})->throws(NotFoundHttpException::class);
