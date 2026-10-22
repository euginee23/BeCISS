<?php

use App\Mail\ResidentApproved;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();

    $this->staff = User::factory()->staff(['residents'])->create();
});

test('accounts still finishing sign-up are listed under pending registrations', function () {
    User::factory()->resident()->unverified()->create(['name' => 'Unverified Applicant']);
    User::factory()->resident()->create(['name' => 'Profile Applicant']);
    User::factory()->resident()->create(['name' => 'Done Applicant'])
        ->resident()->save(Resident::factory()->make(['status' => 'approved']));

    Livewire::actingAs($this->staff)
        ->test('pages::residents.index')
        ->set('tab', 'pending')
        ->assertSee('Still finishing sign-up')
        ->assertSee('Unverified Applicant')
        ->assertSee('Email not verified')
        ->assertSee('Profile Applicant')
        ->assertSee('Filling in profile')
        ->assertDontSee('Done Applicant');
});

test('approving a registration confirms where the resident went', function () {
    $user = User::factory()->resident()->create();
    $resident = Resident::factory()->pending()->create(['user_id' => $user->id, 'first_name' => 'Lyzamae', 'last_name' => 'Albellar']);

    Livewire::actingAs($this->staff)
        ->test('pages::residents.index')
        ->set('tab', 'pending')
        ->assertSee('Lyzamae')
        ->call('approveResident', $resident->id)
        ->assertSee('was approved and is now in the Registry.');

    expect($resident->refresh()->status)->toBe('approved');
    Mail::assertSent(ResidentApproved::class, 1);
});

test('approving an already approved registration explains instead of emailing again', function () {
    $user = User::factory()->resident()->create();
    $resident = Resident::factory()->pending()->create(['user_id' => $user->id]);

    $page = Livewire::actingAs($this->staff)
        ->test('pages::residents.index')
        ->set('tab', 'pending');

    $resident->update(['status' => 'approved', 'approved_at' => now()]);

    $page->call('approveResident', $resident->id)
        ->assertSee('is already approved and is in the Registry.');

    Mail::assertNothingSent();
});
