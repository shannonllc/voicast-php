<?php

declare(strict_types=1);

namespace Voicast\Resources;

/**
 * How long call records and recordings are kept in the API key's environment (`/v1/retention`): 1, 7, 14 or 30 days
 * (30 by default). Call records count from the end of the call, recordings from when they were saved.
 */
final class Retention extends Resource
{
    /**
     * Gets the retention period.
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{retention_days: 1|7|14|30, choices: list<int>}
     */
    public function get(array $options = []): array
    {
        return $this->http->request('GET', '/v1/retention', true, options: self::opts($options));
    }

    /**
     * Changes the retention period (`retention_days`: 1, 7, 14 or 30). When shortened, call records and recordings past
     * the new period are deleted at the next daily cleanup and cannot be restored. Usage and billing do not change.
     *
     * @param array{retention_days: 1|7|14|30} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{retention_days: 1|7|14|30, choices: list<int>}
     */
    public function update(array $params, array $options = []): array
    {
        return $this->http->request('PATCH', '/v1/retention', true, body: self::pick($params, ['retention_days']), hasBody: true, options: self::opts($options));
    }
}
