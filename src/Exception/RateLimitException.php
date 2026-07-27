<?php

declare(strict_types=1);

namespace Broadcast\Exception;

class RateLimitException extends ApiException
{
    /** Seconds the server asked us to wait, parsed from the Retry-After header. */
    public readonly ?int $retryAfter;

    public function __construct(string $message = '', ?int $retryAfter = null)
    {
        parent::__construct($message);
        $this->retryAfter = $retryAfter;
    }
}
