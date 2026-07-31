<?php

declare(strict_types=1);

namespace Broadcast\Resources;

/**
 * The installation-wide suppression list. Addresses on it never receive mail
 * from any channel. All operations require an admin (system) API token — a
 * channel token gets a 401.
 *
 * There is deliberately no check() here: checking is a per-channel question
 * (it reads the channel list too), so it lives on Suppressions.
 */
final class GlobalSuppressions extends BaseResource
{
    /**
     * List global suppressions (250 per page, with `pagination` metadata;
     * pass `page`). Optional `email` filters by partial match.
     *
     * @param array<string,mixed> $params
     */
    public function list(array $params = []): mixed
    {
        return $this->httpGet('/api/v1/global_suppressions.json', $params);
    }

    /** Add an address to the global list. Already-suppressed is a success (200 instead of 201). */
    public function add(string $email): mixed
    {
        return $this->httpPost('/api/v1/global_suppressions.json', ['email' => $email]);
    }

    /**
     * Remove an address from the global list only. Channels that suppressed
     * the same address on their own account keep their block.
     */
    public function remove(string $email): mixed
    {
        return $this->httpDelete('/api/v1/global_suppressions.json', ['email' => $email]);
    }

    /**
     * Add up to 10,000 addresses at once. Idempotent. Returns `added`,
     * `already_suppressed`, and `invalid` counts.
     *
     * @param list<string> $emails
     */
    public function bulkAdd(array $emails): mixed
    {
        return $this->httpPost('/api/v1/global_suppressions/bulk.json', ['emails' => $emails]);
    }

    /**
     * Remove up to 10,000 addresses at once. Returns `removed` and
     * `not_found` counts.
     *
     * @param list<string> $emails
     */
    public function bulkRemove(array $emails): mixed
    {
        return $this->httpDelete('/api/v1/global_suppressions/bulk.json', ['emails' => $emails]);
    }
}
