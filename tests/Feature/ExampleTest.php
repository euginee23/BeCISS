<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('homepage shows only supported certificate cards', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Barangay Clearance')
        ->assertSee('Barangay Indigency')
        ->assertSee('Certificate of Residency')
        ->assertSee('Barangay Certification')
        ->assertDontSee('Business Permit Clearance')
        ->assertDontSee('Certificate of Good Moral')
        ->assertDontSee('First Time Job Seeker');
});
