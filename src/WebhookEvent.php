<?php

declare(strict_types=1);

namespace Voicast;

/**
 * A webhook sent by voicast (`{ type, timestamp, data }`). Check `type` (the `EventType` constants) to know the shape
 * of `data`:
 *
 * | type | data |
 * |---|---|
 * | `call.started` | `{ call_id, provider_call_id, from, started_at, tenant: { id, external_id } }` |
 * | `call.handoff_requested` | `{ call_id, provider_call_id, reason, fields, tenant }` |
 * | `call.ended` | the call (as `calls->get()`) and `tenant` |
 * | `call.transcript_ready` | `{ call_id, provider_call_id, turns: [{ role, text }], tenant }` |
 * | `call.summary_ready` | `{ call_id, provider_call_id, summary, category, fields, tenant }` |
 * | `connection.needs_reauth` | `{ connection_id, connector, label, status, tenant }` (`tenant` is null for an environment connection) |
 * | `billing.balance_low` | `{ organization_id, balance_yen, threshold_yen }` |
 * | `billing.balance_depleted` | `{ organization_id, balance_yen, call_id }` |
 * | `billing.recharge_failed` | `{ organization_id, invoice_id, amount_yen, attempt, max_attempts, auto_recharge_disabled, decline_code, balance_yen }` |
 * | `billing.cap_reached` | `{ organization_id, kind: 'auto_recharge', month, spending_cap_yen, recharged_yen, amount_yen, balance_yen }` |
 */
final class WebhookEvent
{
    /** The event type, such as `call.ended`. */
    public readonly string $type;
    /** ISO 8601. */
    public readonly ?string $timestamp;
    /** @var mixed The event's data (associative arrays). */
    public readonly mixed $data;
    /** @var array<string, mixed> The whole verified body. */
    public readonly array $raw;

    /** @param array<string, mixed> $body A verified body with `type` and `data`. */
    public function __construct(array $body)
    {
        $this->type = (string) $body['type'];
        $this->timestamp = isset($body['timestamp']) && is_string($body['timestamp']) ? $body['timestamp'] : null;
        $this->data = $body['data'];
        $this->raw = $body;
    }

    /** The tenant the event belongs to (`data['tenant']`, with `id` and `external_id`), or null (billing events). */
    public function tenant(): ?array
    {
        $t = is_array($this->data) ? ($this->data['tenant'] ?? null) : null;
        return is_array($t) ? $t : null;
    }
}
