<?php

declare(strict_types=1);

namespace Voicast;

/** The types of webhooks sent by voicast (`WebhookEvent::$type`). */
final class EventType
{
    public const CALL_STARTED = 'call.started';
    public const CALL_HANDOFF_REQUESTED = 'call.handoff_requested';
    public const CALL_ENDED = 'call.ended';
    public const CALL_TRANSCRIPT_READY = 'call.transcript_ready';
    public const CALL_SUMMARY_READY = 'call.summary_ready';
    public const CONNECTION_NEEDS_REAUTH = 'connection.needs_reauth';
    public const BILLING_BALANCE_LOW = 'billing.balance_low';
    public const BILLING_BALANCE_DEPLETED = 'billing.balance_depleted';
    public const BILLING_RECHARGE_FAILED = 'billing.recharge_failed';
    public const BILLING_CAP_REACHED = 'billing.cap_reached';

    /** @return list<string> */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
