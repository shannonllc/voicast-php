<?php

declare(strict_types=1);

namespace Voicast\Exception;

/** 401: the API key is missing, wrong or revoked. */
class AuthenticationException extends VoicastException
{
}
