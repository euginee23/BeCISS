<?php

use App\Models\BarangayProfile;
use App\Models\Blotter;
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

test('admin can download blotter report', function () {
    $user = User::factory()->admin()->create();
    $blotter = Blotter::factory()->completed()->create();

    $this->actingAs($user)
        ->get(route('blotters.download', $blotter).'?'.http_build_query([
            'format' => 'docx',
            'date_of_issuance' => '2026-04-01',
        ]))
        ->assertSuccessful();
});

test('staff can download blotter report', function () {
    $user = User::factory()->staff()->create();
    $blotter = Blotter::factory()->completed()->create();

    $this->actingAs($user)
        ->get(route('blotters.download', $blotter).'?'.http_build_query([
            'format' => 'docx',
            'date_of_issuance' => '2026-04-01',
        ]))
        ->assertSuccessful();
});

test('resident can download their own blotter report', function () {
    $user = User::factory()->resident()->create();
    $resident = Resident::factory()->create(['user_id' => $user->id]);
    $blotter = Blotter::factory()->completed()->create([
        'resident_id' => $resident->id,
    ]);

    $this->actingAs($user)
        ->get(route('blotters.download', $blotter).'?'.http_build_query([
            'format' => 'docx',
            'date_of_issuance' => '2026-04-01',
        ]))
        ->assertSuccessful();
});

test('resident cannot download another residents blotter', function () {
    $user = User::factory()->resident()->create();
    Resident::factory()->create(['user_id' => $user->id]);
    $blotter = Blotter::factory()->completed()->create();

    $this->actingAs($user)
        ->get(route('blotters.download', $blotter).'?'.http_build_query([
            'format' => 'docx',
            'date_of_issuance' => '2026-04-01',
        ]))
        ->assertForbidden();
});

test('unauthenticated user cannot download blotter', function () {
    $blotter = Blotter::factory()->completed()->create();

    $this->get(route('blotters.download', $blotter).'?'.http_build_query([
        'format' => 'docx',
        'date_of_issuance' => '2026-04-01',
    ]))
        ->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('date of issuance is required for blotter download', function () {
    $user = User::factory()->admin()->create();
    $blotter = Blotter::factory()->completed()->create();

    $this->actingAs($user)
        ->get(route('blotters.download', $blotter).'?'.http_build_query([
            'format' => 'docx',
        ]))
        ->assertInvalid(['date_of_issuance']);
});

test('format is required and must be valid for blotter download', function () {
    $user = User::factory()->admin()->create();
    $blotter = Blotter::factory()->completed()->create();

    $this->actingAs($user)
        ->get(route('blotters.download', $blotter).'?'.http_build_query([
            'date_of_issuance' => '2026-04-01',
        ]))
        ->assertInvalid(['format']);

    $this->actingAs($user)
        ->get(route('blotters.download', $blotter).'?'.http_build_query([
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

test('downloaded blotter docx has correct content type', function () {
    $user = User::factory()->admin()->create();
    $blotter = Blotter::factory()->completed()->create();

    $response = $this->actingAs($user)
        ->get(route('blotters.download', $blotter).'?'.http_build_query([
            'format' => 'docx',
            'date_of_issuance' => '2026-04-01',
        ]));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $response->assertHeader('content-disposition');
});

test('downloaded blotter filename includes blotter number', function () {
    $user = User::factory()->admin()->create();
    $blotter = Blotter::factory()->completed()->create();

    $response = $this->actingAs($user)
        ->get(route('blotters.download', $blotter).'?'.http_build_query([
            'format' => 'docx',
            'date_of_issuance' => '2026-04-01',
        ]));

    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain($blotter->blotter_number);
});

test('blotter docx fills correct placeholders', function () {
    $user = User::factory()->admin()->create();
    $resident = Resident::factory()->create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'address' => 'Zone 2, Main Street',
        'purok' => '5',
    ]);
    $blotter = Blotter::factory()->completed()->create([
        'resident_id' => $resident->id,
        'incident_type' => 'theft',
        'incident_datetime' => '2026-03-15 14:30:00',
        'owner_name' => 'Pedro Reyes',
        'or_number' => 'OR-1234-5678',
        'fee' => 50.00,
    ]);

    $response = $this->actingAs($user)
        ->get(route('blotters.download', $blotter).'?'.http_build_query([
            'format' => 'docx',
            'date_of_issuance' => '2026-04-01',
        ]));

    $response->assertSuccessful();

    $tempPath = sys_get_temp_dir().'/blotter_test_'.uniqid().'.docx';
    file_put_contents($tempPath, $response->streamedContent());

    $zip = new ZipArchive;
    $zip->open($tempPath);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    @unlink($tempPath);

    expect($xml)
        ->toContain('Maria')
        ->toContain('Santos')
        ->toContain('Theft')
        ->toContain('Pedro Reyes')
        ->toContain('April 1, 2026');
});
