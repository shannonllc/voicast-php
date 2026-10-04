<?php

declare(strict_types=1);

namespace Voicast;

/**
 * Body of an in-call tool request (`config.tools[].url`). Reply with 2xx and JSON within 8 seconds; the JSON is given
 * to the AI as is.
 */
final class ToolCallRequest
{
    /** The tool's `name`. */
    public readonly string $tool;
    /** Unique per call of a tool; also sent as `idempotency-key`. Use it to avoid booking twice. */
    public readonly ?string $toolCallId;
    /** @var array<string, mixed> The arguments from the AI. */
    public readonly array $arguments;
    /**
     * @var array<string, mixed> The call: `id`, `channel` (`phone` or `web`; `test` in a test), `provider_call_id`,
     *      `from` (phone calls, when your Twilio app passed it) and `tenant` (`id`, `external_id`; `null` in a test
     *      without `tenant_id`).
     */
    public readonly array $call;
    /** `true` when sent by `tools->try()` (or the dashboard's test button), not during a call. */
    public readonly bool $test;
    /** @var array<string, mixed> The whole verified body. */
    public readonly array $raw;

    /** @param array<string, mixed> $body A verified body with `type: tool.call`, `tool` and `call`. */
    public function __construct(array $body)
    {
        $this->tool = (string) $body['tool'];
        $this->toolCallId = isset($body['tool_call_id']) && is_string($body['tool_call_id']) ? $body['tool_call_id'] : null;
        $this->arguments = isset($body['arguments']) && is_array($body['arguments']) ? $body['arguments'] : [];
        $this->call = (array) $body['call'];
        $this->test = ($body['test'] ?? false) === true;
        $this->raw = $body;
    }
}
