<?php

declare(strict_types=1);

namespace Broadcast\Tests;

use Broadcast\CurlHttpClient;
use Broadcast\Exception\TimeoutException;

/**
 * The real cURL transport. A request to a closed local port fails at once, which is enough to run
 * the whole send() path. curl_close() is deprecated since PHP 8.5 (a no-op since 8.0), so calling it
 * printed a deprecation on every request.
 */
final class CurlHttpClientTest extends TestCase
{
    public function testARequestRaisesNoDeprecation(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($socket);
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $deprecations = [];
        set_error_handler(function (int $errno, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;
            return true;
        }, E_DEPRECATED | E_USER_DEPRECATED);

        try {
            (new CurlHttpClient())->send('GET', "http://127.0.0.1:{$port}/", [], null, 2, 2);
            $this->fail('A closed port must fail');
        } catch (TimeoutException) {
            // expected: nothing listens on the port
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
    }
}
