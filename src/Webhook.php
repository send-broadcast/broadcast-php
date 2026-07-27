<?php

declare(strict_types=1);

namespace Broadcast;

/** Inbound webhook verification. */
final class Webhook
{
    public const TIMESTAMP_TOLERANCE = 300; // 5 minutes

    /**
     * Every event type a webhook endpoint can subscribe to, mirroring
     * WebhookEndpoint::AVAILABLE_EVENT_TYPES server-side. Use these when
     * creating an endpoint — an unknown event type is dropped silently.
     */
    public const EMAIL_EVENTS = [
        'email.sent',
        'email.delivered',
        'email.delivery_delayed',
        'email.complained',
        'email.bounced',
        'email.opened',
        'email.clicked',
        'email.failed',
    ];

    public const SUBSCRIBER_EVENTS = [
        'subscriber.created',
        'subscriber.updated',
        'subscriber.deleted',
        'subscriber.subscribed',
        'subscriber.unsubscribed',
        'subscriber.bounced',
        'subscriber.complained',
    ];

    public const BROADCAST_EVENTS = [
        'broadcast.scheduled',
        'broadcast.queueing',
        'broadcast.sending',
        'broadcast.sent',
        'broadcast.failed',
        'broadcast.partial_failure',
        'broadcast.aborted',
        'broadcast.paused',
    ];

    public const SEQUENCE_EVENTS = [
        'sequence.subscriber_added',
        'sequence.subscriber_completed',
        'sequence.subscriber_moved',
        'sequence.subscriber_removed',
        'sequence.subscriber_paused',
        'sequence.subscriber_resumed',
        'sequence.subscriber_error',
    ];

    /** Delivery-machinery events, not content events. */
    public const SYSTEM_EVENTS = ['message.attempt.exhausted', 'test.webhook'];

    /** @return list<string> */
    public static function eventTypes(): array
    {
        return array_merge(
            self::EMAIL_EVENTS,
            self::SUBSCRIBER_EVENTS,
            self::BROADCAST_EVENTS,
            self::SEQUENCE_EVENTS,
            self::SYSTEM_EVENTS
        );
    }

    /**
     * Verify an inbound webhook.
     *
     * Returns false rather than throwing for every rejection — a missing
     * header, a stale timestamp, a bad signature. A handler should answer 401
     * for all of them identically, and distinguishing them in the return type
     * invites leaking which check failed.
     *
     * $payload must be the raw request body, exactly as received.
     * Re-serialising a parsed array changes the bytes and verification fails.
     */
    public static function verify(
        ?string $payload,
        ?string $signatureHeader,
        ?string $timestampHeader,
        ?string $secret,
        ?int $now = null
    ): bool {
        if ($payload === null || $signatureHeader === null || $timestampHeader === null || $secret === null) {
            return false;
        }

        if (!preg_match('/\A-?\d+\z/', trim($timestampHeader))) {
            return false;
        }
        $timestamp = (int) $timestampHeader;

        $currentTime = $now ?? time();
        if (!self::timestampValid($timestamp, $currentTime)) {
            return false;
        }

        $actual = self::extractSignature($signatureHeader);
        if ($actual === null) {
            return false;
        }

        return hash_equals(self::computeSignature($payload, $timestamp, $secret), $actual);
    }

    public static function computeSignature(string $payload, int $timestamp, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $timestamp . '.' . $payload, $secret, true));
    }

    public static function timestampValid(int $timestamp, ?int $currentTime = null): bool
    {
        $currentTime ??= time();

        return abs($currentTime - $timestamp) <= self::TIMESTAMP_TOLERANCE;
    }

    public static function extractSignature(string $header): ?string
    {
        if (!str_starts_with($header, 'v1,')) {
            return null;
        }

        $signature = substr($header, 3);

        return $signature === '' ? null : $signature;
    }
}
