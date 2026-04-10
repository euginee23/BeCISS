<?php

use App\Models\User;

test('admin can view appearance settings page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('appearance.edit'))
        ->assertSuccessful()
        ->assertSee('Appearance settings')
        ->assertSee("\$flux.appearance = 'light'", false)
        ->assertSee("\$flux.appearance = 'dark'", false)
        ->assertSee("\$flux.appearance = 'system'", false);
});

test('staff can view appearance settings page', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('appearance.edit'))
        ->assertSuccessful()
        ->assertSee('Appearance settings');
});
