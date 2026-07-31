<?php

declare(strict_types=1);

namespace Broadcast\Resources;

/**
 * The current channel's suppression list. Addresses on it never receive
 * broadcasts, sequences, or transactionals from this channel.
 *
 * The installation-wide list is a separate resource — see GlobalSuppressions —
 * but check() reads across both on purpose: it answers the question an
 * integration actually asks, "will this address receive mail?".
 */
final class Suppressions extends BaseResource
{
    /**
     * List the channel's suppressions (250 per page, with `pagination`
     * metadata; pass `page`). Optional `email` filters by partial,
     * case-insensitive match.
     *
     * @param array<string,mixed> $params
     */
    public function list(array $params = []): mixed
    {
        return $this->httpGet('/api/v1/suppressions.json', $params);
    }

    /**
     * Add an address to the channel's suppression list. Adding an address
     * that is already suppressed is a success (the server answers 200 instead
     * of 201), so callers do not have to check first.
     */
    public function add(string $email): mixed
    {
        return $this->httpPost('/api/v1/suppressions.json', ['email' => $email]);
    }

    /**
     * Remove an address from the channel's suppression list. Returns
     * `removed: false` (not an error) when the address was not on it. Does
     * not touch the global list.
     */
    public function remove(string $email): mixed
    {
        return $this->httpDelete('/api/v1/suppressions.json', ['email' => $email]);
    }

    /**
     * Add up to 10,000 addresses at once. Idempotent: a retried batch cannot
     * duplicate. Returns `added`, `already_suppressed`, and `invalid` counts.
     *
     * @param list<string> $emails
     */
    public function bulkAdd(array $emails): mixed
    {
        return $this->httpPost('/api/v1/suppressions/bulk.json', ['emails' => $emails]);
    }

    /**
     * Remove up to 10,000 addresses at once. Returns `removed` and
     * `not_found` counts.
     *
     * @param list<string> $emails
     */
    public function bulkRemove(array $emails): mixed
    {
        return $this->httpDelete('/api/v1/suppressions/bulk.json', ['emails' => $emails]);
    }

    /**
     * Will this address receive mail? Reads across both the global and the
     * channel list — a globally blocked address reports `suppressed: true`
     * here even though it is absent from the channel's own list. The
     * response's `scope` says which list matched.
     */
    public function check(string $email): mixed
    {
        return $this->httpGet('/api/v1/suppressions/check.json', ['email' => $email]);
    }
}
