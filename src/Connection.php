<?php

declare(strict_types=1);

namespace Broadcast;

use Broadcast\Exception\ApiException;
use Broadcast\Exception\AuthenticationException;
use Broadcast\Exception\AuthorizationException;
use Broadcast\Exception\ConflictException;
use Broadcast\Exception\NotFoundException;
use Broadcast\Exception\RateLimitException;
use Broadcast\Exception\TimeoutException;
use Broadcast\Exception\ValidationException;
use Broadcast\Exception\WarningException;

/**
 * HTTP transport. Owns request building, response/error mapping, retries,
 * redirects, and warning dispatch, so Client stays a thin facade.
 */
final class Connection
{
    private const MAX_REDIRECTS = 3;
    private const REDIRECT_CODES = [301, 302, 307, 308];

    /** @var array<int,array{0:class-string<\Throwable>,1:string}> */
    private const ERROR_MAPPING = [
        401 => [AuthenticationException::class, 'Authentication failed'],
        403 => [AuthorizationException::class, 'Not authorized'],
        404 => [NotFoundException::class, 'Resource not found'],
        409 => [ConflictException::class, 'A request with this Idempotency-Key is still being processed'],
        422 => [ValidationException::class, 'Validation failed'],
    ];

    private Configuration $config;
    private HttpClientInterface $http;

    public function __construct(Configuration $config)
    {
        $this->config = $config;
        $this->http = $config->httpClient ?? new CurlHttpClient();
    }

    /** @param array<string,mixed>|null $payload */
    public function request(
        string $method,
        string $path,
        mixed $payload = null,
        array $headers = [],
        bool $raw = false
    ): mixed {
        $url = $this->buildUrl($path, $method, $payload);

        return $this->retryWithBackoff(
            fn () => $this->execute($method, $url, $payload, $headers, $raw, 0)
        );
    }

    // --- Request building ---------------------------------------------------

    private function buildUrl(string $path, string $method, mixed $payload): string
    {
        $url = $this->config->host . $path;

        if ($method === 'GET' && self::isPresent($payload)) {
            $url .= '?' . self::buildQuery($payload);
        }

        return $url;
    }

