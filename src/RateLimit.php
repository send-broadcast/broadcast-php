<?php

declare(strict_types=1);

namespace Broadcast;

/**
 * Parsed X-RateLimit-* response headers.
 *
 * `reset` is the time the current window rolls over, not a duration.
 */
final class RateLimit
{
    public function __construct(
        public readonly int $limit,
        public readonly ?int $remaining,
        public readonly ?\DateTimeImmutable $reset,
    ) {
    }
}
