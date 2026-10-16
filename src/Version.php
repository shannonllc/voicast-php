<?php

declare(strict_types=1);

namespace Voicast;

final class Version
{
    /** SDK version. Kept equal to CHANGELOG.md by a test. */
    public const VERSION = '0.4.0';

    /** Sent as User-Agent so API request logs show the SDK and its version. */
    public const USER_AGENT = 'voicast-php/' . self::VERSION;
}