    /** @param array<string,mixed> $params */
    public static function buildQuery(array $params): string
    {
        $pairs = [];

        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $entry) {
                    $pairs[] = rawurlencode((string) $key . '[]') . '=' . rawurlencode(self::stringify($entry));
                }
            } elseif (is_array($value)) {
                foreach ($value as $sub => $subValue) {
                    $pairs[] = rawurlencode((string) $key . '[' . $sub . ']') . '=' . rawurlencode(self::stringify($subValue));
                }
            } else {
                $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode(self::stringify($value));
            }
        }

        return implode('&', $pairs);
    }

    private static function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            // PHP casts true to "1"; Rails wants "true".
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /** @param array<string,mixed> $extraHeaders */
    private function execute(
        string $method,
        string $url,
        mixed $payload,
        array $extraHeaders,
        bool $raw,
        int $redirects
    ): mixed {
        $headers = [
            'Authorization' => 'Bearer ' . (string) $this->config->apiToken,
            'Content-Type' => 'application/json',
            'User-Agent' => 'broadcast-php/' . Version::VERSION,
        ];
        foreach ($extraHeaders as $name => $value) {
            if ($value === null) {
                continue;
            }
            $headers[(string) $name] = (string) $value;
        }

        $body = null;
        if ($method !== 'GET' && self::isPresent($payload)) {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $this->debugRequest($method, $url, $body);

        $response = $this->http->send($method, $url, $headers, $body, $this->config->timeout, $this->config->openTimeout);

        $this->debugResponse($response['status']);

        if (in_array($response['status'], self::REDIRECT_CODES, true)) {
            return $this->followRedirect($response, $method, $url, $extraHeaders, $raw, $redirects);
        }

        return $this->handleResponse($response, $raw);
    }

    // --- Redirects ----------------------------------------------------------
    //
    // A redirect nearly always means a misconfigured `host` (http vs https, a
    // bare apex that redirects to www, a stale domain). Two things are never
    // followed: writes, because replaying a send against an unexpected origin is
    // worse than failing; and anything that changes host, because every request
    // carries the API token.

    /** @param array{status:int, headers:array<string,string>, body:string} $response */
    private function followRedirect(
        array $response,
        string $method,
        string $url,
        array $extraHeaders,
        bool $raw,
        int $redirects
    ): mixed {
        $location = $response['headers']['location'] ?? null;

        if ($method !== 'GET') {
            throw new ApiException(sprintf(
                'Host redirected %s %s to %s. Set `host` to the final URL — writes are not followed automatically.',
                $method,
                $url,
                $location ?? '(no Location header)'
            ));
        }
        if ($location === null) {
            throw new ApiException(sprintf('Redirect from %s had no Location header', $url));
        }
        if ($redirects >= self::MAX_REDIRECTS) {
            throw new ApiException(sprintf('Too many redirects (%d) starting at %s', self::MAX_REDIRECTS, $url));
        }

        $target = self::resolveUrl($url, $location);

        if (strtolower((string) parse_url($target, PHP_URL_HOST)) !== strtolower((string) parse_url($url, PHP_URL_HOST))) {
            throw new ApiException(sprintf(
                'Host redirected %s to a different host (%s). Not following it — the request carries '
                . 'your API token. Set `host` to the correct instance URL.',
                $url,
                $target
            ));
        }

        // The query string is already baked into the current URL.
        return $this->execute('GET', $target, null, $extraHeaders, $raw, $redirects + 1);
    }

    private static function resolveUrl(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        if (str_starts_with($location, '/')) {
            return $scheme . '://' . $host . $port . $location;
        }

        $path = $parts['path'] ?? '/';
        $directory = rtrim(substr($path, 0, (int) strrpos($path, '/')), '/');

        return $scheme . '://' . $host . $port . $directory . '/' . $location;
    }

    // --- Responses ----------------------------------------------------------

    /** @param array{status:int, headers:array<string,string>, body:string} $response */
    private function handleResponse(array $response, bool $raw): mixed
    {
        $status = $response['status'];

        if ($status >= 200 && $status <= 299) {
            return $this->buildSuccess($response, $raw);
        }

        $message = self::parseError($response['body']);

        if ($status === 429) {
            $retryAfter = $response['headers']['retry-after'] ?? null;
            throw new RateLimitException(
                $message ?? 'Rate limit exceeded',
                $retryAfter === null ? null : (int) $retryAfter
            );
        }

        if (isset(self::ERROR_MAPPING[$status])) {
            [$class, $default] = self::ERROR_MAPPING[$status];
            throw new $class($message ?? $default);
        }

        if ($status >= 500) {
            throw new ApiException($message ?? sprintf('Server error (%d)', $status));
        }

        throw new ApiException($message ?? sprintf('Unexpected response: %d', $status));
    }

    /** @param array{status:int, headers:array<string,string>, body:string} $response */
    private function buildSuccess(array $response, bool $raw): mixed
    {
        if ($raw) {
            // Raw endpoints serve text (/api/v1/skill) and binary file assets
            // alike; the body is handed back untouched either way.
            return $response['body'];
        }

        $parsed = self::parseSuccessBody($response['body']);

        // A bare array body (a JSON list) carries no metadata, matching the gem.
        if (!is_array($parsed) || array_is_list($parsed)) {
            return $parsed;
        }

        $result = new Response($parsed, $response['status'], $response['headers']);
        $this->handleWarnings($result);

        return $result;
    }

    private function handleWarnings(Response $result): void
    {
        if (!$result->hasWarnings()) {
            return;
        }

        if ($this->config->warningsMode === 'raise') {
            throw new WarningException($result->warnings(), $result);
        }

        if ($this->config->warningsMode === 'log' && $this->config->logger !== null) {
            foreach ($result->warnings() as $warning) {
                $this->config->logger->warning('[broadcast] ' . $warning);
            }
        }
    }

    // --- Retries ------------------------------------------------------------

    private function retryWithBackoff(callable $operation): mixed
    {
        $attempts = 0;

        while (true) {
            $attempts++;
            try {
                return $operation();
            } catch (\Throwable $error) {
                if ($attempts >= $this->config->retryAttempts || !self::isRetryable($error)) {
                    throw $error;
                }
                $this->sleep($this->delayFor($error, $attempts));
            }
        }
    }

    private static function isRetryable(\Throwable $error): bool
    {
        if ($error instanceof TimeoutException || $error instanceof RateLimitException) {
            return true;
        }

        // Only 5xx. A 422 is deterministic — retrying it is pure latency.
        return $error instanceof ApiException && str_contains($error->getMessage(), 'Server error');
    }

    /** Honour Retry-After, but never sleep longer than maxRetryDelay. */
    private function delayFor(\Throwable $error, int $attempts): float
    {
        if ($error instanceof RateLimitException && $error->retryAfter !== null) {
            return min((float) $error->retryAfter, $this->config->maxRetryDelay);
        }

        return min($this->config->retryDelay * $attempts, $this->config->maxRetryDelay);
    }

    private function sleep(float $seconds): void
    {
        if ($this->config->sleep !== null) {
            ($this->config->sleep)($seconds);

            return;
        }

        usleep((int) round($seconds * 1_000_000));
    }

    // --- Debug logging ------------------------------------------------------

    private function debugRequest(string $method, string $url, ?string $body): void
    {
        if (!$this->config->debug || $this->config->logger === null) {
            return;
        }

        // Never log the Authorization header or the body: bodies carry
        // subscriber email addresses and credential fields.
        $this->config->logger->debug(sprintf(
            '[broadcast] -> %s %s%s',
            $method,
            $url,
            $body !== null ? ' (body redacted)' : ''
        ));
    }

    private function debugResponse(int $status): void
    {
        if (!$this->config->debug || $this->config->logger === null) {
            return;
        }

        $this->config->logger->debug(sprintf('[broadcast] <- %d', $status));
    }

    // --- Helpers ------------------------------------------------------------

    private static function isPresent(mixed $payload): bool
    {
        return is_array($payload) && $payload !== [];
    }

    private static function parseSuccessBody(string $body): mixed
    {
        if (trim($body) === '') {
            return [];
        }

        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // A 2xx that isn't JSON (an HTML error page from a proxy, say).
            // Surface it as an empty body rather than exploding — raw: true is
            // the deliberate way to read non-JSON endpoints.
            return [];
        }
    }

    private static function parseError(string $body): ?string
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        if (isset($decoded['error']) && is_string($decoded['error'])) {
            return $decoded['error'];
        }

        return self::formatErrors($decoded['errors'] ?? null);
    }

    /** ActiveModel errors arrive as {"field": ["msg", ...]}. */
    private static function formatErrors(mixed $errors): ?string
    {
        if ($errors === null) {
            return null;
        }

        if (is_array($errors) && array_is_list($errors)) {
            return implode(', ', array_map(static fn ($e) => (string) $e, $errors));
        }

        if (!is_array($errors)) {
            return null;
        }

        $parts = [];
        foreach ($errors as $field => $messages) {
            $list = is_array($messages) ? $messages : [$messages];
            $parts[] = $field . ' ' . implode(', ', array_map(static fn ($m) => (string) $m, $list));
        }

        return implode('; ', $parts);
    }
}
