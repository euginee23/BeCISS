<?php

namespace App\Services\Sms;

/**
 * A provider that can say which network a number sits on.
 *
 * Split out from SmsSender because routing needs to ask one provider where a
 * number lives and then hand the message to a different one. IprogSmsSender
 * already did this; declaring it as an interface is what lets the router
 * depend on the capability rather than on that class.
 */
interface NetworkDetector
{
    /**
     * @return array{network: string, is_smart_tnt: bool}|null Null when the
     *                                                         lookup is unavailable — callers must not read that as
     *                                                         "unreachable", only as "not known".
     */
    public function detectNetwork(string $phone): ?array;
}
