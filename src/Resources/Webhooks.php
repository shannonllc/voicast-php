<?php

declare(strict_types=1);

namespace Voicast\Resources;

/** Where webhooks (`call.ended` and others) are sent is set up by you on the webhook portal. */
final class Webhooks extends Resource
{
    /**
     * Creates a link (valid for 15 minutes) to the webhook portal: endpoints, event types and signing secrets.
     *
     * @param array{frame_origin?: string} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{url: string, expires_at: int}
     */
    public function createPortalLink(array $params = [], array $options = []): array
    {
        return $this->http->request('POST', '/v1/webhooks/portal', true, body: self::object(self::pick($params, ['frame_origin'])), hasBody: true, options: self::opts($options));
    }
}
