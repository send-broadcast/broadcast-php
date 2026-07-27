<?php

declare(strict_types=1);

namespace Broadcast\Resources;

/**
 * Autopilot — AI-generated newsletters.
 *
 * Requires the autopilot_read / autopilot_write token permissions.
 *
 * Sources and tone samples have no API endpoints; they are configured in the
 * web UI. Since activate() requires an active source, an autopilot created
 * entirely over the API cannot be activated until a source is added there.
 */
final class Autopilots extends BaseResource
{
    /**
     * The API renders a configured key bullet-masked and never returns the real
     * value. Writing a masked value back would replace a working credential
     * with bullets, so update() strips it — the same guard as EmailServers.
     */
    private const REDACTED_KEY_PATTERN = '/\A\x{2022}+\z/u';

    /** @param array<string,mixed> $params */
    public function list(array $params = []): mixed
    {
        return $this->httpGet('/api/v1/autopilots', $params);
    }

    public function get(string|int $id): mixed
    {
        return $this->httpGet("/api/v1/autopilots/{$id}");
    }

    /** @param array<string,mixed> $attrs */
    public function create(array $attrs): mixed
    {
        return $this->httpPost('/api/v1/autopilots', ['autopilot' => $attrs]);
    }

    /**
     * Pass the real key to rotate it, or omit the field. A masked key is dropped.
     *
     * @param array<string,mixed> $attrs
     */
    public function update(string|int $id, array $attrs): mixed
    {
        return $this->httpPatch("/api/v1/autopilots/{$id}", ['autopilot' => $this->scrubKey($attrs)]);
    }

    public function delete(string|int $id): mixed
    {
        return $this->httpDelete("/api/v1/autopilots/{$id}");
    }

    // --- Lifecycle ---

    /**
     * Requires at least one active source, an API key, and a model. Throws
     * ValidationException naming the missing prerequisites otherwise.
     */
    public function activate(string|int $id): mixed
    {
        return $this->httpPost("/api/v1/autopilots/{$id}/activate");
    }

    public function pause(string|int $id): mixed
    {
        return $this->httpPost("/api/v1/autopilots/{$id}/pause");
    }

    public function deactivate(string|int $id): mixed
    {
        return $this->httpPost("/api/v1/autopilots/{$id}/deactivate");
    }

    /** Returns 202 — generation is asynchronous, so poll runs(). */
    public function triggerRun(string|int $id): mixed
    {
        return $this->httpPost("/api/v1/autopilots/{$id}/trigger_run");
    }

    /** @param array<string,mixed> $params */
    public function runs(string|int $id, array $params = []): mixed
    {
        return $this->httpGet("/api/v1/autopilots/{$id}/runs", $params);
    }

    /**
     * @param array<string,mixed> $attrs
     * @return array<string,mixed>
     */
    private function scrubKey(array $attrs): array
    {
        $key = $attrs['openrouter_api_key'] ?? null;
        if (!is_string($key) || preg_match(self::REDACTED_KEY_PATTERN, $key) !== 1) {
            return $attrs;
        }

        $this->warn(
            '[broadcast-php] Dropped redacted openrouter_api_key from update payload — '
            . 'pass the real key or omit the field'
        );
        unset($attrs['openrouter_api_key']);

        return $attrs;
    }
}
