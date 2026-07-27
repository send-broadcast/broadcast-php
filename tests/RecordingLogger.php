<?php

declare(strict_types=1);

namespace Broadcast\Tests;

use Psr\Log\AbstractLogger;

final class RecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $warnings = [];

    /** @var list<string> */
    public array $debugs = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        if ($level === 'warning') {
            $this->warnings[] = (string) $message;
        }
        if ($level === 'debug') {
            $this->debugs[] = (string) $message;
        }
    }
}
