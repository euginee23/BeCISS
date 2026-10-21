<?php

use Illuminate\Support\Facades\Log;

test('the test command writes to the sms log on the log driver', function () {
    config(['sms.default' => 'log']);

    $channel = Mockery::mock();
    $channel->shouldReceive('info')->once()->with('SMS sent', Mockery::on(
        fn (array $context): bool => $context['to'] === '+639171234567' && $context['message'] === 'Hello from the test'
    ));
    Log::shouldReceive('channel')->with('sms')->andReturn($channel);

    $this->artisan('test:sms', ['to' => '09171234567', 'message' => 'Hello from the test'])
        ->expectsOutputToContain('SMS handed off successfully')
        ->assertSuccessful();
});

test('the test command rejects an unusable number', function () {
    $this->artisan('test:sms', ['to' => 'abc'])
        ->expectsOutputToContain('is not a usable phone number')
        ->assertFailed();
});
