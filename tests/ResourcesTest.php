<?php

declare(strict_types=1);

namespace Broadcast\Tests;

/**
 * Wire-shape parity with broadcast-ruby, operation by operation.
 *
 * Every assertion is method + path + body, because those are what the API sees.
 * The .json suffixes on subscribers/segments/transactionals are not cosmetic —
 * they are what the Ruby gem sends, and the coverage report matches on path.
 */
final class ResourcesTest extends TestCase
{
    // --- Discovery ----------------------------------------------------------

    public function testDiscoveryEndpoints(): void
    {
        $client = $this->client();

        $client->discovery->whoami();
        self::assertSame('/api/v1/whoami', $this->http->last()['path']);

        $client->discovery->status();
        self::assertSame('/api/v1/status', $this->http->last()['path']);

        $client->discovery->prime();
        self::assertSame('/api/v1/prime', $this->http->last()['path']);
    }

    public function testClientLevelShims(): void
    {
        $client = $this->client();
        $client->whoami();
        self::assertSame('/api/v1/whoami', $this->http->last()['path']);
        $client->status();
        self::assertSame('/api/v1/status', $this->http->last()['path']);
        $client->prime();
        self::assertSame('/api/v1/prime', $this->http->last()['path']);
    }

    // --- Subscribers --------------------------------------------------------

    public function testSubscribersListAndFind(): void
    {
        $client = $this->client();

        $client->subscribers->list(['page' => 2, 'tags' => ['vip']]);
        self::assertSame('/api/v1/subscribers.json', $this->http->last()['path']);
        self::assertSame(['vip'], $this->http->last()['query']['tags']);

        $client->subscribers->find('a@b.com');
        self::assertSame('/api/v1/subscribers/find.json', $this->http->last()['path']);
        self::assertSame('a@b.com', $this->http->last()['query']['email']);
    }

    public function testSubscribersCreateWrapsUnderSubscriber(): void
    {
        $client = $this->client();
        $client->subscribers->create(['email' => 'a@b.com', 'first_name' => 'Ada']);

        self::assertSame('POST', $this->http->last()['method']);
        self::assertSame(
            ['subscriber' => ['email' => 'a@b.com', 'first_name' => 'Ada']],
            $this->http->last()['body']
        );
    }

    public function testSubscribersCreateLiftsDoubleOptInToTopLevel(): void
    {
        $client = $this->client();
        $client->subscribers->create([
            'email' => 'a@b.com',
            'double_opt_in' => true,
            'confirmation_template_id' => 7,
        ]);

        self::assertSame([
            'subscriber' => ['email' => 'a@b.com'],
            'double_opt_in' => true,
            'confirmation_template_id' => 7,
        ], $this->http->last()['body']);
    }

    public function testSubscribersUpdate(): void
    {
        $client = $this->client();
        $client->subscribers->update('a@b.com', ['first_name' => 'Grace']);

        self::assertSame('PATCH', $this->http->last()['method']);
        self::assertSame(
            ['email' => 'a@b.com', 'subscriber' => ['first_name' => 'Grace']],
            $this->http->last()['body']
        );
    }

    public function testSubscriberTagOperations(): void
    {
        $client = $this->client();

        $client->subscribers->addTags('a@b.com', ['vip']);
        self::assertSame('/api/v1/subscribers/add_tag.json', $this->http->last()['path']);
        self::assertSame(['email' => 'a@b.com', 'tags' => ['vip']], $this->http->last()['body']);

        $client->subscribers->removeTags('a@b.com', ['vip']);
        self::assertSame('DELETE', $this->http->last()['method']);
        self::assertSame('/api/v1/subscribers/remove_tag.json', $this->http->last()['path']);
    }

    public function testSubscriberLifecycleActions(): void
    {
        $client = $this->client();

        foreach (['activate', 'deactivate', 'unsubscribe', 'resubscribe', 'redact'] as $action) {
            $client->subscribers->{$action}('a@b.com');
            self::assertSame('POST', $this->http->last()['method'], $action);
            self::assertSame("/api/v1/subscribers/{$action}.json", $this->http->last()['path']);
            self::assertSame(['email' => 'a@b.com'], $this->http->last()['body']);
        }
    }

    // --- Broadcasts ---------------------------------------------------------

