<?php

use App\Services\Sms\PhoneNumber;

test('local, international and spaced numbers normalise to E.164', function (string $input) {
    expect(PhoneNumber::toE164($input))->toBe('+639171234567');
})->with([
    '09171234567',
    '9171234567',
    '+639171234567',
    '+63 917 123 4567',
    '0917-123-4567 ',
]);

test('blank input has no number', function (?string $input) {
    expect(PhoneNumber::toE164($input))->toBeNull();
})->with([null, '', '   ', '000']);
