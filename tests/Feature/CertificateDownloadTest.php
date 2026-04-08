<?php

use App\Models\BarangayProfile;
use App\Models\Certificate;
use App\Models\Resident;
use App\Models\User;

beforeEach(function () {
    BarangayProfile::factory()->create();
});

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

test('admin can download completed certificate of residency', function () {
    $user = User::factory()->admin()->create();
    $certificate = Certificate::factory()->residency()->completed()->create();

    $this->actingAs($user)
        ->get(route('certificates.download', $certificate).'?'.http_build_query([
            'format' => 'docx',
            'date_of_issuance' => '2026-04-01',
        ]))
        ->assertSuccessful();
});

test('staff can download completed certificate of residency', function () {
    $user = User::factory()->staff()->create();
    $certificate = Certificate::factory()->residency()->completed()->create();

    $this->actingAs($user)
        ->get(route('certificates.download', $certificate).'?'.http_build_query([
            'format' => 'docx',
            'date_of_issuance' => '2026-04-01',
        ]))
        ->assertSuccessful();
});

test('resident can download their own completed certificate', function () {
    $user = User::factory()->resident()->create();
    $resident = Resident::factory()->create(['user_id' => $user->id]);
    $certificate = Certificate::factory()->residency()->completed()->create([
        'resident_id' => $resident->id,
    ]);

    $this->actingAs($user)
        ->get(route('certificates.download', $certificate).'?'.http_build_query([
            'format' => 'docx',
            'date_of_issuance' => '2026-04-01',
        ]))
        ->assertSuccessful();
});

test('resident cannot download another residents certificate', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id]);
    $certificate = Certificate::factory()->residency()->completed()->create();

    $this->actingAs($user)
        ->get(route('certificates.download', $certificate).'?'.http_build_query([
            'format' => 'docx',
            'date_of_issuance' => '2026-04-01',
        ]))
        ->assertForbidden();
});

test('unauthenticated user cannot download certificate', function () {
    $certificate = Certificate::factory()->residency()->completed()->create();

    $this->get(route('certificates.download', $certificate).'?'.http_build_query([
        'format' => 'docx',
        'date_of_issuance' => '2026-04-01',
    ]))
        ->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| Status & Type Guards
|--------------------------------------------------------------------------
*/

test('can download certificate regardless of status', function () {
    $user = User::factory()->admin()->create();

    $statuses = ['pending', 'processing', 'ready_for_pickup', 'completed'];

    foreach ($statuses as $status) {
        $certificate = Certificate::factory()->residency()->create(['status' => $status]);

        $this->actingAs($user)
            ->get(route('certificates.download', $certificate).'?'.http_build_query([
                'format' => 'docx',
                'date_of_issuance' => '2026-04-01',
            ]))
            ->assertSuccessful();
    }
});

test('cannot download certificate type without template', function () {
    $user = User::factory()->admin()->create();
    $certificate = Certificate::factory()->barangayClearance()->completed()->create();

    $this->actingAs($user)
        ->get(route('certificates.download', $certificate).'?'.http_build_query([
            'format' => 'docx',
            'date_of_issuance' => '2026-04-01',
        ]))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('date of issuance is required', function () {
    $user = User::factory()->admin()->create();
    $certificate = Certificate::factory()->residency()->completed()->create();

    $this->actingAs($user)
        ->get(route('certificates.download', $certificate).'?'.http_build_query([
            'format' => 'docx',
        ]))
        ->assertInvalid(['date_of_issuance']);
});

test('format is required and must be valid', function () {
    $user = User::factory()->admin()->create();
    $certificate = Certificate::factory()->residency()->completed()->create();

    $this->actingAs($user)
        ->get(route('certificates.download', $certificate).'?'.http_build_query([
            'date_of_issuance' => '2026-04-01',
        ]))
        ->assertInvalid(['format']);

    $this->actingAs($user)
        ->get(route('certificates.download', $certificate).'?'.http_build_query([
            'format' => 'xlsx',
            'date_of_issuance' => '2026-04-01',
        ]))
        ->assertInvalid(['format']);
});

/*
|--------------------------------------------------------------------------
| Document Generation
|--------------------------------------------------------------------------
*/

test('downloaded docx has correct content type', function () {
    $user = User::factory()->admin()->create();
    $certificate = Certificate::factory()->residency()->completed()->create();

    $response = $this->actingAs($user)
        ->get(route('certificates.download', $certificate).'?'.http_build_query([
            'format' => 'docx',
            'date_of_issuance' => '2026-04-01',
        ]));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $response->assertHeader('content-disposition');
});

test('downloaded docx filename includes certificate number', function () {
    $user = User::factory()->admin()->create();
    $certificate = Certificate::factory()->residency()->completed()->create();

    $response = $this->actingAs($user)
        ->get(route('certificates.download', $certificate).'?'.http_build_query([
            'format' => 'docx',
            'date_of_issuance' => '2026-04-01',
        ]));

    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain($certificate->certificate_number);
});
