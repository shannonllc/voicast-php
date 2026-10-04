<?php

declare(strict_types=1);

namespace Voicast\Resources;

/** Connections to calendars, CRMs and chat (`/v1/connections`). */
final class Connections extends Resource
{
    /**
     * `tenant_id` left out: every connection. `null`: the environment's connections only. A tenant ID: that tenant's.
     *
     * @param array{tenant_id?: string|null} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{data: list<array<string, mixed>>}
     */
    public function list(array $params = [], array $options = []): array
    {
        $query = array_key_exists('tenant_id', $params) ? ['tenant_id' => $params['tenant_id'] ?? ''] : [];
        return $this->http->request('GET', '/v1/connections', true, query: $query, options: self::opts($options));
    }

    /**
     * Connects a connector set up with an API key or a webhook URL (`teams`, `http`).
     * OAuth connectors are connected by the business on the page from `tenants->createConnectLink()`.
     * `secret` is stored encrypted and never returned. Not retried on a 5xx.
     *
     * @param array{connector: string, secret: string, input?: array<string, string>, tenant_id?: string} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function create(array $params, array $options = []): array
    {
        $body = self::objects(self::pick($params, ['connector', 'secret', 'input', 'tenant_id']), ['input']);
        return $this->http->request('POST', '/v1/connections', false, body: $body, hasBody: true, options: self::opts($options));
    }

    /**
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function get(string $connectionId, array $options = []): array
    {
        return $this->http->request('GET', '/v1/connections/' . self::id($connectionId), true, options: self::opts($options));
    }

    /**
     * Changes only the `config` fields given. A connection waiting for setup becomes `active`.
     *
     * @param array{config: array<string, mixed>} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function update(string $connectionId, array $params, array $options = []): array
    {
        $body = self::objects(self::pick($params, ['config']), ['config']);
        return $this->http->request('PATCH', '/v1/connections/' . self::id($connectionId), true, body: $body, hasBody: true, options: self::opts($options));
    }

    /**
     * Reads the choices for the connection's settings from the connected service (calendars, sheets, apps, rooms;
     * empty for http). `$query` is passed as is, such as `['spreadsheet' => ..., 'sheet' => ...]` or `['app' => ...]`.
     *
     * @param array<string, string|null> $query
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function options(string $connectionId, array $query = [], array $options = []): array
    {
        return $this->http->request('GET', '/v1/connections/' . self::id($connectionId) . '/options', true, query: $query, options: self::opts($options));
    }

    /**
     * Disconnects: revokes the tokens at the connected service and deletes the stored secret.
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     */
    public function delete(string $connectionId, array $options = []): void
    {
        $this->http->request('DELETE', '/v1/connections/' . self::id($connectionId), true, options: self::opts($options));
    }
}
