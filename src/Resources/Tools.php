<?php

declare(strict_types=1);

namespace Voicast\Resources;

/** Agent tools: the signing secret for in-call tool requests (`/v1/tools/secret`, one per environment), and tool tests. */
final class Tools extends Resource
{
    /**
     * Gets the signing secret (`whsec_...`) to pass to `Voicast\verifyToolCall()`.
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{secret: string, previous_secret_expires_at: int|null}
     */
    public function getSecret(array $options = []): array
    {
        return $this->http->request('GET', '/v1/tools/secret', true, options: self::opts($options));
    }

    /**
     * Issues a new signing secret. For 24 hours requests carry signatures with both secrets, so switch your server to
     * the new one within that time. Not retried on a 5xx or a connection error.
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{secret: string, previous_secret_expires_at: int|null}
     */
    public function rotateSecret(array $options = []): array
    {
        return $this->http->request('POST', '/v1/tools/secret/rotate', false, options: self::opts($options));
    }

    /**
     * Calls an agent tool once, as during a call, and returns what it answered. Not billed and not recorded as a call.
     * `tool` is the name of a tool saved in `agent_id`'s agent, or a tool definition (before saving it; with
     * `agent_id`, the agent's stored API key is used when `auth.value` is left out). Signed requests carry
     * `test: true`, `call.channel` `test` and a `try_...` call ID; `call.tenant` is `null` without `tenant_id`.
     * Up to 30 per minute per environment. A failing tool is still a result with `ok` false (`error`: `unauthorized`,
     * `not_found`, `upstream_error`, `timeout`, `invalid_arguments`, `missing_api_key` and so on).
     * Not retried on a 5xx or a connection error (it calls your API).
     *
     * @param array{tool: string|array<string, mixed>, agent_id?: string, arguments?: array<string, mixed>, tenant_id?: string} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{tool: string, ok: bool, result?: mixed, error?: string, message?: string, status: int|null, body: string|null, truncated: bool, duration_ms: int}
     */
    public function try(array $params, array $options = []): array
    {
        $body = self::objects(self::pick($params, ['agent_id', 'tool', 'arguments', 'tenant_id']), ['arguments']);
        return $this->http->request('POST', '/v1/tools/try', false, body: $body, hasBody: true, options: self::opts($options));
    }
}
