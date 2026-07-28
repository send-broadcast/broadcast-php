<?php

declare(strict_types=1);

namespace Broadcast;

/**
 * The transport seam.
 *
 * Deliberately tiny rather than PSR-18: PSR-18 would drag in psr/http-message,
 * psr/http-factory and a concrete implementation for what is one request and
 * one response. A PSR-18 client can be adapted onto this in a few lines, and
 * the bundled CurlHttpClient keeps the package installable with no extra
 * dependencies — which matters for the WordPress audience this SDK exists for.
 */
interface HttpClientInterface
{
    /**
     * @param non-empty-string $method HTTP verb, uppercase
     * @param non-empty-string $url absolute URL including scheme
     * @param array<string,string> $headers
     * @return array{status:int, headers:array<string,string>, body:string}
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeout, int $openTimeout): array;
}
