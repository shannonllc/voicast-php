<?php

declare(strict_types=1);

namespace Voicast;

use Voicast\Exception\VoicastException;
use Voicast\Http\CurlTransport;
use Voicast\Http\HttpClient;
use Voicast\Http\Transport;
use Voicast\Resources\Agents;
use Voicast\Resources\Billing;
use Voicast\Resources\Calls;
use Voicast\Resources\Connections;
use Voicast\Resources\Connectors;
use Voicast\Resources\Sessions;
use Voicast\Resources\Tenants;
use Voicast\Resources\Tools;
use Voicast\Resources\Usage;
use Voicast\Resources\Webhooks;

/** Client for the voicast API (`/v1`). Use it on your server only: never ship the API key to a browser or an app. */
final class Client
{
    public const DEFAULT_BASE_URL = 'https://api.voicast.jp';
    /** Seconds per attempt. */
    public const DEFAULT_TIMEOUT = 30.0;
    public const DEFAULT_MAX_RETRIES = 2;
    public const API_KEY_ENV = 'VOICAST_API_KEY';

    public readonly Agents $agents;
    public readonly Tenants $tenants;
    public readonly Calls $calls;
    public readonly Sessions $sessions;
    public readonly Usage $usage;
    public readonly Connectors $connectors;
    public readonly Connections $connections;
    public readonly Webhooks $webhooks;
    public readonly Billing $billing;
    public readonly Tools $tools;
    public readonly string $baseUrl;
    /** @internal */
    public readonly HttpClient $http;

    /**
     * @param string|null $apiKey `vk_live_...` or `vk_test_...`. Defaults to the `VOICAST_API_KEY` environment variable.
     * @param array{base_url?: string, timeout?: float|int, max_retries?: int, transport?: Transport} $options
     *        `timeout` is seconds per attempt (default 30), `max_retries` the retries after the first attempt (default 2),
     *        `transport` a custom transport (default: cURL).
     */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $key = $apiKey ?? trim((string) getenv(self::API_KEY_ENV));
        if ($key === '') {
            throw new VoicastException("Missing API key. Pass it to new Client('vk_...') or set " . self::API_KEY_ENV . '.');
        }
        $this->baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $this->http = new HttpClient(
            $key,
            $this->baseUrl,
            (float) ($options['timeout'] ?? self::DEFAULT_TIMEOUT),
            (int) ($options['max_retries'] ?? self::DEFAULT_MAX_RETRIES),
            $options['transport'] ?? new CurlTransport(),
        );
        $this->agents = new Agents($this->http);
        $this->tenants = new Tenants($this->http);
        $this->calls = new Calls($this->http);
        $this->sessions = new Sessions($this->http);
        $this->usage = new Usage($this->http);
        $this->connectors = new Connectors($this->http);
        $this->connections = new Connections($this->http);
        $this->webhooks = new Webhooks($this->http);
        $this->billing = new Billing($this->http);
        $this->tools = new Tools($this->http);
    }
}
