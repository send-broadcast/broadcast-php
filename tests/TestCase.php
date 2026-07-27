<?php

declare(strict_types=1);

namespace Broadcast\Tests;

use Broadcast\Client;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected StubHttpClient $http;

    /** @var list<float> */
    protected array $slept = [];

    /**
     * @param array<string,mixed>|list<array<string,mixed>> $stubs
     * @param array<string,mixed> $options
     */
    protected function client(array $stubs = [], array $options = []): Client
    {
        $this->http = new StubHttpClient($stubs === [] ? [['body' => []]] : $stubs);
        $this->slept = [];

        return new Client(array_merge([
            'apiToken' => 'test-token',
            'host' => 'https://mail.example.com',
            'retryDelay' => 0,
            'httpClient' => $this->http,
            'sleep' => function (float $seconds): void {
                $this->slept[] = $seconds;
            },
        ], $options));
    }
}
