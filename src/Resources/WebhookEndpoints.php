<?php

declare(strict_types=1);

namespace Broadcast\Resources;

final class WebhookEndpoints extends BaseResource
{
    /** @param array<string,mixed> $params */
    public function list(array $params = []): mixed
    {
        return $this->httpGet('/api/v1/webhook_endpoints', $params);
    }

    public function get(string|int $id): mixed
    {
        return $this->httpGet("/api/v1/webhook_endpoints/{$id}");
    }

    /**
     * The `secret` is returned once, on create, and never again.
     *
     * @param array<string,mixed> $attrs
     */
    public function create(array $attrs): mixed
    {
        return $this->httpPost('/api/v1/webhook_endpoints', ['webhook_endpoint' => $attrs]);
    }

    /** @param array<string,mixed> $attrs */
    public function update(string|int $id, array $attrs): mixed
    {
        return $this->httpPatch("/api/v1/webhook_endpoints/{$id}", ['webhook_endpoint' => $attrs]);
    }

    public function delete(string|int $id): mixed
    {
        return $this->httpDelete("/api/v1/webhook_endpoints/{$id}");
    }

    public function test(string|int $id, string $eventType = 'test.webhook'): mixed
    {
        return $this->httpPost("/api/v1/webhook_endpoints/{$id}/test", ['event_type' => $eventType]);
    }

    /** @param array<string,mixed> $params */
    public function deliveries(string|int $id, array $params = []): mixed
    {
        return $this->httpGet("/api/v1/webhook_endpoints/{$id}/deliveries", $params);
    }
}
