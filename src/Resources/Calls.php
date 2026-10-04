<?php

declare(strict_types=1);

namespace Voicast\Resources;

use Voicast\Page;

/** Calls (`/v1/calls`), newest first. */
final class Calls extends Resource
{
    /**
     * Lists calls, newest first. Returns the first page (`data`, `hasMore`); `foreach` it to walk every call
     * (the next page starts before the last call's `started_at`). `limit` is 1 to 100 (default 50).
     *
     * @param array{tenant_id?: string, before?: int, limit?: int} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return Page<array<string, mixed>>
     */
    public function list(array $params = [], array $options = []): Page
    {
        $fetch = fn (?int $before): array => $this->http->request(
            'GET',
            '/v1/calls',
            true,
            query: ['tenant_id' => $params['tenant_id'] ?? null, 'before' => $before, 'limit' => $params['limit'] ?? null],
            options: self::opts($options),
        );
        return new Page($fetch($params['before'] ?? null), $fetch, static fn (array $call): int => (int) $call['started_at']);
    }

    /**
     * Gets a call. `summary` and `category` are filled in when `call.summary_ready` is sent.
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function get(string $callId, array $options = []): array
    {
        return $this->http->request('GET', '/v1/calls/' . self::id($callId), true, options: self::opts($options));
    }

    /**
     * Gets a call by the provider's call ID: Twilio's CallSid, or the session `id` for Web calls.
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function getByProvider(string $providerCallId, array $options = []): array
    {
        return $this->http->request('GET', '/v1/calls/by-provider/' . self::id($providerCallId), true, options: self::opts($options));
    }

    /**
     * Gets the transcript. Throws `NotFoundException` when the call has none (`has_transcript` is false).
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{call_id: string, turns: list<array{role: string, text: string}>}
     */
    public function transcript(string $callId, array $options = []): array
    {
        return $this->http->request('GET', '/v1/calls/' . self::id($callId) . '/transcript', true, options: self::opts($options));
    }
}
