<?php

declare(strict_types=1);

namespace Broadcast;

/**
 * A single entry from the API's `warnings` array.
 *
 * The API raises these on successful 2xx responses when it accepted the request
 * but ignored part of it — an unrecognised parameter, a parameter that only
 * applies in another mode, a value the server overrode.
 *
 * `param` is a dot-path to the offending parameter (e.g. "subscriber.foo").
 * The API never includes submitted values, so a warning is safe to log.
 */
final class Warning implements \Stringable
{
    public function __construct(
        public readonly ?string $code,
        public readonly ?string $param,
        public readonly ?string $message,
    ) {
    }

    public function __toString(): string
    {
        if ($this->param !== null && $this->param !== '') {
            return sprintf('[%s] %s: %s', $this->code, $this->param, $this->message);
        }

        return sprintf('[%s] %s', $this->code, $this->message);
    }
}
