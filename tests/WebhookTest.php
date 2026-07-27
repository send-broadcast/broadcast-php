<?php

declare(strict_types=1);

namespace Broadcast\Tests;

use Broadcast\Webhook;
use PHPUnit\Framework\TestCase as BaseTestCase;

final class WebhookTest extends BaseTestCase
{
    private const SECRET = 'whsec_test_secret';
    private const NOW = 1800000000;

    private static function payload(): string
    {
        return json_encode(['type' => 'email.delivered', 'data' => ['id' => 1]], JSON_THROW_ON_ERROR);
    }

    private static function sign(string $payload, int $timestamp, string $secret = self::SECRET): string
    {
        return base64_encode(hash_hmac('sha256', $timestamp . '.' . $payload, $secret, true));
    }

    public function testAcceptsACorrectlySignedPayload(): void
    {
        $signature = 'v1,' . self::sign(self::payload(), self::NOW);
        self::assertTrue(Webhook::verify(self::payload(), $signature, (string) self::NOW, self::SECRET, self::NOW));
    }

    public function testRejectsADifferentSecret(): void
    {
        $signature = 'v1,' . self::sign(self::payload(), self::NOW, 'wrong-secret');
        self::assertFalse(Webhook::verify(self::payload(), $signature, (string) self::NOW, self::SECRET, self::NOW));
    }

    public function testRejectsATamperedPayload(): void
    {
        $signature = 'v1,' . self::sign(self::payload(), self::NOW);
        $tampered = json_encode(['type' => 'email.delivered', 'data' => ['id' => 999]], JSON_THROW_ON_ERROR);
        self::assertFalse(Webhook::verify($tampered, $signature, (string) self::NOW, self::SECRET, self::NOW));
    }

    public function testRejectsAStaleTimestamp(): void
    {
        $old = self::NOW - 301;
        $signature = 'v1,' . self::sign(self::payload(), $old);
        self::assertFalse(Webhook::verify(self::payload(), $signature, (string) $old, self::SECRET, self::NOW));
    }

    public function testAcceptsTheEdgeOfTheWindow(): void
    {
        $edge = self::NOW - 300;
        $signature = 'v1,' . self::sign(self::payload(), $edge);
        self::assertTrue(Webhook::verify(self::payload(), $signature, (string) $edge, self::SECRET, self::NOW));
    }

    public function testRejectsAFutureTimestamp(): void
    {
        $future = self::NOW + 301;
        $signature = 'v1,' . self::sign(self::payload(), $future);
        self::assertFalse(Webhook::verify(self::payload(), $signature, (string) $future, self::SECRET, self::NOW));
    }

    public function testRejectsASignatureWithoutThePrefix(): void
    {
        $signature = self::sign(self::payload(), self::NOW);
        self::assertFalse(Webhook::verify(self::payload(), $signature, (string) self::NOW, self::SECRET, self::NOW));
    }

    public function testRejectsAnEmptySignatureAfterThePrefix(): void
    {
        self::assertFalse(Webhook::verify(self::payload(), 'v1,', (string) self::NOW, self::SECRET, self::NOW));
    }

    public function testRejectsNullArgumentsRatherThanThrowing(): void
    {
        $signature = 'v1,' . self::sign(self::payload(), self::NOW);
        self::assertFalse(Webhook::verify(null, $signature, (string) self::NOW, self::SECRET, self::NOW));
        self::assertFalse(Webhook::verify(self::payload(), null, (string) self::NOW, self::SECRET, self::NOW));
        self::assertFalse(Webhook::verify(self::payload(), $signature, null, self::SECRET, self::NOW));
        self::assertFalse(Webhook::verify(self::payload(), $signature, (string) self::NOW, null, self::NOW));
    }

    public function testRejectsAWrongLengthSignatureWithoutThrowing(): void
    {
        self::assertFalse(Webhook::verify(self::payload(), 'v1,c2hvcnQ=', (string) self::NOW, self::SECRET, self::NOW));
    }

    public function testRejectsANonNumericTimestamp(): void
    {
        // PHP would cast "not-a-number" to 0 silently, which sits far outside
        // the tolerance window — but relying on that is luck, not a check.
        $signature = 'v1,' . self::sign(self::payload(), self::NOW);
        self::assertFalse(Webhook::verify(self::payload(), $signature, 'not-a-number', self::SECRET, self::NOW));
    }

    public function testDefaultsToTheCurrentTime(): void
    {
        $current = time();
        $signature = 'v1,' . self::sign(self::payload(), $current);
        self::assertTrue(Webhook::verify(self::payload(), $signature, (string) $current, self::SECRET));
    }

    public function testComputeSignatureMatchesAnIndependentImplementation(): void
    {
        self::assertSame(
            self::sign(self::payload(), self::NOW),
            Webhook::computeSignature(self::payload(), self::NOW, self::SECRET)
        );
    }

    public function testEventTypeCounts(): void
    {
        self::assertCount(8, Webhook::EMAIL_EVENTS);
        self::assertCount(7, Webhook::SUBSCRIBER_EVENTS);
        self::assertCount(8, Webhook::BROADCAST_EVENTS);
        self::assertCount(7, Webhook::SEQUENCE_EVENTS);
        self::assertCount(2, Webhook::SYSTEM_EVENTS);
        self::assertCount(32, Webhook::eventTypes());
    }

    public function testEventTypesHaveNoDuplicates(): void
    {
        $types = Webhook::eventTypes();
        self::assertCount(count($types), array_unique($types));
    }

    public function testEventTypesMatchTheServerSideNames(): void
    {
        foreach ([
            'email.delivery_delayed',
            'broadcast.partial_failure',
            'sequence.subscriber_completed',
            'message.attempt.exhausted',
            'test.webhook',
        ] as $name) {
            self::assertContains($name, Webhook::eventTypes());
        }
    }
}
