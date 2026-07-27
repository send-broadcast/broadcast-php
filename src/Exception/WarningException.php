<?php

declare(strict_types=1);

namespace Broadcast\Exception;

use Broadcast\Warning;

/**
 * Thrown instead of returning when warningsMode is 'raise' and a 2xx response
 * carried warnings. The request DID succeed — the write happened. Callers
 * catching this must not assume anything was rolled back.
 */
class WarningException extends BroadcastException
{
    /** @var list<Warning> */
    public readonly array $warnings;

    public readonly mixed $response;

    /** @param list<Warning> $warnings */
    public function __construct(array $warnings, mixed $response = null)
    {
        $joined = implode('; ', array_map(static fn ($w) => (string) $w, $warnings));
        parent::__construct(sprintf('API returned %d warning(s): %s', count($warnings), $joined));

        $this->warnings = $warnings;
        $this->response = $response;
    }
}
