<?php

namespace App\Services\Sms;

interface SmsSender
{
    /**
     * Send a text message to a single recipient.
     *
     * @param  string  $to  Recipient number in any format; normalised to E.164 by the sender.
     *
     * @throws \RuntimeException When the message cannot be handed off to the provider.
     */
    public function send(string $to, string $message): void;
}
