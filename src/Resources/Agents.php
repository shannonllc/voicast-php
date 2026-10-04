<?php

declare(strict_types=1);

namespace Voicast\Resources;

/** Agents: what the AI says and asks (`/v1/agents`). Every update adds a new version. */
final class Agents extends Resource
{
    /**
     * Creates an agent. Not retried on a 5xx or a connection error (it could create two).
     *
     * @param array{name: string, config: array<string, mixed>} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function create(array $params, array $options = []): array
    {
        return $this->http->request('POST', '/v1/agents', false, body: self::pick($params, ['name', 'config']), hasBody: true, options: self::opts($options));
    }

    /**
     * Lists the agents of the environment, without `config`.
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{data: list<array<string, mixed>>}
     */
    public function list(array $options = []): array
    {
        return $this->http->request('GET', '/v1/agents', true, options: self::opts($options));
    }

    /**
     * Gets an agent with its `config`. `version` defaults to the current version.
     *
     * @param array{version?: int} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function get(string $agentId, array $params = [], array $options = []): array
    {
        return $this->http->request('GET', '/v1/agents/' . self::id($agentId), true, query: ['version' => $params['version'] ?? null], options: self::opts($options));
    }

    /**
     * Saves a new version. Calls in progress keep the version they started with.
     * Not retried on a 5xx or a connection error (each request adds a version).
     *
     * @param array{name?: string, config: array<string, mixed>} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function update(string $agentId, array $params, array $options = []): array
    {
        return $this->http->request('PUT', '/v1/agents/' . self::id($agentId), false, body: self::pick($params, ['name', 'config']), hasBody: true, options: self::opts($options));
    }

    /**
     * Lists the versions of an agent, newest first.
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{data: list<array{version: int, created_at: int}>}
     */
    public function versions(string $agentId, array $options = []): array
    {
        return $this->http->request('GET', '/v1/agents/' . self::id($agentId) . '/versions', true, options: self::opts($options));
    }

    /**
     * Deletes an agent. Throws `ConflictException` (`in_use`) while a tenant uses it.
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     */
    public function delete(string $agentId, array $options = []): void
    {
        $this->http->request('DELETE', '/v1/agents/' . self::id($agentId), true, options: self::opts($options));
    }
}
