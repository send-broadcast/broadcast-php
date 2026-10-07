<?php

declare(strict_types=1);

namespace Broadcast\Resources;

/**
 * Subscriber topics: kinds of email subscribers opt in to or out of. A topic
 * reads a top-level custom_data key (true, false, or no value) or a tag.
 * Topics use the token's subscriber permissions.
 */
final class Topics extends BaseResource
{
    /** @param array<string,mixed> $params */
    public function list(array $params = []): mixed
    {
        return $this->httpGet('/api/v1/topics.json', $params);
    }

    public function get(string|int $id): mixed
    {
        return $this->httpGet("/api/v1/topics/{$id}.json");
    }

    /** @param array<string,mixed> $attrs */
    public function create(array $attrs): mixed
    {
        return $this->httpPost('/api/v1/topics', ['topic' => $attrs]);
    }

    /** @param array<string,mixed> $attrs */
    public function update(string|int $id, array $attrs): mixed
    {
        return $this->httpPatch("/api/v1/topics/{$id}", ['topic' => $attrs]);
    }

    /** Refused (422) while a broadcast or sequence uses the topic. */
    public function delete(string|int $id): mixed
    {
        return $this->httpDelete("/api/v1/topics/{$id}");
    }
}
