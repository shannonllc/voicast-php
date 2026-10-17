# Changelog

## 0.5.0 (2026-10-17)

- Voices are chosen by ID: the agent config `voice` takes a voice ID, such as `female-breezy` (default), `female-firm` or `male-calm`. Agents read from the API return these IDs. The README lists only the IDs. The API still accepts the earlier voice names and saves the matching ID, so existing code keeps working; switch to the ID.
- Agent tools take `confirm_message` and `done_message`: for `confirm` tools, voicast now asks the caller with a fixed confirmation message and tells the result in a fixed message. These templates set the messages; `{argument name}` inserts an argument value (dates and times are read as spoken dates).

## 0.4.0 (2026-10-16)

- Transfer on a handoff for SIP: SIP credentials carry `transfer_to` (always present, `''` for none), where the PBX transfers the call with SIP REFER when the AI hands off to a person (outcome `handoff`): an extension or number (digits and `*` `#` `+`, up to 32 characters) or a SIP URI (`sip:` / `sips:`, up to 200 characters). `tenants->createSipCredential` takes `transfer_to`.
- `tenants->updateSipCredential($tenantId, $credentialId, ['name', 'agent_id', 'transfer_to'])` (`PATCH /v1/tenants/:id/sip-credentials/:credential`, retried like other PATCH requests) changes only the keys given and returns the credentials without the password. `'agent_id' => null` uses the tenant's agent; `'transfer_to' => ''` or `null` turns the transfer off.

## 0.3.0 (2026-10-16)

- SIP credentials for a PBX that sends calls to `sip.voicast.jp`: `tenants->createSipCredential($tenantId, ['name', 'agent_id'])` (`POST /v1/tenants/:id/sip-credentials`, returns the password once, not retried on a 5xx or a connection error), `tenants->listSipCredentials($tenantId)` and `tenants->deleteSipCredential($tenantId, $credentialId)`.

## 0.2.0 (2026-10-12)

- Retention period for call records and recordings: `retention->get()` (`GET /v1/retention`) and `retention->update(['retention_days' => 7])` (`PATCH /v1/retention`, retried like other PATCH requests). `retention_days` is 1, 7, 14 or 30 (30 by default and the maximum).
- `usage->get()` takes `channel`, `tenant_id` and `external_id` filters.
- `tools->try()`: a tool definition must carry its own `auth.value`; the agent's stored API key is used only with a saved tool's name (422 `missing_api_key`).

## 0.1.0 (2026-10-04)

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
