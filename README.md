# shannonllc/voicast

PHP SDK for [voicast](https://voicast.jp/), the headless conversational AI platform that handles reception and bookings by phone and on the Web.

- No Composer dependencies. Uses ext-curl and ext-json.
- PHP 8.1+.
- Server-side only. To talk from a browser, create a session here and use `voicast/web` from the [Node.js SDK](https://www.npmjs.com/package/voicast) (or any Pipecat SmallWebRTC client) in the browser.

## Install

```sh
composer require shannonllc/voicast
```

## Quick start

```php
use Voicast\Client;

$voicast = new Client('vk_live_...'); // or set VOICAST_API_KEY

// What the AI says and asks
$agent = $voicast->agents->create([
    'name' => 'Reception',
    'config' => [
        'greeting' => 'お電話ありがとうございます。○○歯科です。',
        'persona' => '丁寧で落ち着いた受付',
        'fields' => [
            ['key' => 'name', 'label' => 'お名前', 'required' => true],
            ['key' => 'phone', 'label' => '電話番号', 'type' => 'phone'],
        ],
        'handoff_when' => ['急患', '担当者と話したいと言われた'],
        'closing' => '承りました。担当者から折り返しご連絡します。',
        'voice' => 'Kore',
    ],
]);

// The business that takes the calls (your customer)
$tenant = $voicast->tenants->create(['external_id' => 'clinic-123', 'name' => '○○歯科', 'agent_id' => $agent['id']]);
// $tenant['secret'] (vts_...) is returned only here: pass it to your Twilio <Stream> as the `secret` parameter
```

Use the API key on your server only. Never ship it to a browser or an app.

Params and responses are associative arrays with the API's field names. Keys you leave out are not sent; a key set to `null` is sent as JSON `null`.

## Talk from a browser or an app

Create a session on your server and pass only `client_token` and `webrtc_url` to the browser or app.

```php
$session = $voicast->sessions->create(['tenant_id' => $tenant['id']]); // 'output' => 'audio' (default) or 'text'
echo json_encode(['client_token' => $session['client_token'], 'webrtc_url' => $session['webrtc_url']]);
```

The token expires in 2 minutes and can be used once; create a new session to reconnect. After the call, get the result with `$voicast->calls->getByProvider($session['id'])`.

## Webhooks

voicast sends `call.ended`, `call.transcript_ready`, `call.summary_ready` and other events to the endpoints you register on the webhook portal (`$voicast->webhooks->createPortalLink()`). Verify them with the endpoint's signing secret. Pass the raw request body, not a decoded array.

```php
use Voicast\EventType;
use Voicast\Exception\WebhookVerificationException;
use function Voicast\verifyWebhook;

try {
    $event = verifyWebhook(file_get_contents('php://input'), $_SERVER, getenv('VOICAST_WEBHOOK_SECRET')); // whsec_...
} catch (WebhookVerificationException $e) {
    http_response_code(400);
    exit;
}
switch ($event->type) {
    case EventType::CALL_ENDED:
        error_log($event->data['outcome'] . ' ' . $event->data['tenant']['external_id']);
        break;
    case EventType::CALL_SUMMARY_READY:
        error_log($event->data['summary']);
        break;
    case EventType::BILLING_BALANCE_LOW:
        error_log((string) $event->data['balance_yen']);
        break;
}
http_response_code(204);
```

`verifyWebhook` throws `WebhookVerificationException` when the signature does not match, the timestamp is more than 5 minutes off (`verifyWebhook($payload, $headers, $secret, tolerance: 300)`), or the body is not a voicast event. Pass a list of secrets while you switch to a new one. Headers can be `$_SERVER`, an array of name => value (names are case-insensitive; list values use the first item), or a PSR-7 request. The shape of `data` for each event type is listed in `WebhookEvent`.

| Event | `data` |
|---|---|
| `call.started` | `{ call_id, provider_call_id, from, started_at, tenant }` |
| `call.handoff_requested` | `{ call_id, provider_call_id, reason, fields, tenant }` |
| `call.ended` | the call and `tenant` |
| `call.transcript_ready` | `{ call_id, provider_call_id, turns, tenant }` |
| `call.summary_ready` | `{ call_id, provider_call_id, summary, category, fields, tenant }` |
| `connection.needs_reauth` | `{ connection_id, connector, label, status, tenant }` |
| `billing.balance_low` / `billing.balance_depleted` / `billing.recharge_failed` / `billing.cap_reached` | billing details |

Laravel:

```php
Route::post('/webhooks/voicast', function (Request $request) {
    try {
        $event = verifyWebhook($request->getContent(), $request->headers->all(), config('services.voicast.webhook_secret'));
    } catch (WebhookVerificationException) {
        return response()->noContent(400);
    }
    // handle $event
    return response()->noContent();
});
```

## Tools the AI uses during a call

An agent's `config['tools']` lets the AI call your API during a call (check free slots, look up a customer, make a booking). voicast POSTs a signed request to the tool's `url` and waits up to 8 seconds; reply with 2xx and JSON, which is given to the AI as is.

```php
use function Voicast\verifyToolCall;

$call = verifyToolCall(file_get_contents('php://input'), $_SERVER, getenv('VOICAST_TOOLS_SECRET'));
if ($call->tool === 'find_free_slots') {
    header('Content-Type: application/json');
    echo json_encode(['slots' => findSlots($call->arguments['date'], $call->call['tenant']['external_id'] ?? null)]); // tenant is null in a test without tenant_id
}
```

The tools signing secret (`whsec_...`) is per environment: read it with `$voicast->tools->getSecret()['secret']` (or in the dashboard). `$voicast->tools->rotateSecret()` issues a new one; for 24 hours requests carry signatures with both, so pass both secrets to `verifyToolCall` while you switch. `$call->toolCallId` (also sent as `idempotency-key`) is unique per tool call: use it so a retried booking is not made twice.

To call a third-party API directly, set `'sign' => false` (the arguments are sent as they are: the query string for `'method' => 'GET'`, the JSON body for `POST`) and `'auth' => ['type' => 'api_key', 'header' => ..., 'value' => ...]` (for `Authorization` the key is sent as `Bearer <key>`). The key is stored encrypted outside the config and never returned; the agent's `tool_keys` shows its last 4 characters, and later updates can leave `value` out.

Test a tool before or after saving it with `tools->try()` (not billed, not recorded as a call, up to 30 per minute). Signed requests have `$call->test === true`, `$call->call['channel'] === 'test'`, and `tenant` is `null` unless you pass `tenant_id`:

```php
$r = $voicast->tools->try(['agent_id' => $agent['id'], 'tool' => 'find_free_slots', 'arguments' => ['date' => '2026-10-05']]);
if (!$r['ok']) {
    error_log(($r['error'] ?? '') . ' ' . ($r['message'] ?? '') . ' ' . $r['status'] . ' ' . $r['body']);
}
```

## API

| Method | Endpoint |
|---|---|
| `agents->create(['name', 'config'])` | `POST /v1/agents` |
| `agents->list()` | `GET /v1/agents` |
| `agents->get($id, ['version'])` | `GET /v1/agents/:id` |
| `agents->update($id, ['name', 'config'])` | `PUT /v1/agents/:id` (adds a version) |
| `agents->versions($id)` | `GET /v1/agents/:id/versions` |
| `agents->delete($id)` | `DELETE /v1/agents/:id` |
| `tenants->create(['external_id', 'name', 'agent_id'])` | `POST /v1/tenants` |
| `tenants->list()` | `GET /v1/tenants` |
| `tenants->get($id)` | `GET /v1/tenants/:id` |
| `tenants->update($id, ['name', 'agent_id', 'status'])` | `PATCH /v1/tenants/:id` |
| `tenants->rotateSecret($id)` | `POST /v1/tenants/:id/secret` |
| `tenants->delete($id)` | `DELETE /v1/tenants/:id` |
| `tenants->listConnections($id)` | `GET /v1/tenants/:id/connections` |
| `tenants->createConnectLink($id, ['connectors', 'frame_origin'])` | `POST /v1/tenants/:id/connect-links` |
| `calls->list(['tenant_id', 'before', 'limit'])` | `GET /v1/calls` |
| `calls->get($id)` | `GET /v1/calls/:id` |
| `calls->getByProvider($providerCallId)` | `GET /v1/calls/by-provider/:providerCallId` |
| `calls->transcript($id)` | `GET /v1/calls/:id/transcript` |
| `sessions->create(['tenant_id', 'agent_id', 'output'])` | `POST /v1/sessions` |
| `usage->get(['from', 'to', 'group_by'])` | `GET /v1/usage` |
| `connectors->list()` | `GET /v1/connectors` |
| `connections->list(['tenant_id'])` | `GET /v1/connections` |
| `connections->create(['connector', 'secret', 'input', 'tenant_id'])` | `POST /v1/connections` |
| `connections->get($id)` | `GET /v1/connections/:id` |
| `connections->update($id, ['config'])` | `PATCH /v1/connections/:id` |
| `connections->options($id, $query)` | `GET /v1/connections/:id/options` |
| `connections->delete($id)` | `DELETE /v1/connections/:id` |
| `webhooks->createPortalLink(['frame_origin'])` | `POST /v1/webhooks/portal` |
| `billing->get()` | `GET /v1/billing` |
| `billing->createTopup(['amount_yen'])` | `POST /v1/billing/topups` |
| `tools->getSecret()` | `GET /v1/tools/secret` |
| `tools->rotateSecret()` | `POST /v1/tools/secret/rotate` |
| `tools->try(['tool', 'agent_id', 'arguments', 'tenant_id'])` | `POST /v1/tools/try` |

Every method also takes per-call options as the last argument: `['timeout' => seconds, 'max_retries' => n]`.

`tenants->update($id, ['agent_id' => null])` removes the tenant's agent. `connections->list()` returns every connection, `connections->list(['tenant_id' => null])` the environment's connections only, and `connections->list(['tenant_id' => 'ten_...'])` that tenant's.

The call summary and category are on the call (`summary`, `category`), filled in when `call.summary_ready` is sent, and `billed_yen` is the fee for the call (the amount taken from the balance).

### Balance and top-ups

The organization has one balance, shared by its live and test environments. `billing->get()` returns the balance (`balance`), the auto-recharge settings and the price per minute. `billing->createTopup(['amount_yen' => 5000])` returns a Stripe Checkout `url` (500 to 300,000 yen, before tax); open it in a browser to pay. Payment is by card top-up only; auto-recharge, the monthly cap and the card are managed in the dashboard.

```php
$billing = $voicast->billing->get();
if ($billing['balance'] !== null && $billing['balance']['yen'] < 1000) {
    echo $voicast->billing->createTopup(['amount_yen' => 5000])['url'];
}
```

### Paging through calls

`calls->list()` returns the first page (`$page->data`, `$page->hasMore`). `foreach` over the page walks every call, newest first.

```php
$page = $voicast->calls->list(['tenant_id' => 'ten_...', 'limit' => 100]);

foreach ($voicast->calls->list(['tenant_id' => 'ten_...']) as $call) {
    echo $call['id'], ' ', $call['outcome'], "\n";
}
```

## Errors

Every exception extends `Voicast\Exception\VoicastException` and has `status`, `errorCode` (such as `invalid_request`, `in_use`, `balance_depleted`), `getMessage()`, `details` and `requestId`.

| Class | When |
|---|---|
| `AuthenticationException` | 401: the API key is missing, wrong or revoked |
| `PaymentRequiredException` | 402: new calls are refused (`balance_depleted`) |
| `PermissionException` | 403 |
| `NotFoundException` | 404 |
| `ConflictException` | 409: duplicate `external_id`, agent `in_use`, `tenant_disabled`, `needs_reauth` |
| `ValidationException` | 400, 422. See `details` |
| `RateLimitException` | 429, after retries. `retryAfter` in seconds |
| `ApiException` | other responses, including 5xx |
| `ConnectionException` / `TimeoutException` | no response, or `timeout` reached |
| `WebhookVerificationException` | `verifyWebhook` or `verifyToolCall` failed |

```php
use Voicast\Exception\PaymentRequiredException;

try {
    $voicast->sessions->create(['tenant_id' => $tenantId]);
} catch (PaymentRequiredException $e) {
    if ($e->errorCode !== 'balance_depleted') {
        throw $e;
    }
    showTopUpNotice();
}
```

## Retries and timeouts

`new Client($key, ['timeout' => 30, 'max_retries' => 2])` are the defaults. 429 is retried for every request, waiting `retry-after` (up to one minute). 5xx, timeouts and connection errors are retried only for requests safe to repeat (GET, PATCH, DELETE, and creating links), with exponential backoff from 0.5 s. Creating agents, tenants, sessions, connections and top-ups, updating an agent and rotating a secret are not retried on those, so they never run twice. The API does not take an `Idempotency-Key`.

To send requests through Guzzle or a PSR-18 client, implement `Voicast\Http\Transport` and pass it as `'transport'`.

## Links

- voicast: https://voicast.jp/
- Changelog: [CHANGELOG.md](./CHANGELOG.md)
- License: MIT
