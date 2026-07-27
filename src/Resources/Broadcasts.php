<?php

declare(strict_types=1);

namespace Broadcast\Resources;

final class Broadcasts extends BaseResource
{
    /** @param array<string,mixed> $params */
    public function list(array $params = []): mixed
    {
        return $this->httpGet('/api/v1/broadcasts', $params);
    }

    public function get(string|int $id): mixed
    {
        return $this->httpGet("/api/v1/broadcasts/{$id}");
    }

    /** @param array<string,mixed> $attrs */
    public function create(array $attrs): mixed
    {
        return $this->httpPost('/api/v1/broadcasts', $attrs);
    }

    /** @param array<string,mixed> $attrs */
    public function update(string|int $id, array $attrs): mixed
    {
        return $this->httpPatch("/api/v1/broadcasts/{$id}", $attrs);
    }

    public function delete(string|int $id): mixed
    {
        return $this->httpDelete("/api/v1/broadcasts/{$id}");
    }

    /** Sends immediately. There is no undo — the API has no unsend. */
    public function send(string|int $id): mixed
    {
        return $this->httpPost("/api/v1/broadcasts/{$id}/send_broadcast");
    }

    public function schedule(string|int $id, string $scheduledSendAt, string $scheduledTimezone): mixed
    {
        $body = ['scheduled_send_at' => $scheduledSendAt, 'scheduled_timezone' => $scheduledTimezone];

        return $this->httpPost("/api/v1/broadcasts/{$id}/schedule_broadcast", $body);
    }

    public function cancelSchedule(string|int $id): mixed
    {
        return $this->httpPost("/api/v1/broadcasts/{$id}/cancel_schedule");
    }

    public function statistics(string|int $id): mixed
    {
        return $this->httpGet("/api/v1/broadcasts/{$id}/statistics");
    }

    /** @param array<string,mixed> $params */
    public function statisticsTimeline(string|int $id, array $params = []): mixed
    {
        return $this->httpGet("/api/v1/broadcasts/{$id}/statistics/timeline", $params);
    }

    /** @param array<string,mixed> $params */
    public function statisticsLinks(string|int $id, array $params = []): mixed
    {
        return $this->httpGet("/api/v1/broadcasts/{$id}/statistics/links", $params);
    }
}
