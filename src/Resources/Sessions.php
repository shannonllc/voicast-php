<?php

declare(strict_types=1);

namespace Voicast\Resources;

/** Sessions for talking from a browser or an app over WebRTC (`/v1/sessions`). */
final class Sessions extends Resource
{
    /**
     * Creates a session on your server. Pass only `client_token` and `webrtc_url` to the browser or app, never the API
     * key. The token expires in 2 minutes and can be used once. Throws `PaymentRequiredException` when calls are
     * refused for billing. Not retried on a 5xx or a connection error.
     *
     * @param array{tenant_id: string, agent_id?: string, output?: 'audio'|'text'} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function create(array $params, array $options = []): array
    {
        return $this->http->request('POST', '/v1/sessions', false, body: self::pick($params, ['tenant_id', 'agent_id', 'output']), hasBody: true, options: self::opts($options));
    }
}
