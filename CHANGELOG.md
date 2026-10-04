# Changelog

## 0.1.0 (unreleased)

- First release. Same API coverage as the Node.js SDK 0.1.0 (without the browser helper `voicast/web`). No Composer dependencies (ext-curl and ext-json), PHP 8.1+.
- Agent tools for third-party APIs: a tool takes `method` (`GET` or `POST`), `sign` (`false` sends the arguments as they are) and `auth` (`['type' => 'api_key', 'header' => ..., 'value' => ...]`; the key is stored encrypted and never returned). An agent's `tool_keys` lists the stored keys (tool name and last 4 characters) on `create`, `get` and `update`.
- `tools->try(['tool', 'agent_id', 'arguments', 'tenant_id'])` calls a saved or unsaved tool once and returns `ok`, `result`, `error`, `message`, `status`, `body`, `truncated` and `duration_ms`. Not retried on a 5xx or a connection error. `ToolCallRequest::$test` is `true` for these requests, `call['channel']` is `test` and `call['tenant']` can be `null`.
- A connector entry's `actions` lists action names; `'*'` is no longer accepted.
- `new Client($apiKey = null, ['base_url', 'timeout', 'max_retries', 'transport'])`: API key from the argument or `VOICAST_API_KEY`, default base URL `https://api.voicast.jp`. Retries 429 (honouring `retry-after` and `x-ratelimit-reset`, up to one minute) and, for requests safe to repeat (GET, PATCH, DELETE), 5xx, timeouts and connection errors, with exponential backoff and jitter. Every method takes `['timeout' => seconds, 'max_retries' => n]` as the last argument. A custom `Transport` routes requests through Guzzle or a PSR-18 client.
- Agents: `agents->create`, `list`, `get($id, ['version' => n])`, `update`, `versions`, `delete`.
- Tenants: `tenants->create` (returns the connection secret once), `list`, `get`, `update` (`'agent_id' => null` removes the agent), `rotateSecret`, `delete`, `listConnections`, `createConnectLink`.
- Calls: `calls->list(['tenant_id', 'before', 'limit'])` returns a `Page` (`foreach` it to walk every call), `get`, `getByProvider`, `transcript`.
- Sessions for Web and app voice: `sessions->create(['tenant_id', 'agent_id', 'output'])`.
- Usage: `usage->get(['from', 'to', 'group_by'])`.
- Connectors and connections: `connectors->list`, `connections->list(['tenant_id' => ...])`, `create`, `get`, `update`, `options`, `delete`.
- Webhook portal: `webhooks->createPortalLink(['frame_origin' => ...])`.
- Billing: `billing->get()` (balance, auto-recharge, price per minute) and `billing->createTopup(['amount_yen' => ...])` (a Stripe Checkout URL). Tools signing secret: `tools->getSecret()` and `tools->rotateSecret()`. A call's `billed_yen` is the fee for the call.
- Exceptions: `AuthenticationException` (401), `PaymentRequiredException` (402), `PermissionException` (403), `NotFoundException` (404), `ConflictException` (409), `ValidationException` (400, 422), `RateLimitException` (429), `ApiException`, `ConnectionException`, `TimeoutException`, all extending `VoicastException` with `status`, `errorCode`, `details` and `requestId`.
- `Voicast\verifyWebhook($payload, $headers, $secret)` returns a `WebhookEvent` (`type`, `timestamp`, `data`; `EventType` has the type names). `Voicast\verifyToolCall($payload, $headers, $secret)` verifies in-call tool requests and returns a `ToolCallRequest` (`tool`, `toolCallId`, `arguments`, `call`). Both take a list of secrets while you switch to a new one. `Webhook` for lower-level use and `sign()` for tests.
