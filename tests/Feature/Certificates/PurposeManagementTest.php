<?php

use App\Models\CertificatePurpose;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('is available to admins only', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.purposes'))
        ->assertSuccessful()
        ->assertSee('Loan Application');

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.settings.purposes'))
        ->assertForbidden();
});

it('seeds the shipped purposes with other always last', function () {
    $options = CertificatePurpose::options();

    expect($options)->toContain('Employment / Job Application')
        ->and(end($options))->toBe(CertificatePurpose::OTHER);
});

it('adds a purpose to the end of the list', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.settings.purposes')
        ->call('openCreateModal')
        ->set('name', 'Burial Assistance')
        ->call('save')
        ->assertHasNoErrors();

    $options = CertificatePurpose::options();

    expect($options[count($options) - 2])->toBe('Burial Assistance');
    $this->assertDatabaseHas('activity_logs', ['module' => 'purposes', 'action' => 'created']);
});

it('rejects duplicates and the reserved other option', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.settings.purposes')
        ->call('openCreateModal')
        ->set('name', 'Loan Application')
        ->call('save')
        ->assertHasErrors(['name' => 'unique'])
        ->set('name', 'Other')
        ->call('save')
        ->assertHasErrors(['name' => 'not_in']);
});

it('hides deactivated purposes from the request forms', function () {
    $purpose = CertificatePurpose::where('name', 'Loan Application')->first();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.settings.purposes')
        ->call('toggleActive', $purpose->id);

    expect(CertificatePurpose::options())->not->toContain('Loan Application');
});

it('reorders purposes', function () {
    $first = CertificatePurpose::query()->ordered()->first();
    $second = CertificatePurpose::query()->ordered()->skip(1)->first();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.settings.purposes')
        ->call('move', $second->id, 'up');

    expect(CertificatePurpose::query()->ordered()->first()->id)->toBe($second->id)
        ->and(CertificatePurpose::query()->ordered()->skip(1)->first()->id)->toBe($first->id);
});

it('deletes a purpose', function () {
    $purpose = CertificatePurpose::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.settings.purposes')
        ->call('delete', $purpose->id);

    $this->assertModelMissing($purpose);
});
