<?php

use App\Mail\CertificateReadyForPickup;
use App\Mail\CertificateRejected;
use App\Models\Certificate;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('marking certificate ready for pickup sends email', function () {
    Mail::fake();

    $user = User::factory()->create(['role' => 'admin']);
    $residentUser = User::factory()->create(['role' => 'resident']);
    $resident = Resident::factory()->create(['user_id' => $residentUser->id]);
    $certificate = Certificate::factory()->processing()->create([
        'resident_id' => $resident->id,
        'processed_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::certificates.show', ['certificate' => $certificate])
        ->call('markReadyForPickup');

    Mail::assertSent(CertificateReadyForPickup::class, function ($mail) use ($residentUser) {
        return $mail->hasTo($residentUser->email);
    });
});

test('rejecting certificate sends rejection email', function () {
    Mail::fake();

    $user = User::factory()->create(['role' => 'admin']);
    $residentUser = User::factory()->create(['role' => 'resident']);
    $resident = Resident::factory()->create(['user_id' => $residentUser->id]);
    $certificate = Certificate::factory()->create([
        'resident_id' => $resident->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::certificates.show', ['certificate' => $certificate])
        ->set('rejectionReason', 'Incomplete documents')
        ->call('rejectCertificate');

    Mail::assertSent(CertificateRejected::class, function ($mail) use ($residentUser) {
        return $mail->hasTo($residentUser->email);
    });
});

test('no email sent when resident has no linked user', function () {
    Mail::fake();

    $user = User::factory()->create(['role' => 'admin']);
    $resident = Resident::factory()->create(['user_id' => null]);
    $certificate = Certificate::factory()->processing()->create([
        'resident_id' => $resident->id,
        'processed_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::certificates.show', ['certificate' => $certificate])
        ->call('markReadyForPickup');

    Mail::assertNothingSent();
});
