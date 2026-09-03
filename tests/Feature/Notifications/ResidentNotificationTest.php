<?php

use App\Mail\RegistrationRejected;
use App\Models\Certificate;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\ResidentNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Resident approval / rejection database notifications
|--------------------------------------------------------------------------
*/

test('approving a resident sends database notification to the resident', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $residentUser = User::factory()->resident()->create();
    Resident::factory()->pending()->create(['user_id' => $residentUser->id]);
    $resident = $residentUser->resident;

    Livewire::actingAs($admin)
        ->test('pages::residents.index')
        ->call('approveResident', $resident->id);

    Notification::assertSentTo($residentUser, ResidentNotification::class, function ($n) {
        return $n->type === 'registration_approved';
    });
});

test('rejecting a resident emails the reason and removes the account', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $residentUser = User::factory()->resident()->create(['email' => 'applicant@example.com']);
    Resident::factory()->pending()->create(['user_id' => $residentUser->id]);
    $resident = $residentUser->resident;

    Livewire::actingAs($admin)
        ->test('pages::residents.index')
        ->set('residentToReject', $resident->id)
        ->set('rejectionReason', 'Incomplete documents')
        ->call('rejectResident');

    Mail::assertSent(RegistrationRejected::class, function (RegistrationRejected $mail) {
        return $mail->hasTo('applicant@example.com')
            && $mail->reason === 'Incomplete documents';
    });

    $this->assertDatabaseMissing('users', ['id' => $residentUser->id]);
    $this->assertDatabaseMissing('residents', ['id' => $resident->id]);
});

/*
|--------------------------------------------------------------------------
| Certificate status database notifications
|--------------------------------------------------------------------------
*/

test('starting processing sends database notification to the resident', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $residentUser = User::factory()->resident()->create();
    $resident = Resident::factory()->create(['user_id' => $residentUser->id]);
    $certificate = Certificate::factory()->create(['resident_id' => $resident->id]);

    Livewire::actingAs($admin)
        ->test('pages::certificates.show', ['certificate' => $certificate])
        ->call('startProcessing');

    Notification::assertSentTo($residentUser, ResidentNotification::class, function ($n) {
        return $n->type === 'certificate_processing';
    });
});

test('marking ready for pickup sends database notification to the resident', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $residentUser = User::factory()->resident()->create();
    $resident = Resident::factory()->create(['user_id' => $residentUser->id]);
    $certificate = Certificate::factory()->processing()->create([
        'resident_id' => $resident->id,
        'processed_by' => $admin->id,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::certificates.show', ['certificate' => $certificate])
        ->call('markReadyForPickup');

    Notification::assertSentTo($residentUser, ResidentNotification::class, function ($n) {
        return $n->type === 'certificate_ready';
    });
});

test('completing a certificate sends database notification to the resident', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $residentUser = User::factory()->resident()->create();
    $resident = Resident::factory()->create(['user_id' => $residentUser->id]);
    $certificate = Certificate::factory()->create([
        'resident_id' => $resident->id,
        'status' => 'ready_for_pickup',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::certificates.show', ['certificate' => $certificate])
        ->set('orNumber', 'OR-12345')
        ->call('completeCertificate');

    Notification::assertSentTo($residentUser, ResidentNotification::class, function ($n) {
        return $n->type === 'certificate_completed';
    });
});

test('rejecting a certificate sends database notification to the resident', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $residentUser = User::factory()->resident()->create();
    $resident = Resident::factory()->create(['user_id' => $residentUser->id]);
    $certificate = Certificate::factory()->create(['resident_id' => $resident->id]);

    Livewire::actingAs($admin)
        ->test('pages::certificates.show', ['certificate' => $certificate])
        ->set('rejectionReason', 'Invalid documents supplied')
        ->call('rejectCertificate');

    Notification::assertSentTo($residentUser, ResidentNotification::class, function ($n) {
        return $n->type === 'certificate_rejected';
    });
});

/*
|--------------------------------------------------------------------------
| Notifications page
|--------------------------------------------------------------------------
*/

test('resident can view notifications page', function () {
    $residentUser = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $residentUser->id]);

    $this->actingAs($residentUser)
        ->get(route('resident.notifications'))
        ->assertOk();
});

test('mark all as read clears unread notifications', function () {
    $residentUser = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $residentUser->id]);

    $residentUser->notify(new ResidentNotification(
        type: 'registration_approved',
        title: 'Test',
        body: 'Test body',
    ));

    expect($residentUser->unreadNotifications()->count())->toBe(1);

    Livewire::actingAs($residentUser)
        ->test('pages::resident.notifications')
        ->call('markAllRead');

    expect($residentUser->fresh()->unreadNotifications()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Notification presentation
|--------------------------------------------------------------------------
*/

test('blotter notification types have their own icon', function () {
    foreach (['blotter_processing', 'blotter_ready', 'blotter_completed', 'blotter_rejected'] as $type) {
        expect(ResidentNotification::iconFor($type)['icon'])->not->toBe('bell');
    }
});

test('an unknown notification type falls back to a bell', function () {
    expect(ResidentNotification::iconFor('something_else')['icon'])->toBe('bell')
        ->and(ResidentNotification::iconFor(null)['icon'])->toBe('bell');
});

test('the notifications page paginates past the first page', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id]);

    for ($i = 0; $i < 18; $i++) {
        $user->notify(new ResidentNotification(
            type: 'certificate_ready',
            title: 'Certificate '.$i,
            body: 'Body '.$i,
            url: null,
        ));
    }

    Livewire::actingAs($user)
        ->test('pages::resident.notifications')
        ->assertOk();
});

test('staff can reach the shared notifications page', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('notifications'))
        ->assertOk();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('notifications'))
        ->assertOk();
});
