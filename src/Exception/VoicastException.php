<?php

declare(strict_types=1);

namespace Voicast\Exception;

/**
 * Base class for every exception thrown by the SDK.
 *
 * The API error code is `errorCode` (`Exception::getCode()` is an integer and is not used).
 */
class VoicastException extends \RuntimeException
{
    /**
     * @param int|null $status HTTP status. Null when no response was received.
     * @param string|null $errorCode API error code, such as `invalid_request`, `in_use` or `balance_depleted`.
     * @param mixed $details Extra information from the API, such as the list of invalid fields for a 422.
     * @param string|null $requestId Request ID (`x-request-id`, or Cloudflare's `cf-ray`) to quote when contacting support.
     */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $errorCode = null,
        public readonly mixed $details = null,
        public readonly ?string $requestId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getDetails(): mixed
    {
        return $this->details;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
