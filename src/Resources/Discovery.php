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

    /**
     * This installation's own OpenAPI document, as YAML. Returns a string —
     * this endpoint serves application/yaml.
     *
     * The server URL inside the document is rewritten by the installation to
     * the host that served it, so the result feeds a client generator or an API
     * explorer without hand-editing. Preferable to a spec copied from
     * elsewhere: a 2.28 install serves the 2.28 surface, so the document cannot
     * drift from the routes it describes.
     */
    public function openapi(): string
    {
        return (string) $this->httpGet('/api/v1/openapi', [], true);
    }
}