    public function testBroadcastsCrudIsUnwrapped(): void
    {
        $client = $this->client();

        $client->broadcasts->list();
        self::assertSame('/api/v1/broadcasts', $this->http->last()['path']);

        $client->broadcasts->get(5);
        self::assertSame('/api/v1/broadcasts/5', $this->http->last()['path']);

        $client->broadcasts->create(['subject' => 'Hello']);
        self::assertSame(['subject' => 'Hello'], $this->http->last()['body']);

        $client->broadcasts->update(5, ['subject' => 'Edited']);
        self::assertSame('PATCH', $this->http->last()['method']);

        $client->broadcasts->delete(5);
        self::assertSame('DELETE', $this->http->last()['method']);
    }

    public function testBroadcastSendScheduleCancel(): void
    {
        $client = $this->client();

        $client->broadcasts->send(5);
        self::assertSame('/api/v1/broadcasts/5/send_broadcast', $this->http->last()['path']);

        $client->broadcasts->schedule(5, '2026-08-01T09:00:00Z', 'UTC');
        self::assertSame('/api/v1/broadcasts/5/schedule_broadcast', $this->http->last()['path']);
        self::assertSame(
            ['scheduled_send_at' => '2026-08-01T09:00:00Z', 'scheduled_timezone' => 'UTC'],
            $this->http->last()['body']
        );

        $client->broadcasts->cancelSchedule(5);
        self::assertSame('/api/v1/broadcasts/5/cancel_schedule', $this->http->last()['path']);
    }

    public function testBroadcastStatistics(): void
    {
        $client = $this->client();

        $client->broadcasts->statistics(5);
        self::assertSame('/api/v1/broadcasts/5/statistics', $this->http->last()['path']);

        $client->broadcasts->statisticsTimeline(5, ['interval' => 'hour']);
        self::assertSame('/api/v1/broadcasts/5/statistics/timeline', $this->http->last()['path']);

        $client->broadcasts->statisticsLinks(5);
        self::assertSame('/api/v1/broadcasts/5/statistics/links', $this->http->last()['path']);
    }

    // --- Sequences ----------------------------------------------------------

    public function testSequencesCrudAndSteps(): void
    {
        $client = $this->client();

        $client->sequences->list();
        self::assertSame('/api/v1/sequences', $this->http->last()['path']);

        $client->sequences->get(3);
        self::assertArrayNotHasKey('include_steps', $this->http->last()['query']);

        $client->sequences->get(3, true);
        self::assertSame('true', $this->http->last()['query']['include_steps']);

        $client->sequences->addSubscriber(3, ['email' => 'a@b.com']);
        self::assertSame('/api/v1/sequences/3/add_subscriber', $this->http->last()['path']);

        $client->sequences->removeSubscriber(3, 'a@b.com');
        self::assertSame('DELETE', $this->http->last()['method']);
        self::assertSame(['email' => 'a@b.com'], $this->http->last()['body']);

        $client->sequences->listSubscribers(3, 2);
        self::assertSame('2', $this->http->last()['query']['page']);

        $client->sequences->listSteps(3);
        self::assertSame('/api/v1/sequences/3/steps', $this->http->last()['path']);

        $client->sequences->getStep(3, 9);
        self::assertSame('/api/v1/sequences/3/steps/9', $this->http->last()['path']);

        $client->sequences->createStep(3, ['subject' => 'Day 1']);
        self::assertSame('POST', $this->http->last()['method']);

        $client->sequences->updateStep(3, 9, ['subject' => 'Day 2']);
        self::assertSame('PATCH', $this->http->last()['method']);

        $client->sequences->moveStep(3, 9, 4);
        self::assertSame('/api/v1/sequences/3/steps/9/move', $this->http->last()['path']);
        self::assertSame(['under_id' => 4], $this->http->last()['body']);

        $client->sequences->deleteStep(3, 9);
        self::assertSame('DELETE', $this->http->last()['method']);
    }

    // --- Segments, templates, forms -----------------------------------------

    public function testSegments(): void
    {
        $client = $this->client();

        $client->segments->list();
        self::assertSame('/api/v1/segments.json', $this->http->last()['path']);

        $client->segments->get(2);
        self::assertSame('/api/v1/segments/2.json', $this->http->last()['path']);

        $client->segments->create(['name' => 'VIPs']);
        self::assertSame('/api/v1/segments', $this->http->last()['path']);
        self::assertSame(['segment' => ['name' => 'VIPs']], $this->http->last()['body']);

        $client->segments->update(2, ['name' => 'Renamed']);
        self::assertSame(['segment' => ['name' => 'Renamed']], $this->http->last()['body']);
    }

