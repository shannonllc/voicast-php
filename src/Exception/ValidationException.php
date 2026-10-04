<?php

declare(strict_types=1);

namespace Voicast\Exception;

/** 400 or 422: the request body or parameters are invalid. See `details`. */
class ValidationException extends VoicastException
{
}
