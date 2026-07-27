<?php

declare(strict_types=1);

namespace Broadcast\Tests;

use Broadcast\HttpClientInterface;

/** Replays queued responses and records every request, so no socket is opened. */
final class StubHttpClient implements HttpClientInterface
{
    /** @var list<array<string,mixed>> */
    private array $queue;

    /** @var list<array<string,mixed>> */
    public array $calls = [];

    /** @param array<string,mixed>|list<array<string,mixed>> $stubs */
    public function __construct(array $stubs = [])
    {
        $this->queue = array_is_list($stubs) ? $stubs : [$stubs];
        if ($this->queue === []) {
            $this->queue = [[]];
        }
    }

    public function send(string $method, string $url, array $headers, ?string $body, int $timeout, int $openTimeout): array
    {
        $lowered = [];
        foreach ($headers as $name => $value) {
            $lowered[strtolower($name)] = $value;
        }

        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        $this->calls[] = [
            'method' => $method,
            'url' => $url,
            'path' => $parts['path'] ?? '',
            'query' => $query,
            'headers' => $lowered,
            'body' => $body === null ? null : json_decode($body, true),
            'rawBody' => $body,
        ];

        $stub = count($this->queue) > 1 ? array_shift($this->queue) : $this->queue[0];

        if (isset($stub['throws'])) {
            throw $stub['throws'];
        }

        return [
            'status' => $stub['status'] ?? 200,
            'headers' => $stub['headers'] ?? ['content-type' => 'application/json'],
            'body' => array_key_exists('text', $stub)
                ? (string) $stub['text']
                : json_encode($stub['body'] ?? [], JSON_THROW_ON_ERROR),
        ];
    }

    /** @return array<string,mixed> */
    public function last(): array
    {
        return $this->calls[count($this->calls) - 1];
    }
}
