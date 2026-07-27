<?php

declare(strict_types=1);

namespace Broadcast;

use Broadcast\Resources\Autopilots;
use Broadcast\Resources\Broadcasts;
use Broadcast\Resources\Discovery;
use Broadcast\Resources\EmailServers;
use Broadcast\Resources\Migration;
use Broadcast\Resources\OptInForms;
use Broadcast\Resources\Segments;
use Broadcast\Resources\Sequences;
use Broadcast\Resources\Subscribers;
use Broadcast\Resources\Templates;
use Broadcast\Resources\Transactionals;
use Broadcast\Resources\WebhookEndpoints;

/**
 * Client for the Broadcast API.
 *
 *   $client = new Broadcast\Client([
 *       'apiToken' => '...',
 *       'host' => 'https://mail.example.com',
 *   ]);
 *   $client->subscribers->create(['email' => 'ada@example.com']);
 */
final class Client
{
    public readonly Configuration $config;

    private Connection $connection;

    /** Channel override for withChannel(). */
    private string|int|null $channelOverride = null;

    public readonly Subscribers $subscribers;
    public readonly Sequences $sequences;
    public readonly Broadcasts $broadcasts;
    public readonly Segments $segments;
    public readonly Templates $templates;
    public readonly WebhookEndpoints $webhookEndpoints;
    public readonly Transactionals $transactionals;
    public readonly OptInForms $optInForms;
    public readonly EmailServers $emailServers;
    public readonly Autopilots $autopilots;
    public readonly Discovery $discovery;

    /** Read-only export endpoints. Requires an admin (system) API token. */
    public readonly Migration $migration;

    /** @param array<string,mixed> $options */
    public function __construct(array $options = [])
    {
        $this->config = new Configuration($options);
        $this->config->validate();
        $this->connection = new Connection($this->config);

        $this->subscribers = new Subscribers($this);
        $this->sequences = new Sequences($this);
        $this->broadcasts = new Broadcasts($this);
        $this->segments = new Segments($this);
        $this->templates = new Templates($this);
        $this->webhookEndpoints = new WebhookEndpoints($this);
        $this->transactionals = new Transactionals($this);
        $this->optInForms = new OptInForms($this);
        $this->emailServers = new EmailServers($this);
        $this->autopilots = new Autopilots($this);
        $this->discovery = new Discovery($this);
        $this->migration = new Migration($this);
    }

    // --- Channel scoping (admin/system tokens) ---

    /**
     * Run a callable with a temporary broadcast_channel_id applied to every
     * request inside it, restoring the previous scope afterwards — including
     * when the callable throws.
     *
     *   $client->withChannel(123, fn () => $client->emailServers->list());
     *
     * The override lives on the client instance; use a separate client per
     * channel, or pass broadcast_channel_id explicitly, when working on
     * several channels concurrently.
     */
    public function withChannel(string|int $broadcastChannelId, callable $callback): mixed
    {
        $previous = $this->channelOverride;
        $this->channelOverride = $broadcastChannelId;

        try {
            return $callback($this);
        } finally {
            $this->channelOverride = $previous;
        }
    }

    // --- Transactional email (convenience shims) ---

    /**
     * Thin wrapper around transactionals->create(). Use that directly for
     * template_id, double_opt_in, preheader and idempotency_key.
     */
    public function sendEmail(
        string $to,
        ?string $subject = null,
        ?string $body = null,
        ?string $replyTo = null
    ): mixed {
        return $this->transactionals->create([
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'reply_to' => $replyTo,
        ]);
    }

    public function getEmail(string|int $id): mixed
    {
        return $this->transactionals->get($id);
    }

    // --- Discovery (convenience shims) ---

    public function whoami(): mixed
    {
        return $this->discovery->whoami();
    }

    public function status(): mixed
    {
        return $this->discovery->status();
    }

    public function prime(): mixed
    {
        return $this->discovery->prime();
    }

    public function skill(): string
    {
        return $this->discovery->skill();
    }

    // --- Internal ---

    /**
     * @internal
     * @param array<string,mixed> $headers
     */
    public function request(
        string $method,
        string $path,
        mixed $bodyOrParams = null,
        array $headers = [],
        bool $raw = false
    ): mixed {
        return $this->connection->request($method, $path, $this->injectChannelScope($bodyOrParams), $headers, $raw);
    }

    private function activeChannelId(): string|int|null
    {
        return $this->channelOverride ?? $this->config->broadcastChannelId;
    }

    /**
     * Auto-include broadcast_channel_id when configured (or set via
     * withChannel) and the caller has not already specified one.
     */
    private function injectChannelScope(mixed $bodyOrParams): mixed
    {
        $channelId = $this->activeChannelId();
        if ($channelId === null) {
            return $bodyOrParams;
        }

        $payload = is_array($bodyOrParams) ? $bodyOrParams : [];
        if (isset($payload['broadcast_channel_id'])) {
            return $payload;
        }

        $payload['broadcast_channel_id'] = $channelId;

        return $payload;
    }
}
