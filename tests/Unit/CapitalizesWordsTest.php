<?php

use App\Concerns\CapitalizesWords;

$capitalize = new class
{
    use CapitalizesWords;
};

test('it capitalizes the first letter of every word', function () use ($capitalize) {
    expect($capitalize::capitalizeWords('juan dela cruz'))->toBe('Juan Dela Cruz');
});

test('it capitalizes after a hyphen, apostrophe and period', function () use ($capitalize) {
    expect($capitalize::capitalizeWords('anna-marie o\'brien jr.'))->toBe('Anna-Marie O\'Brien Jr.');
});

test('it leaves the rest of each word untouched', function () use ($capitalize) {
    expect($capitalize::capitalizeWords('McDonald dela Cruz III'))->toBe('McDonald Dela Cruz III');
});

test('it trims surrounding whitespace', function () use ($capitalize) {
    expect($capitalize::capitalizeWords('  maria  '))->toBe('Maria');
});

test('it passes null and empty strings through', function () use ($capitalize) {
    expect($capitalize::capitalizeWords(null))->toBeNull()
        ->and($capitalize::capitalizeWords('   '))->toBe('');
});
