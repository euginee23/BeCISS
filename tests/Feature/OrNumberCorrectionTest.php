<?php

use App\Models\ActivityLog;
use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\User;
use App\Services\CertificateWorkflow;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();

    $this->admin = User::factory()->admin()->create();

    $this->certificate = Certificate::factory()->awaitingPayment()->create();
    app(CertificateWorkflow::class)->recordPayment($this->certificate, 'OR-WRONG', $this->admin);
});

describe('certificates', function () {
    it('corrects the OR number on the certificate and its payment', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $this->certificate])
            ->call('openOrNumberModal')
            ->assertSet('correctedOrNumber', 'OR-WRONG')
            ->set('correctedOrNumber', ' OR-RIGHT ')
            ->call('correctOrNumber')
            ->assertHasNoErrors()
            ->assertSet('showOrNumberModal', false)
            ->assertSee('OR-RIGHT');

        $certificate = $this->certificate->fresh();

        expect($certificate->or_number)->toBe('OR-RIGHT')
            ->and($certificate->payments()->sole())
            ->or_number->toBe('OR-RIGHT')
            ->amount->toEqual('50.00')
            ->and(ActivityLog::query()->where('action', 'or_corrected')->sole()->properties)
            ->toBe(['from' => 'OR-WRONG', 'to' => 'OR-RIGHT']);
    });

    it('refuses an OR number already used on another payment', function () {
        $other = Certificate::factory()->awaitingPayment()->create();
        app(CertificateWorkflow::class)->recordPayment($other, 'OR-TAKEN', $this->admin);

        Livewire::actingAs($this->admin)
            ->test('pages::certificates.show', ['certificate' => $this->certificate])
            ->set('correctedOrNumber', 'OR-TAKEN')
            ->call('correctOrNumber')
            ->assertHasErrors(['correctedOrNumber']);

        expect($this->certificate->fresh()->or_number)->toBe('OR-WRONG');
    });

    it('requires the payments permission', function () {
        $staff = User::factory()->staff(['certificates'])->create();

        Livewire::actingAs($staff)
            ->test('pages::certificates.show', ['certificate' => $this->certificate])
            ->set('correctedOrNumber', 'OR-RIGHT')
            ->call('correctOrNumber')
            ->assertForbidden();

        expect($this->certificate->fresh()->or_number)->toBe('OR-WRONG');
    });
});

describe('blotters', function () {
    beforeEach(function () {
        $this->blotter = Blotter::factory()->completed()->create(['fee' => 100, 'is_paid' => false, 'or_number' => null]);
        $this->blotter->recordPayment('BLT-WRONG', $this->admin);
    });

    it('corrects the OR number on the blotter and its payment', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::blotters.show', ['blotter' => $this->blotter])
            ->call('openOrNumberModal')
            ->set('correctedOrNumber', 'BLT-RIGHT')
            ->call('correctOrNumber')
            ->assertHasNoErrors()
            ->assertSee('BLT-RIGHT');

        expect($this->blotter->fresh()->or_number)->toBe('BLT-RIGHT')
            ->and($this->blotter->payments()->sole()->or_number)->toBe('BLT-RIGHT');
    });

    it('refuses an OR number already used on another payment', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::blotters.show', ['blotter' => $this->blotter])
            ->set('correctedOrNumber', 'OR-WRONG')
            ->call('correctOrNumber')
            ->assertHasErrors(['correctedOrNumber']);

        expect($this->blotter->fresh()->or_number)->toBe('BLT-WRONG');
    });

    it('keeps the payment in step when the OR is changed on the edit form', function () {
        $this->blotter->update(['status' => 'processing']);

        Livewire::actingAs($this->admin)
            ->test('pages::blotters.edit', ['blotter' => $this->blotter])
            ->set('or_number', 'BLT-EDITED')
            ->call('save')
            ->assertHasNoErrors();

        expect($this->blotter->fresh()->or_number)->toBe('BLT-EDITED')
            ->and($this->blotter->payments()->sole()->or_number)->toBe('BLT-EDITED');
    });
});
