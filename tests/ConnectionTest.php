<?php

declare(strict_types=1);

namespace Broadcast\Tests;

use Broadcast\Client;
use Broadcast\Exception\ApiException;
use Broadcast\Exception\AuthenticationException;
use Broadcast\Exception\AuthorizationException;
use Broadcast\Exception\ConfigurationException;
use Broadcast\Exception\ConflictException;
use Broadcast\Exception\NotFoundException;
use Broadcast\Exception\RateLimitException;
use Broadcast\Exception\TimeoutException;
use Broadcast\Exception\ValidationException;
use Broadcast\Exception\WarningException;
use Broadcast\Response;
use PHPUnit\Framework\Attributes\DataProvider;

final class ConnectionTest extends TestCase
{
    // --- Configuration ------------------------------------------------------

    public function testRequiresAnApiToken(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/api_token is required/');
        new Client(['host' => 'https://mail.example.com', 'apiToken' => '']);
    }

    public function testRequiresAHostAndSaysHowToSetIt(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/host is required.*BROADCAST_HOST/s');
        new Client(['apiToken' => 'token', 'host' => '']);
    }

    public function testStripsWhitespaceAndTrailingSlashFromHost(): void
    {
        $client = new Client(['apiToken' => 't', 'host' => '  https://mail.example.com/  ']);
        self::assertSame('https://mail.example.com', $client->config->host);
    }

    public function testRejectsAHostWithNoScheme(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/must include a scheme/');
        new Client(['apiToken' => 't', 'host' => 'mail.example.com']);
    }

    public function testRejectsAnUnknownWarningsMode(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/warnings_mode must be one of/');
        new Client(['apiToken' => 't', 'host' => 'https://a.com', 'warningsMode' => 'explode']);
    }

    public function testRejectsAnUnknownOption(): void
    {
        // A typo in a setting name should fail loudly, not be silently ignored.
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/Unknown option/');
        new Client(['apiToken' => 't', 'host' => 'https://a.com', 'timout' => 5]);
    }

    // --- Request building ---------------------------------------------------

    public function testSendsBearerAuthContentTypeAndUserAgent(): void
    {
        $client = $this->client();
        $client->discovery->whoami();

        $headers = $this->http->last()['headers'];
        self::assertSame('Bearer test-token', $headers['authorization']);
        self::assertSame('application/json', $headers['content-type']);
        self::assertMatchesRegularExpression('#^broadcast-php/\d+\.\d+\.\d+$#', $headers['user-agent']);
    }

    public function testBuildsTheUrlFromHostAndPath(): void
    {
        $client = $this->client();
        $client->discovery->whoami();
        self::assertSame('https://mail.example.com/api/v1/whoami', $this->http->last()['url']);
    }

    public function testGetParamsBecomeAQueryStringNotABody(): void
    {
        $client = $this->client();
        $client->subscribers->list(['page' => 2, 'is_active' => true]);

        self::assertNull($this->http->last()['body']);
        self::assertSame('2', $this->http->last()['query']['page']);
        // PHP casts true to "1"; Rails needs "true".
        self::assertSame('true', $this->http->last()['query']['is_active']);
    }

    public function testArrayParamsRepeatWithBracketSuffix(): void
    {
        $client = $this->client();
        $client->subscribers->list(['tags' => ['a', 'b']]);
        self::assertSame(['a', 'b'], $this->http->last()['query']['tags']);
    }

    public function testNestedParamsFlatten(): void
    {
        $client = $this->client();
        $client->subscribers->list(['custom_data' => ['plan' => 'pro']]);
        self::assertSame(['plan' => 'pro'], $this->http->last()['query']['custom_data']);
    }

    public function testNullParamsAreDropped(): void
    {
        $client = $this->client();
        $client->subscribers->list(['page' => 1, 'source' => null]);
        self::assertArrayNotHasKey('source', $this->http->last()['query']);
    }

