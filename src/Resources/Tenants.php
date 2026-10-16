<?php

declare(strict_types=1);

namespace Voicast\Resources;

/** Tenants: the businesses that actually take the calls (`/v1/tenants`). */
final class Tenants extends Resource
{
    /**
     * Creates a tenant. The connection secret (`vts_...`) is returned only here.
     * Not retried on a 5xx or a connection error (a retry would get 409 and lose the secret).
     *
     * @param array{external_id: string, name?: string, agent_id?: string} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function create(array $params, array $options = []): array
    {
        return $this->http->request('POST', '/v1/tenants', false, body: self::pick($params, ['external_id', 'name', 'agent_id']), hasBody: true, options: self::opts($options));
    }

    /**
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{data: list<array<string, mixed>>}
     */
    public function list(array $options = []): array
    {
        return $this->http->request('GET', '/v1/tenants', true, options: self::opts($options));
    }

    /**
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function get(string $tenantId, array $options = []): array
    {
        return $this->http->request('GET', '/v1/tenants/' . self::id($tenantId), true, options: self::opts($options));
    }

    /**
     * Changes only the keys given. `'agent_id' => null` removes the tenant's agent.
     *
     * @param array{name?: string, agent_id?: string|null, status?: 'active'|'disabled'} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function update(string $tenantId, array $params, array $options = []): array
    {
        return $this->http->request('PATCH', '/v1/tenants/' . self::id($tenantId), true, body: self::object(self::pick($params, ['name', 'agent_id', 'status'])), hasBody: true, options: self::opts($options));
    }

    /**
     * Issues a new connection secret. The previous one keeps working for 24 hours. Not retried on a 5xx.
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{secret: string, previous_secret_expires_at: int}
     */
    public function rotateSecret(string $tenantId, array $options = []): array
    {
        return $this->http->request('POST', '/v1/tenants/' . self::id($tenantId) . '/secret', false, options: self::opts($options));
    }

    /**
     * Deletes a tenant and revokes its connections. Its calls are kept.
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     */
    public function delete(string $tenantId, array $options = []): void
    {
        $this->http->request('DELETE', '/v1/tenants/' . self::id($tenantId), true, options: self::opts($options));
    }

    /**
     * Lists the tenant's connections.
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{data: list<array<string, mixed>>}
     */
    public function listConnections(string $tenantId, array $options = []): array
    {
        return $this->http->request('GET', '/v1/tenants/' . self::id($tenantId) . '/connections', true, options: self::opts($options));
    }

    /**
     * Creates a link (valid for 15 minutes) to the page where the business connects its calendar, CRM or chat.
     * Open it in a new tab, or in an iframe on `frame_origin`.
     *
     * @param array{connectors?: list<string>, frame_origin?: string} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{url: string, expires_at: int}
     */
    public function createConnectLink(string $tenantId, array $params = [], array $options = []): array
    {
        return $this->http->request('POST', '/v1/tenants/' . self::id($tenantId) . '/connect-links', true, body: self::object(self::pick($params, ['connectors', 'frame_origin'])), hasBody: true, options: self::opts($options));
    }

    /**
     * Creates Digest credentials for a PBX that sends calls over SIP to `sip.voicast.jp`.
     * `agent_id` left out (or `null`) uses the tenant's agent. `transfer_to` is where the PBX transfers the call
     * (with SIP REFER) when the AI hands off to a person: an extension or number (digits and `*` `#` `+`, up to 32
     * characters) or a SIP URI (`sip:` / `sips:`, up to 200 characters); left out (or `''` / `null`) for no transfer.
     * The password is returned only here.
     * Not retried on a 5xx or a connection error (a retry would make a second credential).
     *
     * @param array{name?: string, agent_id?: string|null, transfer_to?: string|null} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{id: string, username: string, password: string, realm: string, server: string, tenant_id: string, agent_id: string|null, name: string, transfer_to: string, created_at: int}
     */
    public function createSipCredential(string $tenantId, array $params = [], array $options = []): array
    {
        return $this->http->request('POST', '/v1/tenants/' . self::id($tenantId) . '/sip-credentials', false, body: self::object(self::pick($params, ['name', 'agent_id', 'transfer_to'])), hasBody: true, options: self::opts($options));
    }

    /**
     * Lists the tenant's SIP credentials (without passwords).
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{data: list<array<string, mixed>>}
     */
    public function listSipCredentials(string $tenantId, array $options = []): array
    {
        return $this->http->request('GET', '/v1/tenants/' . self::id($tenantId) . '/sip-credentials', true, options: self::opts($options));
    }

    /**
     * Changes only the keys given and returns the credentials (without the password).
     * `'agent_id' => null` uses the tenant's agent; `'transfer_to' => ''` or `null` turns the transfer off.
     *
     * @param array{name?: string, agent_id?: string|null, transfer_to?: string|null} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{id: string, username: string, realm: string, server: string, tenant_id: string, agent_id: string|null, name: string, transfer_to: string, created_at: int}
     */
    public function updateSipCredential(string $tenantId, string $credentialId, array $params, array $options = []): array
    {
        return $this->http->request('PATCH', '/v1/tenants/' . self::id($tenantId) . '/sip-credentials/' . self::id($credentialId), true, body: self::object(self::pick($params, ['name', 'agent_id', 'transfer_to'])), hasBody: true, options: self::opts($options));
    }

    /**
     * Deletes SIP credentials. Calls on them are refused at once.
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     */
    public function deleteSipCredential(string $tenantId, string $credentialId, array $options = []): void
    {
        $this->http->request('DELETE', '/v1/tenants/' . self::id($tenantId) . '/sip-credentials/' . self::id($credentialId), true, options: self::opts($options));
    }
}
