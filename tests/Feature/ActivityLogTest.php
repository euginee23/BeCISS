<?php

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Ana Reyes']);
});

test('approving a registration is logged against the approving staff', function () {
    Mail::fake();

    $residentUser = User::factory()->resident()->create();
    $resident = Resident::factory()->pending()->create(['user_id' => $residentUser->id]);

    Livewire::actingAs($this->admin)
        ->test('pages::residents.index')
        ->call('approveResident', $resident->id);

    $log = ActivityLog::latest('id')->first();

    expect($log->module)->toBe('residents')
        ->and($log->action)->toBe('approved')
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->subject_id)->toBe($resident->id);
});

test('a rejection is still logged after the resident is deleted', function () {
    Mail::fake();

    $residentUser = User::factory()->resident()->create();
    $resident = Resident::factory()->pending()->create([
        'user_id' => $residentUser->id,
        'first_name' => 'Jose',
        'last_name' => 'Rizal',
        'middle_name' => null,
        'suffix' => null,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::residents.index')
        ->set('residentToReject', $resident->id)
        ->set('rejectionReason', 'Missing ID')
        ->call('rejectResident');

    $log = ActivityLog::where('action', 'rejected')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->description)->toContain('Jose Rizal')
        ->and($log->description)->toContain('Missing ID');

    // The subject is gone, but the record survives.
    $this->assertDatabaseMissing('residents', ['id' => $resident->id]);
    expect($log->subject)->toBeNull();
});

test('certificate transitions are each attributed', function () {
    Mail::fake();

    $certificate = Certificate::factory()->create(['status' => 'pending']);

    Livewire::actingAs($this->admin)
        ->test('pages::certificates.show', ['certificate' => $certificate])
        ->call('startProcessing')
        ->call('markReadyForPickup')
        ->set('orNumber', 'OR-9911')
        ->call('completeCertificate');

    $actions = ActivityLog::where('module', 'certificates')->pluck('action')->all();

    expect($actions)->toContain('processing', 'ready', 'completed', 'paid');

    $payment = ActivityLog::where('action', 'paid')->first();
    expect($payment->properties['or_number'])->toBe('OR-9911')
        ->and($payment->user_id)->toBe($this->admin->id);
});

test('rejecting a certificate straight from pending records who did it', function () {
    Mail::fake();

    $certificate = Certificate::factory()->create(['status' => 'pending']);

    Livewire::actingAs($this->admin)
        ->test('pages::certificates.show', ['certificate' => $certificate])
        ->set('rejectionReason', 'Requirements incomplete')
        ->call('rejectCertificate');

    $log = ActivityLog::where('action', 'rejected')->latest('id')->first();

    expect($log->module)->toBe('certificates')
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->description)->toContain('Requirements incomplete');
});

test('appointment cancellation is attributed', function () {
    Mail::fake();

    $appointment = Appointment::factory()->create(['status' => 'scheduled']);

    Livewire::actingAs($this->admin)
        ->test('pages::appointments.show', ['appointment' => $appointment])
        ->set('cancellationReason', 'Resident asked to reschedule')
        ->call('cancelAppointment');

    $log = ActivityLog::where('module', 'appointments')->where('action', 'cancelled')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($this->admin->id);

    expect($appointment->fresh()->handled_by)->toBe($this->admin->id);
});

test('blotter completion is attributed', function () {
    $blotter = Blotter::factory()->create(['status' => 'processing']);

    Livewire::actingAs($this->admin)
        ->test('pages::blotters.show', ['blotter' => $blotter])
        ->set('orNumber', 'OR-2211')
        ->call('completeBlotter');

    $actions = ActivityLog::where('module', 'blotters')->pluck('action')->all();

    expect($actions)->toContain('completed', 'paid');
});

test('records expose their own activity timeline', function () {
    Mail::fake();

    $certificate = Certificate::factory()->create(['status' => 'pending']);

    Livewire::actingAs($this->admin)
        ->test('pages::certificates.show', ['certificate' => $certificate])
        ->call('startProcessing');

    expect($certificate->fresh()->activityLogs)->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| Activity log page
|--------------------------------------------------------------------------
*/

test('the activity log page is admin only', function () {
    $this->actingAs($this->admin)->get(route('admin.activity-logs'))->assertOk();

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.activity-logs'))
        ->assertForbidden();
});

test('the activity log page filters by staff and module', function () {
    $other = User::factory()->staff()->create(['name' => 'Ben Cruz']);

    ActivityLog::create([
        'user_id' => $this->admin->id,
        'module' => 'certificates',
        'action' => 'completed',
        'description' => 'Ana completed a certificate.',
    ]);

    ActivityLog::create([
        'user_id' => $other->id,
        'module' => 'blotters',
        'action' => 'completed',
        'description' => 'Ben completed a blotter.',
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.activity-logs')
        ->assertSee('Ana completed a certificate.')
        ->assertSee('Ben completed a blotter.')
        ->set('staff', (string) $this->admin->id)
        ->assertSee('Ana completed a certificate.')
        ->assertDontSee('Ben completed a blotter.')
        ->set('staff', '')
        ->set('module', 'blotters')
        ->assertSee('Ben completed a blotter.')
        ->assertDontSee('Ana completed a certificate.');
});
