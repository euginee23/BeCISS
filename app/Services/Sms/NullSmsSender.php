<?php

namespace App\Services\Sms;

/**
 * Discards every message. Useful in tests, and for switching SMS off in an
 * environment without removing the calls.
 */
class NullSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        //
    }
}
