<?php

declare(strict_types=1);

namespace Broadcast\Exception;

/**
 * 409 — an in-flight request is already using this Idempotency-Key. The
 * original request is still processing; retrying after a short pause will
 * either replay its stored response or run fresh if it failed.
 */
class ConflictException extends ApiException
{
}
