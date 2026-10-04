<?php

declare(strict_types=1);

namespace Voicast\Exception;

/** 409: conflicts with the current state (duplicate `external_id`, agent `in_use`, `tenant_disabled`, `needs_reauth`). */
class ConflictException extends VoicastException
{
}
