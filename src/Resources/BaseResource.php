<?php

declare(strict_types=1);

namespace Broadcast\Resources;

use Broadcast\Client;

/**
 * Shared plumbing for resource classes.
 *
 * The HTTP helpers are named httpGet/httpDelete so a resource can expose
 * `get()` and `delete()` as its public API without shadowing them.
 */
abstract class BaseResource
{
    public const MAX_IDEMPOTENCY_KEY_LENGTH = 255;

    public function __construct(protected Client $client)
    {
    }

    /**
     * @param non-empty-string $path
     * @param array<string,mixed> $params
     */
    protected function httpGet(string $path, array $params = [], bool $raw = false): mixed
    {
        return $this->client->request('GET', $path, $params, [], $raw);
    }

    /**
     * @param non-empty-string $path
     * @param array<string,mixed> $body
     * @param array<string,mixed> $headers
     */
    protected function httpPost(string $path, array $body = [], array $headers = []): mixed
    {
        return $this->client->request('POST', $path, $body, $headers);
    }

    /**
     * @param non-empty-string $path
     * @param array<string,mixed> $body
     */
    protected function httpPatch(string $path, array $body = []): mixed
    {
        return $this->client->request('PATCH', $path, $body);
    }

    /**
     * @param non-empty-string $path
     * @param array<string,mixed> $body
     */
    protected function httpPut(string $path, array $body = []): mixed
    {
        return $this->client->request('PUT', $path, $body);
    }

    /**
     * @param non-empty-string $path
     * @param array<string,mixed>|null $body
     */
    protected function httpDelete(string $path, ?array $body = null): mixed
    {
        return $this->client->request('DELETE', $path, $body);
    }

    /** Emit through the PSR-3 logger, falling back to error_log. */
    protected function warn(string $message): void
    {
        $logger = $this->client->config->logger;
        if ($logger !== null) {
            $logger->warning($message);

            return;
        }
        error_log($message);
    }

    /**
     * Drop null values so an omitted argument never reaches the wire.
     *
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    protected static function compact(array $values): array
    {
        return array_filter($values, static fn ($value) => $value !== null);
    }

    /**
     * Validate an idempotency key and turn it into the Idempotency-Key header.
     *
     * @return array<string,string>
     */
    protected static function idempotencyHeaders(?string $key): array
    {
        if ($key === null) {
            return [];
        }

        $trimmed = trim($key);
        if ($trimmed === '') {
            return [];
        }

        if (mb_strlen($trimmed) > self::MAX_IDEMPOTENCY_KEY_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'idempotency_key must be %d characters or fewer (got %d)',
                self::MAX_IDEMPOTENCY_KEY_LENGTH,
                mb_strlen($trimmed)
            ));
        }

        return ['Idempotency-Key' => $trimmed];
    }
}
