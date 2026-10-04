<?php

declare(strict_types=1);

namespace Voicast\Exception;

/** The request did not complete within `timeout`. */
class TimeoutException extends ConnectionException
{
}
