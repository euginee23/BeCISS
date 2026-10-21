<?php

use App\Services\Sms\IprogSmsSender;
use Illuminate\Support\Facades\Http;

function iprogSender(string $token = 'test-token', bool $approved = false): IprogSmsSender
{
    return new IprogSmsSender(token: $token, logChannel: null, senderNameApproved: $approved);
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('a send posts the token, number and message to IPROG', function () {
    Http::fake([
        '*/phone_numbers/detect' => Http::response(['data' => ['network' => 'Globe/TM', 'is_smart_tnt' => false]]),
        '*/sms_messages' => Http::response(['status' => 200, 'message_id' => 'iSms-1']),
    ]);

    iprogSender()->send('09171234567', 'Your certificate is ready.');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sms_messages')
        && $request['api_token'] === 'test-token'
        && $request['phone_number'] === '+639171234567'
        && $request['message'] === 'Your certificate is ready.');
});

test('an error in the response body is reported even on HTTP 200', function (array $body) {
    Http::fake([
        '*/phone_numbers/detect' => Http::response(['data' => ['network' => 'Globe/TM', 'is_smart_tnt' => false]]),
        '*/sms_messages' => Http::response($body),
    ]);

    iprogSender()->send('09171234567', 'Hello');
})->with([
    'status 500' => [['status' => 500, 'message' => 'Invalid Token']],
    'status error' => [['status' => 'error', 'message' => 'Failed', 'data' => ['errors' => ['No credits']]]],
])->throws(RuntimeException::class, 'IPROG SMS rejected the message');

test('a missing token is refused before calling IPROG', function () {
    iprogSender(token: '')->send('09171234567', 'Hello');
})->throws(RuntimeException::class, 'IPROGSMS_TOKEN is not set');

test('Smart numbers are refused while no sender name is approved', function () {
    Http::fake([
        '*/phone_numbers/detect' => Http::response(['data' => ['network' => 'Smart/TNT', 'is_smart_tnt' => true]]),
    ]);

    expect(fn () => iprogSender()->send('09181234567', 'Hello'))
        ->toThrow(RuntimeException::class, 'Smart, TNT and Sun');

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/sms_messages'));
});
