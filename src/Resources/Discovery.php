<?php

declare(strict_types=1);

namespace Broadcast\Resources;

/**
 * Introspection endpoints.
 *
 * Built for agents and CLIs that need to discover what a token can do before
 * acting, and equally useful as a deploy-time smoke check.
 */
final class Discovery extends BaseResource
{
    /** Token label, type, per-resource permissions, and the resolved channel. */
    public function whoami(): mixed
    {
        return $this->httpGet('/api/v1/whoami');
    }

    /**
     * Channel sender config, subscriber counts, and per-feature transmission
     * readiness. Worth calling before a send.
     */
    public function status(): mixed
    {
        return $this->httpGet('/api/v1/status');
    }

    /** Full capability manifest: version, permissions, endpoint list, rate limit. */
    public function prime(): mixed
    {
        return $this->httpGet('/api/v1/prime');
    }

    /**
     * Plain-text agent skill manifest, including the safety rules agents are
     * expected to follow. Returns a string — this endpoint serves text/plain.
     */
    public function skill(): string
    {
        return (string) $this->httpGet('/api/v1/skill', [], true);
    }
}
