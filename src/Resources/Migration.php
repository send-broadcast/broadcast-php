<?php

declare(strict_types=1);

namespace Broadcast\Resources;

/**
 * Read-only export endpoints under /api/migration/v1.
 *
 * Two things differ from the v1 API:
 *
 * 1. **Admin tokens only.** Channel-scoped tokens are rejected outright.
 * 2. **broadcast_channel_id is required on every call.** Set it once via
 *    `new Client(['broadcastChannelId' => ...])` or `withChannel()` and it is
 *    attached automatically; otherwise pass it per call.
 *
 * On a demo instance (DEMO_MODE) this entire API returns 403 for every request,
 * valid token or not, so a public demo cannot be used as a token oracle. That
 * surfaces here as AuthorizationException.
 *
 * Every list endpoint pages with limit (1..250, default 250) and offset, and
 * returns ['data' => [...], 'pagination' => [...]].
 *
 * @method mixed channels(array $params = [])
 * @method mixed subscribers(array $params = [])
 * @method mixed templates(array $params = [])
 * @method mixed segments(array $params = [])
 * @method mixed sequences(array $params = [])
 * @method mixed emailServers(array $params = [])
 * @method mixed optInForms(array $params = [])
 * @method mixed broadcasts(array $params = [])
 * @method mixed outboundReceipts(array $params = [])
 * @method mixed webhookEndpoints(array $params = [])
 * @method mixed tokens(array $params = [])
 * @method mixed suppressions(array $params = [])
 * @method mixed tags(array $params = [])
 * @method mixed users(array $params = [])
 * @method mixed linkRedirects(array $params = [])
 * @method mixed linkClicks(array $params = [])
 * @method mixed subscriberHistories(array $params = [])
 * @method mixed fileAssets(array $params = [])
 */
final class Migration extends BaseResource
{
    /**
     * Method name => path segment. Generated rather than hand-written: 18
     * near-identical methods invite exactly the copy-paste drift this SDK
     * family exists to prevent. Declared in .api-coverage.yml so the coverage
     * report still counts them.
     *
     * @var array<string,string>
     */
    public const COLLECTIONS = [
        'channels' => 'channels',
        'subscribers' => 'subscribers',
        'templates' => 'templates',
        'segments' => 'segments',
        'sequences' => 'sequences',
        'emailServers' => 'email_servers',
        'optInForms' => 'opt_in_forms',
        'broadcasts' => 'broadcasts',
        'outboundReceipts' => 'outbound_receipts',
        'webhookEndpoints' => 'webhook_endpoints',
        'tokens' => 'tokens',
        'suppressions' => 'suppressions',
        'tags' => 'tags',
        'users' => 'users',
        'linkRedirects' => 'link_redirects',
        'linkClicks' => 'link_clicks',
        'subscriberHistories' => 'subscriber_histories',
        'fileAssets' => 'file_assets',
    ];

    /**
     * Export summary: format version, channel identity, per-resource counts,
     * and recent-history totals. Call this first to size an export.
     *
     * `days_history` windows the time-bounded counts; the server clamps to 1..365.
     *
     * @param array<string,mixed> $params
     */
    public function manifest(array $params = []): mixed
    {
        return $this->httpGet('/api/migration/v1/manifest', $params);
    }

    /**
     * Binary contents of a stored file asset — bytes, not JSON.
     *
     * @param array<string,mixed> $params
     */
    public function downloadFileAsset(string|int $id, array $params = []): string
    {
        return (string) $this->httpGet("/api/migration/v1/file_assets/{$id}/download", $params, true);
    }

    /**
     * Page through a collection, yielding each record.
     *
     *   foreach ($client->migration->eachRecord('subscribers') as $sub) { ... }
     *
     * Stops when the server reports has_more false, and advances by the limit
     * the server actually applied rather than the one requested — the server
     * clamps to 250, so trusting the request would skip records.
     *
     * @param array<string,mixed> $params
     * @return \Generator<int,mixed>
     */
    public function eachRecord(string $collection, int $limit = 250, array $params = []): \Generator
    {
        $offset = 0;

        while (true) {
            $page = $this->__call($collection, [array_merge($params, ['limit' => $limit, 'offset' => $offset])]);

            $records = is_array($page['data'] ?? null) ? $page['data'] : [];
            foreach ($records as $record) {
                yield $record;
            }

            $pagination = is_array($page['pagination'] ?? null) ? $page['pagination'] : [];
            if (empty($pagination['has_more'])) {
                return;
            }

            // Note the ?? rather than ?: — a server-reported limit of 0 must
            // stay 0 so the guard below fires, rather than falling back to the
            // record count and looping forever.
            $advanced = (int) ($pagination['limit'] ?? count($records));
            if ($advanced <= 0) {
                return;
            }

            $offset += $advanced;
        }
    }

    /** @param array<int,mixed> $arguments */
    public function __call(string $name, array $arguments): mixed
    {
        if (!isset(self::COLLECTIONS[$name])) {
            throw new \BadMethodCallException(sprintf(
                'Unknown migration collection "%s". Known collections: %s',
                $name,
                implode(', ', array_keys(self::COLLECTIONS))
            ));
        }

        $params = $arguments[0] ?? [];
        $collection = self::COLLECTIONS[$name];

        // Interpolated rather than concatenated so the coverage scanner reads
        // this as one templated path; string concatenation leaves the literal
        // "/api/migration/v1/" behind, which reports as a stale endpoint.
        return $this->httpGet("/api/migration/v1/{$collection}", is_array($params) ? $params : []);
    }
}
