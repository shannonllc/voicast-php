<?php

declare(strict_types=1);

namespace Voicast\Exception;

/** 429: too many requests. Thrown after retries are used up. */
class RateLimitException extends VoicastException
{
    /** @param float|null $retryAfter Seconds to wait before retrying, from `retry-after`. */
    public function __construct(
        string $message,
        ?int $status = null,
        ?string $errorCode = null,
        mixed $details = null,
        ?string $requestId = null,
        ?\Throwable $previous = null,
        public readonly ?float $retryAfter = null,
    ) {
        parent::__construct($message, $status, $errorCode, $details, $requestId, $previous);
    }
}
