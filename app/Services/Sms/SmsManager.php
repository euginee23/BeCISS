<?php

namespace App\Services\Sms;

use InvalidArgumentException;

/**
 * Builds SMS senders by driver name.
 *
 * Not a singleton and it caches nothing: tests change the sms config and
 * then forget the SmsSender instance, and a manager holding a built driver
 * would hand them a stale one.
 */
class SmsManager
{
    /**
     * @param  string|null  $name  A driver name from config('sms.drivers'), or
     *                             null for the configured default.
     */
    public function driver(?string $name = null): SmsSender
    {
        $name = (string) ($name ?? config('sms.default'));

        /**
         * .env reads a bare `null` as an empty value, so SMS_DRIVER=null
         * arrives here blank rather than as a driver name.
         */
        if ($name === '') {
            throw new InvalidArgumentException(
                'No SMS driver configured. Set SMS_DRIVER=iprogsms to send, SMS_DRIVER=log to record '
                .'messages without sending, or SMS_DRIVER=none to disable sending. '
                .'Note that SMS_DRIVER=null is read as an empty value.'
            );
        }

        $config = config("sms.drivers.{$name}", []);

        return match ($config['driver'] ?? $name) {
            'iprogsms' => new IprogSmsSender(
                token: (string) ($config['token'] ?? ''),
                baseUrl: (string) (($config['base_url'] ?? '') ?: IprogSmsSender::BASE_URL),
                provider: is_numeric($config['provider'] ?? null) ? (int) $config['provider'] : null,
                countryCode: (string) config('sms.country_code', '63'),
                timeout: (int) ($config['timeout'] ?? 15),
                logChannel: array_key_exists('log_channel', $config) ? $config['log_channel'] : 'sms',
                senderNameApproved: (bool) ($config['sender_name_approved'] ?? false),
                networkCacheTtl: (int) ($config['network_cache_ttl'] ?? 604800),
            ),
            'log' => new LogSmsSender(
                channel: $config['channel'] ?? 'sms',
                from: (string) config('sms.from'),
                countryCode: (string) config('sms.country_code', '63'),
            ),
            'none' => new NullSmsSender,
            default => throw new InvalidArgumentException("Unsupported SMS driver [{$name}]."),
        };
    }

    /**
     * The network detector for a driver, or null when that driver cannot
     * detect (log and none).
     */
    public function detector(?string $name = null): ?NetworkDetector
    {
        $sender = $this->driver($name);

        return $sender instanceof NetworkDetector ? $sender : null;
    }
}
