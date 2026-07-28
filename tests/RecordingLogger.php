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

    /**
     * The $message parameter is deliberately untyped.
     *
     * psr/log 3.x declares `string|\Stringable $message`; 1.x declares it
     * untyped. PHP's contravariance rules let an implementation widen a
     * parameter but never narrow one, so an untyped (implicitly `mixed`)
     * parameter satisfies all three majors, while `string|\Stringable` is a
     * fatal error against 1.x. composer.json claims `^1.1 || ^2.0 || ^3.0`,
     * and the lowest-dependency CI job is what proves that claim true.
     *
     * @param mixed $level
     * @param mixed $message
     * @param array<string,mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        if ($level === 'warning') {
            $this->warnings[] = (string) $message;
        }
        if ($level === 'debug') {
            $this->debugs[] = (string) $message;
        }
    }
}
