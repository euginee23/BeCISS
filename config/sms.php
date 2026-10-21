<?php

use App\Services\Sms\IprogSmsSender;

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    |
    | Set SMS_DRIVER=iprogsms to send for real (costs credits). "log" writes
    | each message to storage/logs/sms.log instead, which keeps local and CI
    | runs free. "none" discards messages.
    |
    | Supported: "iprogsms", "log", "none"
    |
    */

    'default' => env('SMS_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Sender Name
    |--------------------------------------------------------------------------
    |
    | Recorded on logged messages only. IPROG takes no sender field; the name
    | recipients see is set on the IPROG account.
    |
    */

    'from' => env('SMS_FROM', env('APP_NAME', 'BeCISS')),

    /*
    |--------------------------------------------------------------------------
    | Default Country Code
    |--------------------------------------------------------------------------
    |
    | Used to normalise local numbers ("0917...") to E.164. 63 = Philippines.
    |
    */

    'country_code' => env('SMS_COUNTRY_CODE', '63'),

    'drivers' => [

        'iprogsms' => [
            'driver' => 'iprogsms',

            'token' => env('IPROGSMS_TOKEN'),

            'base_url' => env('IPROGSMS_URL', IprogSmsSender::BASE_URL),

            /**
             * Upstream telco route (0, 1 or 2). Unset lets IPROG use its default.
             */
            'provider' => env('IPROGSMS_PROVIDER'),

            'timeout' => env('IPROGSMS_TIMEOUT', 15),

            /**
             * IPROG reaches Globe, TM and DITO with its shared sender name.
             * Smart, TNT and Sun only accept a custom sender name IPROG has
             * approved; while this is false those numbers are refused rather
             * than charged for a message that never arrives.
             */
            'sender_name_approved' => env('IPROGSMS_SENDER_NAME_APPROVED', false),

            /**
             * IPROG prepends this name and a space to every message, and it
             * counts towards the 160 characters of a billed text.
             */
            'sender_name' => env('IPROGSMS_SENDER_NAME', ''),

            /**
             * How long a number's network lookup is cached, in seconds.
             */
            'network_cache_ttl' => env('IPROGSMS_NETWORK_CACHE_TTL', 604800),

            'log_channel' => env('SMS_LOG_CHANNEL', 'sms'),
        ],

        'log' => [
            'driver' => 'log',
            'channel' => env('SMS_LOG_CHANNEL', 'sms'),
        ],

        'none' => [
            'driver' => 'none',
        ],

        'null' => [
            'driver' => 'none',
        ],

    ],

];
