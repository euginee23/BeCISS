<?php

test('the landing page no longer advertises intelligent scheduling', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Intelligent')
        ->assertDontSee('intelligent scheduling');
});

test('the landing page uses the service scheduling tagline', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Barangay e-Connect & Service Scheduling', false);
});
