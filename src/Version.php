<?php

declare(strict_types=1);

namespace Broadcast;

final class Version
{
    /**
     * Kept in sync with composer.json's packaged version by tests/PackageTest.
     * A User-Agent that lies about its version misattributes server-side
     * client analytics.
     */
    public const VERSION = '0.2.0';
}
