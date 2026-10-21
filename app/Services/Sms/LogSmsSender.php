<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Writes messages to a log channel instead of sending them, mirroring
 * Laravel's log mailer. This is the default until a provider is connected.
 */
class LogSmsSender implements SmsSender
{
    /** Characters per GSM-7 segment; longer messages are billed per segment. */
    private const SEGMENT_LENGTH = 160;

    public function __construct(
        private readonly string $channel = 'sms',
        private readonly string $from = 'BeCISS',
        private readonly string $countryCode = '63',
    ) {}

    public function send(string $to, string $message): void
    {
        $normalised = PhoneNumber::toE164($to, $this->countryCode);

        if ($normalised === null) {
            throw new RuntimeException("Cannot send SMS: \"{$to}\" is not a usable phone number.");
        }

        $length = mb_strlen($message);

        Log::channel($this->channel)->info('SMS sent', [
            'driver' => 'log',
            'to' => $normalised,
            'to_raw' => $to,
            'from' => $this->from,
            'message' => $message,
            'length' => $length,
            'segments' => (int) ceil(max($length, 1) / self::SEGMENT_LENGTH),
        ]);
    }
}
