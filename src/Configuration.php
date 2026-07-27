<?php

declare(strict_types=1);

namespace Broadcast;

use Broadcast\Exception\ConfigurationException;
use Psr\Log\LoggerInterface;

/**
 * Client configuration and validation.
 *
 * Durations are in **seconds**, matching the Ruby gem.
 */
final class Configuration
{
    /**
     * How to handle the `warnings` array the API returns on successful writes:
     *   'log'    — warn through the PSR-3 logger if one is set (default)
     *   'raise'  — throw WarningException; note the write already happened
     *   'ignore' — leave them on the response for the caller to inspect
     */
    public const WARNINGS_MODES = ['log', 'raise', 'ignore'];

    /** Env vars use the same names as the Broadcast CLI's ~/.config/broadcast/config. */
    public const ENV_HOST = 'BROADCAST_HOST';
    public const ENV_TOKEN = 'BROADCAST_API_TOKEN';

    public ?string $apiToken;
    public ?string $host;
    public int $timeout;
    public int $openTimeout;
    public int $retryAttempts;
    public float $retryDelay;
    public float $maxRetryDelay;
    public string $warningsMode;
    public ?LoggerInterface $logger;
    public bool $debug;
    public string|int|null $broadcastChannelId;

    /** Injectable transport, so the suite never opens a socket. */
    public ?HttpClientInterface $httpClient;

    /** @var null|callable(float):void Injectable sleep, so retries do not stall tests. */
    public $sleep;

    /** @param array<string,mixed> $options */
    public function __construct(array $options = [])
    {
        $known = [
            'apiToken', 'host', 'timeout', 'openTimeout', 'retryAttempts', 'retryDelay',
            'maxRetryDelay', 'warningsMode', 'logger', 'debug', 'broadcastChannelId',
            'httpClient', 'sleep',
        ];
        // A typo in a setting name should fail loudly rather than be silently
        // ignored, leaving the caller wondering why their timeout had no effect.
        $unknown = array_diff(array_keys($options), $known);
        if ($unknown !== []) {
            throw new ConfigurationException(
                'Unknown option(s): ' . implode(', ', $unknown) . '. Known options: ' . implode(', ', $known)
            );
        }

        $this->apiToken = $options['apiToken'] ?? (getenv(self::ENV_TOKEN) ?: null);
        // No default host. Broadcast is self-hosted-first — every instance lives
        // at its own domain, so any built-in guess is wrong for nearly everyone.
        $this->host = $options['host'] ?? (getenv(self::ENV_HOST) ?: null);

        $this->timeout = $options['timeout'] ?? 30;
        $this->openTimeout = $options['openTimeout'] ?? 10;
        $this->retryAttempts = $options['retryAttempts'] ?? 3;
        $this->retryDelay = $options['retryDelay'] ?? 1;
        // Ceiling for a server-supplied Retry-After. Without it a long
        // rate-limit window would block the caller for as long as the server asked.
        $this->maxRetryDelay = $options['maxRetryDelay'] ?? 30;

        $this->warningsMode = $options['warningsMode'] ?? 'log';
        $this->logger = $options['logger'] ?? null;
        $this->debug = $options['debug'] ?? false;
        $this->broadcastChannelId = $options['broadcastChannelId'] ?? null;
        $this->httpClient = $options['httpClient'] ?? null;
        $this->sleep = $options['sleep'] ?? null;
    }

    public function validate(): void
    {
        if ($this->isBlank($this->apiToken)) {
            throw new ConfigurationException('api_token is required');
        }
        if ($this->isBlank($this->host)) {
            throw new ConfigurationException(self::hostMissingMessage());
        }

        $this->host = rtrim(trim((string) $this->host), '/');
        $this->validateHostScheme();
        $this->validateWarningsMode();
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }

    private function validateHostScheme(): void
    {
        if (str_starts_with((string) $this->host, 'http://') || str_starts_with((string) $this->host, 'https://')) {
            return;
        }

        throw new ConfigurationException(
            sprintf('host must include a scheme (http:// or https://), got "%s"', $this->host)
        );
    }

    private function validateWarningsMode(): void
    {
        if (in_array($this->warningsMode, self::WARNINGS_MODES, true)) {
            return;
        }

        throw new ConfigurationException(sprintf(
            'warnings_mode must be one of %s, got "%s"',
            implode(', ', self::WARNINGS_MODES),
            $this->warningsMode
        ));
    }

    private static function hostMissingMessage(): string
    {
        return 'host is required — point it at your Broadcast instance, e.g. '
            . "new Broadcast\\Client(['apiToken' => '...', 'host' => 'https://mail.example.com']). "
            . 'You can also set the ' . self::ENV_HOST . ' environment variable.';
    }
}
