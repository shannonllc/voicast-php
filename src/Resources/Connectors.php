<?php

declare(strict_types=1);

namespace Voicast\Resources;

/** Connectors that can be connected (`/v1/connectors`). */
final class Connectors extends Resource
{
    /**
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{data: list<array<string, mixed>>}
     */
    public function list(array $options = []): array
    {
        return $this->http->request('GET', '/v1/connectors', true, options: self::opts($options));
    }
}
