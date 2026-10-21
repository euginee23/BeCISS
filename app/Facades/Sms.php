<?php

namespace App\Facades;

use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void send(string $to, string $message)
 *
 * @see SmsSender
 */
class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SmsSender::class;
    }
}
