<?php

use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-14 10:00:00');

    $this->actingAs(User::factory()->staff()->create());
});

test('the report presets set the date range up to today', function (string $page, string $range, string $from) {
    Livewire::test($page)
        ->call('setRange', $range)
        ->assertSet('from', $from)
        ->assertSet('to', '2026-10-14')
        ->assertSee(['Today', 'This week', 'This month', 'This year']);
})->with([
    'pages::reports.index',
    'pages::reports.collections',
])->with([
    'today' => ['today', '2026-10-14'],
    'this week' => ['week', '2026-10-12'],
    'this month' => ['month', '2026-10-01'],
    'this year' => ['year', '2026-01-01'],
]);

test('the active preset follows the chosen dates', function () {
    $component = Livewire::test('pages::reports.index');

    expect($component->instance()->activeRange())->toBe('month');

    $component->call('setRange', 'year');
    expect($component->instance()->activeRange())->toBe('year');

    $component->set('from', '2026-02-03');
    expect($component->instance()->activeRange())->toBeNull();
});
