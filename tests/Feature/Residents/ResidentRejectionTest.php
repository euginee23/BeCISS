<?php

use App\Mail\RegistrationRejected;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('a rejected applicant can register again with the same email', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $applicant = User::factory()->resident()->create(['email' => 'reapply@example.com']);
    $resident = Resident::factory()->pending()->create(['user_id' => $applicant->id]);

    Livewire::actingAs($admin)
        ->test('pages::residents.index')
        ->set('residentToReject', $resident->id)
        ->set('rejectionReason', 'Photo of ID was unreadable.')
        ->call('rejectResident')
        ->assertHasNoErrors();

    // The email is free again, so a fresh signup succeeds.
    Livewire::test('auth.register-modal')
        ->set('first_name', 'Juan')
        ->set('last_name', 'Dela Cruz')
        ->set('email', 'reapply@example.com')
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('register')
        ->assertHasNoErrors(['email']);

    $this->assertDatabaseHas('users', ['email' => 'reapply@example.com', 'first_name' => 'Juan']);
});

test('rejection emails the reason before the account is removed', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $applicant = User::factory()->resident()->create(['email' => 'applicant@example.com']);
    $resident = Resident::factory()->pending()->create([
        'user_id' => $applicant->id,
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => null,
        'suffix' => null,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::residents.index')
        ->set('residentToReject', $resident->id)
        ->set('rejectionReason', 'Address could not be verified.')
        ->call('rejectResident');

    Mail::assertSent(RegistrationRejected::class, function (RegistrationRejected $mail) {
        return $mail->hasTo('applicant@example.com')
            && $mail->recipientName === 'Maria Santos'
            && $mail->reason === 'Address could not be verified.';
    });
});

test('rejection requires a reason', function () {
    $admin = User::factory()->admin()->create();
    $resident = Resident::factory()->pending()->create();

    Livewire::actingAs($admin)
        ->test('pages::residents.index')
        ->set('residentToReject', $resident->id)
        ->set('rejectionReason', '')
        ->call('rejectResident')
        ->assertHasErrors(['rejectionReason']);

    $this->assertDatabaseHas('residents', ['id' => $resident->id]);
});

test('deleting a resident frees the email but keeps the resident record', function () {
    $admin = User::factory()->admin()->create();
    $residentUser = User::factory()->resident()->create(['email' => 'kept@example.com']);
    $resident = Resident::factory()->create(['user_id' => $residentUser->id]);

    Livewire::actingAs($admin)
        ->test('pages::residents.index')
        ->call('confirmDelete', $resident->id)
        ->call('deleteResident');

    $this->assertDatabaseMissing('users', ['id' => $residentUser->id]);

    // Soft deleted, so the request history it owns is preserved.
    $this->assertSoftDeleted('residents', ['id' => $resident->id]);
});