    public function testEmptyParamsAddNoQueryString(): void
    {
        $client = $this->client();
        $client->discovery->whoami();
        self::assertStringNotContainsString('?', $this->http->last()['url']);
    }

    public function testWritesSendAJsonBody(): void
    {
        $client = $this->client(['status' => 201, 'body' => []]);
        $client->subscribers->create(['email' => 'a@b.com']);

        self::assertSame('POST', $this->http->last()['method']);
        self::assertSame(['subscriber' => ['email' => 'a@b.com']], $this->http->last()['body']);
    }

    // --- Responses ----------------------------------------------------------

    public function testParsesJsonAndAttachesMetadata(): void
    {
        $client = $this->client([
            'status' => 201,
            'body' => ['id' => 7],
            'headers' => ['content-type' => 'application/json', 'x-ratelimit-limit' => '120'],
        ]);
        $result = $client->subscribers->create(['email' => 'a@b.com']);

        self::assertInstanceOf(Response::class, $result);
        self::assertSame(7, $result['id']);
        self::assertSame(201, $result->status());
        self::assertSame(120, $result->rateLimit()->limit);
    }

    public function testABodyFieldNamedStatusIsNotShadowed(): void
    {
        $client = $this->client(['status' => 200, 'body' => ['status' => 'draft']]);
        $result = $client->broadcasts->get(1);

        self::assertSame('draft', $result['status']);
        self::assertSame(200, $result->status());
    }

    public function testEmptyBodyBecomesAnEmptyResult(): void
    {
        $client = $this->client(['status' => 204, 'text' => '']);
        $result = $client->broadcasts->delete(1);
        self::assertSame([], $result instanceof Response ? $result->toArray() : $result);
    }

    public function testNonJson2xxBecomesEmptyRatherThanThrowing(): void
    {
        $client = $this->client(['status' => 200, 'text' => '<html>proxy</html>', 'headers' => ['content-type' => 'text/html']]);
        $result = $client->discovery->whoami();
        self::assertSame([], $result instanceof Response ? $result->toArray() : $result);
    }

    public function testRawReturnsTheBodyUntouched(): void
    {
        $client = $this->client(['status' => 200, 'text' => '# Skill', 'headers' => ['content-type' => 'text/plain; charset=utf-8']]);
        self::assertSame('# Skill', $client->discovery->skill());
    }

    public function testRawReturnsBinaryIntact(): void
    {
        $png = "\x89PNG\r\n";
        $client = $this->client(['status' => 200, 'text' => $png, 'headers' => ['content-type' => 'image/png']]);
        self::assertSame($png, $client->migration->downloadFileAsset(1));
    }

    // --- Error mapping ------------------------------------------------------

    /** @return list<array{0:int,1:class-string<\Throwable>,2:string}> */
    public static function errorCases(): array
    {
        return [
            [401, AuthenticationException::class, 'Authentication failed'],
            [403, AuthorizationException::class, 'Not authorized'],
            [404, NotFoundException::class, 'Resource not found'],
            [409, ConflictException::class, 'still being processed'],
            [422, ValidationException::class, 'Validation failed'],
        ];
    }

    // Attribute rather than an @dataProvider doc-comment: metadata in
    // doc-comments is deprecated in PHPUnit 11 and unsupported in 12.
    #[DataProvider('errorCases')]
    public function testStatusCodesMapToTypedExceptions(int $status, string $class, string $default): void
    {
        $client = $this->client(['status' => $status, 'text' => 'not json']);

        $this->expectException($class);
        $this->expectExceptionMessageMatches('/' . preg_quote($default, '/') . '/');
        $client->discovery->whoami();
    }

