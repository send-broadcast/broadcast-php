<?php

declare(strict_types=1);

namespace Broadcast\Resources;

final class Segments extends BaseResource
{
    /** @param array<string,mixed> $params */
    public function list(array $params = []): mixed
    {
        return $this->httpGet('/api/v1/segments.json', $params);
    }

    /** Reading a segment recounts its members server-side, so this is not free. */
    public function get(string|int $id, ?int $page = null): mixed
    {
        return $this->httpGet("/api/v1/segments/{$id}.json", $page !== null ? ['page' => $page] : []);
    }

    /** @param array<string,mixed> $attrs */
    public function create(array $attrs): mixed
    {
        return $this->httpPost('/api/v1/segments', ['segment' => $attrs]);
    }

    /** @param array<string,mixed> $attrs */
    public function update(string|int $id, array $attrs): mixed
    {
        return $this->httpPatch("/api/v1/segments/{$id}", ['segment' => $attrs]);
    }

    public function delete(string|int $id): mixed
    {
        return $this->httpDelete("/api/v1/segments/{$id}");
    }
}