    public function testTemplates(): void
    {
        $client = $this->client();

        $client->templates->create(['label' => 'Welcome']);
        self::assertSame(['template' => ['label' => 'Welcome']], $this->http->last()['body']);

        $settings = ['confirmed' => ['heading' => "You're in", 'body' => 'Thanks.']];
        $client->templates->create(['label' => 'C', 'confirmation_page_settings' => $settings]);
        self::assertSame($settings, $this->http->last()['body']['template']['confirmation_page_settings']);
    }

    public function testOptInForms(): void
    {
        $client = $this->client();

        $client->optInForms->create(['label' => 'Footer']);
        self::assertSame(['opt_in_form' => ['label' => 'Footer']], $this->http->last()['body']);

        $client->optInForms->analytics(6, '2026-01-01', '2026-02-01');
        self::assertSame('/api/v1/opt_in_forms/6/analytics', $this->http->last()['path']);
        self::assertSame('2026-01-01', $this->http->last()['query']['start_date']);

        $client->optInForms->analytics(6);
        self::assertSame([], $this->http->last()['query']);

        $client->optInForms->createVariant(6, 'B', 50);
        self::assertSame(['name' => 'B', 'weight' => 50], $this->http->last()['body']);

        $client->optInForms->duplicate(6, 'Copy');
        self::assertSame(['label' => 'Copy'], $this->http->last()['body']);
    }

