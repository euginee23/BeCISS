<?php

use App\Mail\NewPendingRegistration;
use App\Mail\ResidentApproved;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| ResidentApproved — email sent to the resident on approval
|--------------------------------------------------------------------------
*/

test('approving a resident sends approval email to the resident', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $residentUser = User::factory()->resident()->create();
    $resident = Resident::factory()->pending()->create(['user_id' => $residentUser->id]);

    Livewire::actingAs($admin)
        ->test('pages::residents.index')
        ->call('approveResident', $resident->id);

    Mail::assertSent(ResidentApproved::class, function ($mail) use ($residentUser) {
        return $mail->hasTo($residentUser->email);
    });
});

test('approving a resident does not send email to other residents', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $residentUser = User::factory()->resident()->create();
    $otherUser = User::factory()->resident()->create();
    $resident = Resident::factory()->pending()->create(['user_id' => $residentUser->id]);

    Livewire::actingAs($admin)
        ->test('pages::residents.index')
        ->call('approveResident', $resident->id);

    Mail::assertNotSent(ResidentApproved::class, function ($mail) use ($otherUser) {
        return $mail->hasTo($otherUser->email);
    });
});

/*
|--------------------------------------------------------------------------
| NewPendingRegistration — email sent to admins on profile submission
|--------------------------------------------------------------------------
*/

test('submitting a profile sends pending registration email to all admins', function () {
    Mail::fake();

    $admin1 = User::factory()->admin()->create();
    $admin2 = User::factory()->admin()->create();
    $residentUser = User::factory()->resident()->create();

    Livewire::actingAs($residentUser)
        ->test('pages::complete-profile')
        ->set('first_name', 'Juan')
        ->set('last_name', 'dela Cruz')
        ->set('birthdate', '1990-01-15')
        ->set('gender', 'male')
        ->set('civil_status', 'single')
        ->set('contact_number', '09171234567')
        ->set('address', '123 Rizal Street')
        ->call('submitProfile');

    Mail::assertSent(NewPendingRegistration::class, function ($mail) use ($admin1) {
        return $mail->hasTo($admin1->email);
    });

    Mail::assertSent(NewPendingRegistration::class, function ($mail) use ($admin2) {
        return $mail->hasTo($admin2->email);
    });
});

test('submitting a profile does not send pending registration email to non-admins', function () {
    Mail::fake();

    $staffUser = User::factory()->staff()->create();
    $residentUser = User::factory()->resident()->create();

    Livewire::actingAs($residentUser)
        ->test('pages::complete-profile')
        ->set('first_name', 'Juan')
        ->set('last_name', 'dela Cruz')
        ->set('birthdate', '1990-01-15')
        ->set('gender', 'male')
        ->set('civil_status', 'single')
        ->set('contact_number', '09171234567')
        ->set('address', '123 Rizal Street')
        ->call('submitProfile');

    Mail::assertNotSent(NewPendingRegistration::class, function ($mail) use ($staffUser) {
        return $mail->hasTo($staffUser->email);
    });
});

test('no pending registration email sent when no admins exist', function () {
    Mail::fake();

    $residentUser = User::factory()->resident()->create();

    Livewire::actingAs($residentUser)
        ->test('pages::complete-profile')
        ->set('first_name', 'Juan')
        ->set('last_name', 'dela Cruz')
        ->set('birthdate', '1990-01-15')
        ->set('gender', 'male')
        ->set('civil_status', 'single')
        ->set('contact_number', '09171234567')
        ->set('address', '123 Rizal Street')
        ->call('submitProfile');

    Mail::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Pending-approval page auto-redirects if resident is already approved
|--------------------------------------------------------------------------
*/

test('pending-approval page redirects to dashboard when resident is approved', function () {
    $residentUser = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $residentUser->id]); // approved by default

    $this->actingAs($residentUser)
        ->get(route('pending-approval'))
        ->assertRedirect(route('dashboard'));
});

test('pending-approval page shows waiting screen when resident is still pending', function () {
    $residentUser = User::factory()->resident()->create();
    Resident::factory()->pending()->create(['user_id' => $residentUser->id]);

    $this->actingAs($residentUser)
        ->get(route('pending-approval'))
        ->assertOk();
});