    public function testPrefersTheApiErrorMessage(): void
    {
        $client = $this->client(['status' => 404, 'body' => ['error' => 'Subscriber not found']]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Subscriber not found');
        $client->discovery->whoami();
    }

    public function testFormatsAnActiveModelErrorsHash(): void
    {
        $client = $this->client([
            'status' => 422,
            'body' => ['errors' => ['email' => ['is invalid', 'is taken'], 'name' => ['is required']]],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('email is invalid, is taken; name is required');
        $client->subscribers->create(['email' => 'x']);
    }

    public function testFormatsAnErrorsArray(): void
    {
        $client = $this->client(['status' => 422, 'body' => ['errors' => ['too short', 'too rude']]]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('too short, too rude');
        $client->subscribers->create(['email' => 'x']);
    }

    public function test429CarriesRetryAfter(): void
    {
        $client = $this->client(
            ['status' => 429, 'body' => ['error' => 'Slow down'], 'headers' => ['retry-after' => '7']],
            ['retryAttempts' => 1]
        );

        try {
            $client->discovery->whoami();
            self::fail('expected RateLimitException');
        } catch (RateLimitException $e) {
            self::assertSame(7, $e->retryAfter);
        }
    }

    public function test5xxNamesTheStatus(): void
    {
        $client = $this->client(['status' => 503, 'text' => ''], ['retryAttempts' => 1]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/Server error \(503\)/');
        $client->discovery->whoami();
    }

    // --- Redirects ----------------------------------------------------------

    public function testFollowsASameHostGetRedirect(): void
    {
        $client = $this->client([
            ['status' => 301, 'text' => '', 'headers' => ['location' => 'https://mail.example.com/api/v1/whoami/']],
            ['status' => 200, 'body' => ['ok' => true]],
        ]);

        self::assertTrue($client->discovery->whoami()['ok']);
        self::assertCount(2, $this->http->calls);
    }

    public function testResolvesARelativeLocation(): void
    {
        $client = $this->client([
            ['status' => 302, 'text' => '', 'headers' => ['location' => '/api/v2/whoami']],
            ['status' => 200, 'body' => ['ok' => true]],
        ]);

        $client->discovery->whoami();
        self::assertSame('https://mail.example.com/api/v2/whoami', $this->http->calls[1]['url']);
    }

    public function testRefusesACrossHostRedirectBecauseTheTokenWouldTravel(): void
    {
        $client = $this->client([
            ['status' => 301, 'text' => '', 'headers' => ['location' => 'https://evil.example.net/api/v1/whoami']],
        ]);

        try {
            $client->discovery->whoami();
            self::fail('expected ApiException');
        } catch (ApiException $e) {
            self::assertMatchesRegularExpression('/different host/', $e->getMessage());
            self::assertMatchesRegularExpression('/carries your API token/', $e->getMessage());
        }
        self::assertCount(1, $this->http->calls, 'must not have issued the cross-host request');
    }

    public function testHostComparisonIsCaseInsensitive(): void
    {
        $client = $this->client([
            ['status' => 301, 'text' => '', 'headers' => ['location' => 'https://MAIL.EXAMPLE.COM/api/v1/whoami']],
            ['status' => 200, 'body' => ['ok' => true]],
        ]);

        self::assertTrue($client->discovery->whoami()['ok']);
    }

    public function testNeverFollowsARedirectOnAWrite(): void
    {
        $client = $this->client([
            ['status' => 308, 'text' => '', 'headers' => ['location' => 'https://mail.example.com/api/v1/subscribers.json']],
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/writes are not followed automatically/');
        $client->subscribers->create(['email' => 'a@b.com']);
    }

    public function testRedirectWithoutALocationFailsClearly(): void
    {
        $client = $this->client([['status' => 301, 'text' => '', 'headers' => []]]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/no Location header/');
        $client->discovery->whoami();
    }

    public function testGivesUpAfterThreeRedirects(): void
    {
        $hop = ['status' => 301, 'text' => '', 'headers' => ['location' => 'https://mail.example.com/a']];
        $client = $this->client([$hop, $hop, $hop, $hop, $hop]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/Too many redirects \(3\)/');
        $client->discovery->whoami();
    }

    // --- Retries ------------------------------------------------------------

    public function testRetriesA5xxThenSucceeds(): void
    {
        $client = $this->client([
            ['status' => 500, 'text' => ''],
            ['status' => 200, 'body' => ['ok' => true]],
        ]);

        self::assertTrue($client->discovery->whoami()['ok']);
        self::assertCount(2, $this->http->calls);
    }

    public function testGivesUpAfterRetryAttempts(): void
    {
        $client = $this->client(['status' => 500, 'text' => ''], ['retryAttempts' => 3]);

        try {
            $client->discovery->whoami();
            self::fail('expected ApiException');
        } catch (ApiException) {
            // expected
        }
        self::assertCount(3, $this->http->calls);
    }

    public function testDoesNotRetryA422(): void
    {
        $client = $this->client(['status' => 422, 'body' => ['error' => 'nope']]);

        try {
            $client->subscribers->create(['email' => 'x']);
            self::fail('expected ValidationException');
        } catch (ValidationException) {
            // expected
        }
        self::assertCount(1, $this->http->calls, '422 is deterministic — retrying is pure latency');
    }

    public function testCapsALongRetryAfterAtMaxRetryDelay(): void
    {
        $client = $this->client(
            [
                ['status' => 429, 'text' => '', 'headers' => ['retry-after' => '3600']],
                ['status' => 200, 'body' => ['ok' => true]],
            ],
            ['maxRetryDelay' => 5, 'retryDelay' => 1]
        );

        $client->discovery->whoami();
        self::assertSame([5.0], $this->slept);
    }

    public function testATransportFailureIsRetriedThenRaised(): void
    {
        $client = $this->client(['throws' => new TimeoutException('Request timeout: connection refused')], ['retryAttempts' => 2]);

        $this->expectException(TimeoutException::class);
        try {
            $client->discovery->whoami();
        } finally {
            self::assertCount(2, $this->http->calls);
        }
    }

    // --- Warnings -----------------------------------------------------------

    /** @return array<string,mixed> */
    private static function warnedBody(): array
    {
        return ['body' => [
            'id' => 1,
            'warnings' => [['code' => 'unrecognized_parameter', 'param' => 'subscriber.foo', 'message' => 'Unknown']],
        ]];
    }

    public function testLogModeWarnsThroughTheLoggerAndReturns(): void
    {
        $logger = new RecordingLogger();
        $client = $this->client(self::warnedBody(), ['warningsMode' => 'log', 'logger' => $logger]);

        $result = $client->subscribers->create(['email' => 'a@b.com']);
        self::assertSame(1, $result['id']);
        self::assertCount(1, $logger->warnings);
        self::assertStringContainsString('unrecognized_parameter', $logger->warnings[0]);
    }

    public function testRaiseModeThrowsAndCarriesTheResponse(): void
    {
        $client = $this->client(self::warnedBody(), ['warningsMode' => 'raise']);

        try {
            $client->subscribers->create(['email' => 'a@b.com']);
            self::fail('expected WarningException');
        } catch (WarningException $e) {
            self::assertCount(1, $e->warnings);
            self::assertMatchesRegularExpression('/API returned 1 warning\(s\)/', $e->getMessage());
            // The write already happened — the response must be reachable.
            self::assertSame(1, $e->response['id']);
        }
    }

    public function testIgnoreModeIsSilent(): void
    {
        $logger = new RecordingLogger();
        $client = $this->client(self::warnedBody(), ['warningsMode' => 'ignore', 'logger' => $logger]);

        $result = $client->subscribers->create(['email' => 'a@b.com']);
        self::assertSame([], $logger->warnings);
        self::assertCount(1, $result->warnings());
    }

    public function testDebugLoggingNeverEmitsTheBodyOrCredentials(): void
    {
        $logger = new RecordingLogger();
        $client = $this->client([], ['debug' => true, 'logger' => $logger]);

        $client->subscribers->create(['email' => 'secret@example.com']);

        $joined = implode("\n", $logger->debugs);
        self::assertStringNotContainsString('secret@example.com', $joined);
        self::assertStringNotContainsString('test-token', $joined);
        self::assertStringContainsString('body redacted', $joined);
    }
}