    public function testAnalyticsCoercesADateTime(): void
    {
        $client = $this->client();
        $client->optInForms->analytics(6, new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        self::assertSame('2026-01-01T00:00:00+00:00', $this->http->last()['query']['start_date']);
    }

    // --- Email servers ------------------------------------------------------

    public function testEmailServers(): void
    {
        $client = $this->client();

        $client->emailServers->list(10, 5);
        self::assertSame('10', $this->http->last()['query']['limit']);

        $client->emailServers->create(['name' => 'SES']);
        self::assertSame(['email_server' => ['name' => 'SES']], $this->http->last()['body']);

        $client->emailServers->testConnection(8);
        self::assertSame('/api/v1/email_servers/8/test_connection', $this->http->last()['path']);

        $client->emailServers->copyToChannel(8, 42);
        self::assertSame(['target_channel_id' => 42], $this->http->last()['body']);
    }

    public function testUpdateStripsBulletMaskedCredentials(): void
    {
        $logger = new RecordingLogger();
        $client = $this->client([], ['logger' => $logger]);

        $client->emailServers->update(8, [
            'name' => 'Renamed',
            'smtp_password' => '••••••••',
            'aws_secret_access_key' => 'AKIA••••••••WXYZ',
        ]);

        self::assertSame(['email_server' => ['name' => 'Renamed']], $this->http->last()['body']);
        self::assertCount(2, $logger->warnings);
    }

    public function testARealCredentialIsSentThrough(): void
    {
        $client = $this->client();
        $client->emailServers->update(8, ['smtp_password' => 'genuinely-new-password']);
        self::assertSame(
            ['email_server' => ['smtp_password' => 'genuinely-new-password']],
            $this->http->last()['body']
        );
    }

    public function testOnlyKnownCredentialFieldsAreScrubbed(): void
    {
        $client = $this->client();
        $client->emailServers->update(8, ['name' => '••••••••']);
        self::assertSame(['email_server' => ['name' => '••••••••']], $this->http->last()['body']);
    }

    // --- Webhook endpoints & transactionals ---------------------------------

    public function testWebhookEndpoints(): void
    {
        $client = $this->client();

        $client->webhookEndpoints->create(['url' => 'https://x.com/hook']);
        self::assertSame(['webhook_endpoint' => ['url' => 'https://x.com/hook']], $this->http->last()['body']);

        $client->webhookEndpoints->test(1);
        self::assertSame(['event_type' => 'test.webhook'], $this->http->last()['body']);

        $client->webhookEndpoints->test(1, 'email.sent');
        self::assertSame(['event_type' => 'email.sent'], $this->http->last()['body']);

        $client->webhookEndpoints->deliveries(1, ['page' => 2]);
        self::assertSame('/api/v1/webhook_endpoints/1/deliveries', $this->http->last()['path']);
    }

    public function testTransactionalCreateIsFlat(): void
    {
        $client = $this->client();
        $client->transactionals->create([
            'to' => 'a@b.com',
            'subject' => 'Receipt',
            'body' => '<p>T</p>',
            'reply_to' => 's@b.com',
        ]);

        self::assertSame('/api/v1/transactionals.json', $this->http->last()['path']);
        self::assertSame(
            ['to' => 'a@b.com', 'subject' => 'Receipt', 'body' => '<p>T</p>', 'reply_to' => 's@b.com'],
            $this->http->last()['body']
        );
    }

    public function testTransactionalOmitsUnprovidedKeys(): void
    {
        $client = $this->client();
        $client->transactionals->create(['to' => 'a@b.com', 'subject' => 'Hi', 'body' => 'x']);

        $keys = array_keys($this->http->last()['body']);
        sort($keys);
        self::assertSame(['body', 'subject', 'to'], $keys);
    }

    public function testIdempotencyKeyIsAHeaderNotABodyField(): void
    {
        $client = $this->client();
        $client->transactionals->create([
            'to' => 'a@b.com', 'subject' => 'S', 'body' => 'B', 'idempotency_key' => 'order-42',
        ]);

        self::assertSame('order-42', $this->http->last()['headers']['idempotency-key']);
        self::assertArrayNotHasKey('idempotency_key', $this->http->last()['body']);
    }

    public function testBlankIdempotencyKeySendsNoHeader(): void
    {
        $client = $this->client();
        $client->transactionals->create(['to' => 'a@b.com', 'subject' => 'S', 'body' => 'B', 'idempotency_key' => '   ']);
        self::assertArrayNotHasKey('idempotency-key', $this->http->last()['headers']);
    }

    public function testRejectsAnOverLongIdempotencyKeyBeforeSending(): void
    {
        $client = $this->client();

        try {
            $client->transactionals->create([
                'to' => 'a@b.com', 'subject' => 'S', 'body' => 'B', 'idempotency_key' => str_repeat('x', 256),
            ]);
            self::fail('expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            self::assertMatchesRegularExpression('/255 characters or fewer/', $e->getMessage());
        }
        self::assertCount(0, $this->http->calls, 'must not have issued the request');
    }

    public function testAcceptsExactly255(): void
    {
        $client = $this->client();
        $client->transactionals->create([
            'to' => 'a@b.com', 'subject' => 'S', 'body' => 'B', 'idempotency_key' => str_repeat('x', 255),
        ]);
        self::assertSame(255, strlen($this->http->last()['headers']['idempotency-key']));
    }

    public function testGetTransactional(): void
    {
        $client = $this->client();
        $client->transactionals->get(11);
        self::assertSame('/api/v1/transactionals/11.json', $this->http->last()['path']);
    }

    public function testSendEmailShim(): void
    {
        $client = $this->client();
        $client->sendEmail('a@b.com', 'S', 'B');
        self::assertSame('/api/v1/transactionals.json', $this->http->last()['path']);
        self::assertSame(['to' => 'a@b.com', 'subject' => 'S', 'body' => 'B'], $this->http->last()['body']);
    }

    // --- Autopilots ---------------------------------------------------------

    public function testAutopilotsCrudAndLifecycle(): void
    {
        $client = $this->client();

        $client->autopilots->list();
        self::assertSame('/api/v1/autopilots', $this->http->last()['path']);

        $client->autopilots->create(['name' => 'Weekly']);
        self::assertSame(['autopilot' => ['name' => 'Weekly']], $this->http->last()['body']);

        foreach (['activate', 'pause', 'deactivate'] as $action) {
            $client->autopilots->{$action}(2);
            self::assertSame("/api/v1/autopilots/2/{$action}", $this->http->last()['path']);
        }

        $client->autopilots->triggerRun(2);
        self::assertSame('/api/v1/autopilots/2/trigger_run', $this->http->last()['path']);

        $client->autopilots->runs(2, ['limit' => 10]);
        self::assertSame('/api/v1/autopilots/2/runs', $this->http->last()['path']);

        $client->autopilots->delete(2);
        self::assertSame('DELETE', $this->http->last()['method']);
    }

    public function testAutopilotUpdateStripsAMaskedKey(): void
    {
        $logger = new RecordingLogger();
        $client = $this->client([], ['logger' => $logger]);

        $client->autopilots->update(2, ['openrouter_api_key' => '••••••••', 'ai_model' => 'openai/gpt-4o']);

        self::assertSame(['autopilot' => ['ai_model' => 'openai/gpt-4o']], $this->http->last()['body']);
        self::assertCount(1, $logger->warnings);
    }

    public function testARealAutopilotKeyIsSent(): void
    {
        $client = $this->client();
        $client->autopilots->update(2, ['openrouter_api_key' => 'sk-or-v1-realkey']);
        self::assertSame(['autopilot' => ['openrouter_api_key' => 'sk-or-v1-realkey']], $this->http->last()['body']);
    }

    // --- Migration ----------------------------------------------------------

    public function testAllEighteenCollections(): void
    {
        $client = $this->client(['body' => ['data' => [], 'pagination' => ['has_more' => false]]]);

        foreach (\Broadcast\Resources\Migration::COLLECTIONS as $method => $path) {
            $client->migration->{$method}(['limit' => 10]);
            self::assertSame("/api/migration/v1/{$path}", $this->http->last()['path'], $method);
        }
    }

    public function testUnknownCollectionFailsLoudly(): void
    {
        $client = $this->client();
        $this->expectException(\BadMethodCallException::class);
        $client->migration->notARealCollection();
    }

    public function testManifest(): void
    {
        $client = $this->client();
        $client->migration->manifest(['days_history' => 30]);
        self::assertSame('/api/migration/v1/manifest', $this->http->last()['path']);
        self::assertSame('30', $this->http->last()['query']['days_history']);
    }

    public function testEachRecordPagesUntilHasMoreIsFalse(): void
    {
        $client = $this->client([
            ['body' => ['data' => [['id' => 1], ['id' => 2]], 'pagination' => ['has_more' => true, 'limit' => 2]]],
            ['body' => ['data' => [['id' => 3]], 'pagination' => ['has_more' => false, 'limit' => 2]]],
        ]);

        $seen = [];
        foreach ($client->migration->eachRecord('subscribers', 2) as $record) {
            $seen[] = $record['id'];
        }

        self::assertSame([1, 2, 3], $seen);
        self::assertSame('0', $this->http->calls[0]['query']['offset']);
        self::assertSame('2', $this->http->calls[1]['query']['offset']);
    }

    public function testEachRecordAdvancesByTheServerReportedLimit(): void
    {
        // The server clamps limit to 250; advancing by the requested 1000 would skip records.
        $first = array_map(static fn (int $i) => ['id' => $i], range(0, 249));
        $client = $this->client([
            ['body' => ['data' => $first, 'pagination' => ['has_more' => true, 'limit' => 250]]],
            ['body' => ['data' => [['id' => 250]], 'pagination' => ['has_more' => false, 'limit' => 250]]],
        ]);

        $seen = iterator_to_array($client->migration->eachRecord('subscribers', 1000), false);

        self::assertCount(251, $seen);
        self::assertSame('250', $this->http->calls[1]['query']['offset']);
    }

    public function testEachRecordStopsOnAZeroAdvance(): void
    {
        // A zero advance must break the loop rather than spin forever.
        $client = $this->client(['body' => ['data' => [['id' => 1]], 'pagination' => ['has_more' => true, 'limit' => 0]]]);

        $seen = iterator_to_array($client->migration->eachRecord('subscribers'), false);

        self::assertCount(1, $seen);
        self::assertCount(1, $this->http->calls);
    }

    // --- Channel scoping ----------------------------------------------------

    public function testChannelIdIsInjectedIntoQueryParams(): void
    {
        $client = $this->client([], ['broadcastChannelId' => 42]);
        $client->migration->subscribers();
        self::assertSame('42', $this->http->last()['query']['broadcast_channel_id']);
    }

    public function testChannelIdIsInjectedIntoBodies(): void
    {
        $client = $this->client([], ['broadcastChannelId' => 42]);
        $client->broadcasts->create(['subject' => 'Hi']);
        self::assertSame(['subject' => 'Hi', 'broadcast_channel_id' => 42], $this->http->last()['body']);
    }

    public function testAnExplicitChannelIdWins(): void
    {
        $client = $this->client([], ['broadcastChannelId' => 42]);
        $client->migration->subscribers(['broadcast_channel_id' => 7]);
        self::assertSame('7', $this->http->last()['query']['broadcast_channel_id']);
    }

    public function testWithChannelScopesOnlyItsBlock(): void
    {
        $client = $this->client();

        $client->withChannel(99, function () use ($client): void {
            $client->migration->subscribers();
        });
        self::assertSame('99', $this->http->last()['query']['broadcast_channel_id']);

        $client->migration->subscribers();
        self::assertArrayNotHasKey('broadcast_channel_id', $this->http->last()['query']);
    }

    public function testWithChannelRestoresOnException(): void
    {
        $client = $this->client([], ['broadcastChannelId' => 42]);

        try {
            $client->withChannel(99, static function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $client->migration->subscribers();
        self::assertSame('42', $this->http->last()['query']['broadcast_channel_id']);
    }

    public function testWithChannelReturnsTheCallbackResult(): void
    {
        $client = $this->client();
        self::assertSame('returned', $client->withChannel(99, static fn () => 'returned'));
    }
}
