<?php

declare(strict_types=1);

namespace Voicast;

/**
 * Verifies a webhook sent by voicast (`call.ended`, `call.summary_ready`, `billing.*` and others) and returns the event.
 *
 * @param string $payload The raw request body, exactly as received (not a decoded array).
 * @param array<string, string|list<string>>|object $headers `$_SERVER`, an array of name => value or a PSR-7 request.
 * @param string|list<string> $secret The endpoint's signing secret (`whsec_...`) from the webhook portal, or a list
 *        while you switch to a new one.
 * @param int $tolerance Allowed clock difference in seconds, both ways. Default 300.
 * @throws Exception\WebhookVerificationException when verification fails.
 */
function verifyWebhook(string $payload, array|object $headers, string|array $secret, int $tolerance = 300): WebhookEvent
{
    return (new Webhook($secret, $tolerance))->verifyEvent($payload, $headers);
}

/**
 * Verifies an in-call tool request (`config.tools[].url`) and returns it.
 *
 * @param string $payload The raw request body, exactly as received (not a decoded array).
 * @param array<string, string|list<string>>|object $headers `$_SERVER`, an array of name => value or a PSR-7 request.
 * @param string|list<string> $secret The environment's tools signing secret (`whsec_...`) from the dashboard, or a list
 *        while you switch to a new one.
 * @throws Exception\WebhookVerificationException when verification fails.
 */
function verifyToolCall(string $payload, array|object $headers, string|array $secret, int $tolerance = 300): ToolCallRequest
{
    return (new Webhook($secret, $tolerance))->verifyToolCall($payload, $headers);
}
