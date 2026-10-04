<?php

declare(strict_types=1);

namespace Voicast\Resources;

/** Minutes used, for billing your own customers (`/v1/usage`). */
final class Usage extends Resource
{
    /**
     * Counts calls that ended in the period. `from` and `to` are `YYYY-MM-DD` (Japan time, inclusive) or Unix
     * milliseconds (`to` exclusive). Default: this month (Japan time) until now. Up to 366 days.
     *
     * @param array{from?: string|int, to?: string|int, group_by?: 'tenant'|'day'|'channel'} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function get(array $params = [], array $options = []): array
    {
        return $this->http->request('GET', '/v1/usage', true, query: [
            'from' => $params['from'] ?? null,
            'to' => $params['to'] ?? null,
            'group_by' => $params['group_by'] ?? null,
        ], options: self::opts($options));
    }
}
