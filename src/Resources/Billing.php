<?php

declare(strict_types=1);

namespace Voicast\Resources;

/** The organization's billing (`/v1/billing`): balance, auto-recharge and the price per minute. */
final class Billing extends Resource
{
    /**
     * Gets the organization's billing. The organization has one balance, shared by its live and test environments.
     * Calls are paid from the prepaid balance, topped up by card.
     *
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function get(array $options = []): array
    {
        return $this->http->request('GET', '/v1/billing', true, options: self::opts($options));
    }

    /**
     * Creates a Stripe Checkout URL to top up the balance (`amount_yen`: 500 to 300,000 yen, before tax). Open it in a
     * browser to pay. Not retried on a 5xx or a connection error.
     *
     * @param array{amount_yen: int} $params
     * @param array{timeout?: float|int, max_retries?: int} $options
     * @return array{url: string, amount_yen: int, tax_yen: int}
     */
    public function createTopup(array $params, array $options = []): array
    {
        return $this->http->request('POST', '/v1/billing/topups', false, body: self::pick($params, ['amount_yen']), hasBody: true, options: self::opts($options));
    }
}
