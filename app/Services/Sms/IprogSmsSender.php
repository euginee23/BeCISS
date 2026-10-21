<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sends messages through IPROG SMS (https://sms.iprogtech.com).
 *
 * The account exposes one credential — an API token — and no sender ID, so
 * `sms.from` is unused here. Custom sender names are arranged with IPROG and
 * applied on their side.
 *
 * Network coverage is not uniform. IPROG reaches Globe, TM and DITO with its
 * shared sender name, but Smart, TNT and Sun only accept traffic under a
 * custom sender name that IPROG has had approved. Without one, those numbers
 * are unreachable — roughly 40% of the Philippine market — so this class
 * refuses them up front rather than spending a credit on a message that will
 * not arrive.
 */
class IprogSmsSender implements NetworkDetector, SmsSender
{
    /** Root of the IPROG SMS v1 API. */
    public const BASE_URL = 'https://sms.iprogtech.com/api/v1';

    /** Networks IPROG reaches with its shared sender name. */
    public const NETWORK_GLOBE = 'Globe/TM';

    public const NETWORK_DITO = 'DITO';

    /** Needs a custom sender name approved by IPROG. Covers Sun as well. */
    public const NETWORK_SMART = 'Smart/TNT';

    /**
     * A valid Philippine number whose prefix IPROG's table does not list.
     *
     * Not a delivery problem: 0952 comes back this way and is a Globe number
     * that sends normally. It means "we have no answer", so callers treat it
     * as an ordinary number rather than reporting it to staff.
     */
    public const NETWORK_UNKNOWN = 'Unknown Network';

    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl = self::BASE_URL,
        private readonly ?int $provider = null,
        private readonly string $countryCode = '63',
        private readonly int $timeout = 15,
        private readonly ?string $logChannel = 'sms',
        /** True once IPROG has approved a custom sender name for this account. */
        private readonly bool $senderNameApproved = false,
        /** Numbers can be ported between networks, so detection is not cached forever. */
        private readonly int $networkCacheTtl = 604800,
    ) {}

    public function send(string $to, string $message): void
    {
        if (trim($this->token) === '') {
            throw new RuntimeException('Cannot send SMS: IPROGSMS_TOKEN is not set.');
        }

        $normalised = PhoneNumber::toE164($to, $this->countryCode);

        if ($normalised === null) {
            throw new RuntimeException("Cannot send SMS: \"{$to}\" is not a usable phone number.");
        }

        $this->guardNetworkIsReachable($normalised);

        $payload = [
            'api_token' => $this->token,
            // IPROG accepts "09XXXXXXXXX" or "+639XXXXXXXXX"; E.164 is the
            // latter, so the normalised number goes over the wire unchanged.
            'phone_number' => $normalised,
            'message' => $message,
        ];

        if ($this->provider !== null) {
            $payload['sms_provider'] = $this->provider;
        }

        try {
            // Deliberately no retry: a send costs credits and carries no
            // idempotency key, so a request that timed out *after* IPROG
            // accepted it would be delivered twice.
            $response = Http::asForm()
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/sms_messages", $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Could not reach IPROG SMS: {$e->getMessage()}", previous: $e);
        }

        $body = $response->json();

        if (! $response->successful() || ! is_array($body)) {
            throw new RuntimeException(
                "IPROG SMS returned HTTP {$response->status()}: ".Str::limit($response->body(), 200)
            );
        }

        // IPROG answers HTTP 200 even when a send fails, so the body's "status"
        // is the real outcome: 200 on success, otherwise 500 or "error".
        if ((int) ($body['status'] ?? 0) !== 200) {
            throw new RuntimeException('IPROG SMS rejected the message: '.self::describeError($body));
        }

        if ($this->logChannel !== null) {
            // The recipient and provider reference are kept as an audit trail;
            // the message body is not, so resident text stays out of the logs
            // now that sends are real.
            Log::channel($this->logChannel)->info('SMS sent', [
                'driver' => 'iprogsms',
                'to' => $normalised,
                'to_raw' => $to,
                'message_id' => $body['message_id'] ?? null,
                'length' => mb_strlen($message),
            ]);
        }
    }

    /**
     * Refuse a network this account cannot actually reach.
     *
     * Fails open: when detection is unavailable the send proceeds, because a
     * transient lookup failure blocking every message would be worse than
     * occasionally spending a credit on an undeliverable one.
     *
     * @throws RuntimeException When the number is on Smart/TNT/Sun and no
     *                          custom sender name has been approved.
     */
    private function guardNetworkIsReachable(string $normalised): void
    {
        if ($this->senderNameApproved) {
            return;
        }

        $detected = $this->detectNetwork($normalised);

        if (($detected['is_smart_tnt'] ?? false) === true) {
            throw new RuntimeException(
                'Smart, TNT and Sun numbers cannot be reached on this account. IPROG only delivers to '
                .'those networks under a custom sender name it has approved, so this message would be '
                .'charged but never arrive. Contact the resident another way, or ask IPROG to approve a sender name.'
            );
        }
    }

    /**
     * Which network a number is on, per IPROG's own lookup.
     *
     * Their answer is the one that matters, since it decides what they will
     * deliver — a local prefix table would drift and would not account for
     * numbers ported between networks.
     *
     * @return array{network: string, is_smart_tnt: bool}|null Null when the
     *                                                         lookup is unavailable.
     */
    public function detectNetwork(string $phone): ?array
    {
        if (trim($this->token) === '') {
            return null;
        }

        $normalised = PhoneNumber::toE164($phone, $this->countryCode);

        if ($normalised === null) {
            return null;
        }

        // Cached because the composer asks on every open, and because a
        // number's network only changes when it is ported.
        $cached = Cache::get($this->networkCacheKey($normalised));

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/phone_numbers/detect", [
                    'api_token' => $this->token,
                    'phone_number' => $normalised,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        $network = $response->json('data.network');

        // "Invalid Format" means the number never reached their lookup, so it
        // says nothing about the network and is not worth caching.
        if (! is_string($network) || $network === '' || $network === 'Invalid Format') {
            return null;
        }

        $detected = [
            'network' => $network,
            'is_smart_tnt' => (bool) $response->json('data.is_smart_tnt'),
        ];

        Cache::put($this->networkCacheKey($normalised), $detected, $this->networkCacheTtl);

        return $detected;
    }

    private function networkCacheKey(string $normalised): string
    {
        return 'iprogsms:network:'.$normalised;
    }

    /**
     * Current account balance in credits, or null when it cannot be read.
     *
     * Reported rather than thrown: this is a pre-flight courtesy for the
     * `test:sms` command, and an unreadable balance should not block a send.
     */
    public function credits(): ?float
    {
        if (trim($this->token) === '') {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/account/sms_credits", ['api_token' => $this->token]);
        } catch (ConnectionException) {
            return null;
        }

        $balance = $response->json('data.load_balance');

        return is_numeric($balance) ? (float) $balance : null;
    }

    /**
     * Flatten IPROG's error shapes into one sentence.
     *
     * Failures arrive either as {"status": 500, "message": "Invalid Token"} or
     * as {"status": "error", "message": ..., "data": {"errors": [...]}}.
     *
     * @param  array<string, mixed>  $body
     */
    private static function describeError(array $body): string
    {
        $message = is_string($body['message'] ?? null) && $body['message'] !== ''
            ? $body['message']
            : 'no reason given (status '.json_encode($body['status'] ?? null).').';

        $errors = $body['data']['errors'] ?? null;

        if (is_array($errors) && $errors !== []) {
            $message .= ' ('.implode('; ', array_map('strval', $errors)).')';
        }

        return $message;
    }
}
