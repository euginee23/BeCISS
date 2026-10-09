<?php

use App\Models\CertificateType;

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('homepage shows only active certificate types', function () {
    CertificateType::factory()->inactive()->create(['name' => 'Certificate of Good Moral']);

    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Barangay Clearance')
        ->assertSee('Certificate of Indigency')
        ->assertSee('Certificate of Residency')
        ->assertSee('Barangay Certification')
        ->assertDontSee('Business Permit Clearance')
        ->assertDontSee('Certificate of Good Moral')
        ->assertDontSee('First Time Job Seeker');
});
