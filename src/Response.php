<?php

declare(strict_types=1);

namespace Broadcast;

/**
 * The value returned by every JSON API call.
 *
 * Implements ArrayAccess so `$result['id']` reads the body, while methods read
 * transport metadata — the same two namespaces the Ruby gem gets from
 * `Response < Hash`. A body field named `status` stays reachable as
 * `$result['status']` while `$result->status()` is the HTTP status.
 *
 *   $result = $client->subscribers->create(['email' => 'a@b.com']);
 *   $result['id'];                      // body
 *   $result->status();                  // 201
 *   $result->warnings();                // list<Warning>
 *   $result->rateLimit()?->remaining;
 *   $result->isIdempotentReplay();
 *
 * @implements \ArrayAccess<string,mixed>
 * @implements \IteratorAggregate<string,mixed>
 */
final class Response implements \ArrayAccess, \IteratorAggregate, \Countable, \JsonSerializable
{
    /** @var array<string,mixed> */
    private array $data;

    private int $status;

    /** @var array<string,string> */
    private array $headers;

    /** @var null|list<Warning> */
    private ?array $warnings = null;

    private bool $rateLimitParsed = false;
    private ?RateLimit $rateLimit = null;

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $headers
     */
    public function __construct(array $data, int $status, array $headers = [])
    {
        $this->data = $data;
        $this->status = $status;

        $lowered = [];
        foreach ($headers as $name => $value) {
            $lowered[strtolower((string) $name)] = $value;
        }
        $this->headers = $lowered;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** @return list<Warning> */
    public function warnings(): array
    {
        if ($this->warnings === null) {
            $entries = $this->data['warnings'] ?? null;
            $parsed = [];

            if (is_array($entries)) {
                foreach ($entries as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    $parsed[] = new Warning(
                        $entry['code'] ?? null,
                        $entry['param'] ?? null,
                        $entry['message'] ?? null
                    );
                }
            }

            $this->warnings = $parsed;
        }

        return $this->warnings;
    }

    public function hasWarnings(): bool
    {
        return $this->warnings() !== [];
    }

    public function rateLimit(): ?RateLimit
    {
        if (!$this->rateLimitParsed) {
            $this->rateLimitParsed = true;

            $limit = $this->headers['x-ratelimit-limit'] ?? null;
            if ($limit !== null) {
                $remaining = $this->headers['x-ratelimit-remaining'] ?? null;
                $this->rateLimit = new RateLimit(
                    (int) $limit,
                    $remaining === null ? null : (int) $remaining,
                    self::parseTime($this->headers['x-ratelimit-reset'] ?? null)
                );
            }
        }

        return $this->rateLimit;
    }

    /**
     * True when the API replayed a stored response for a repeated
     * Idempotency-Key rather than performing the write again.
     */
    public function isIdempotentReplay(): bool
    {
        return ($this->headers['idempotency-replayed'] ?? null) === 'true';
    }

    // --- ArrayAccess / iteration ------------------------------------------

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // A Response wraps a JSON object. Appending would add an integer key
        // beside the string ones, leaving a body that is neither an object nor
        // a list — and json_encode would then change shape depending on which
        // keys survived. It also contradicted the declared array<string, mixed>.
        if ($offset === null) {
            throw new \LogicException(
                'A Broadcast\\Response wraps a JSON object and cannot be appended to. '
                . 'Assign a key instead: $response[\'name\'] = $value.'
            );
        }

        $this->data[(string) $offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    private static function parseTime(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
