<?php

declare(strict_types=1);

namespace Voicast\Exception;

/** 402: a new call or session was refused for billing (`balance_depleted`). */
class PaymentRequiredException extends VoicastException
{
}
