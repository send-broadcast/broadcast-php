<?php

declare(strict_types=1);

namespace Broadcast\Exception;

/**
 * Base class for everything this package throws.
 *
 * The hierarchy mirrors broadcast-ruby's lib/broadcast/errors.rb. Note the
 * shape: ValidationException and TimeoutException extend this class directly,
 * NOT ApiException. That is deliberate — catching ApiException gets you
 * transport and status failures and leaves validation to be handled explicitly.
 */
class BroadcastException extends \RuntimeException
{
}
