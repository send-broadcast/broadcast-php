<?php

declare(strict_types=1);

namespace Broadcast\Resources;

final class Sequences extends BaseResource
{
    /** @param array<string,mixed> $params */
    public function list(array $params = []): mixed
    {
        return $this->httpGet('/api/v1/sequences', $params);
    }

    public function get(string|int $id, bool $includeSteps = false): mixed
    {
        return $this->httpGet("/api/v1/sequences/{$id}", $includeSteps ? ['include_steps' => true] : []);
    }

    /** @param array<string,mixed> $attrs */
    public function create(array $attrs): mixed
    {
        return $this->httpPost('/api/v1/sequences', $attrs);
    }

    /** @param array<string,mixed> $attrs */
    public function update(string|int $id, array $attrs): mixed
    {
        return $this->httpPatch("/api/v1/sequences/{$id}", $attrs);
    }

    public function delete(string|int $id): mixed
    {
        return $this->httpDelete("/api/v1/sequences/{$id}");
    }

    // --- Subscriber enrollment ---

    /** @param array<string,mixed> $attrs */
    public function addSubscriber(string|int $sequenceId, array $attrs): mixed
    {
        return $this->httpPost("/api/v1/sequences/{$sequenceId}/add_subscriber", $attrs);
    }

    public function removeSubscriber(string|int $sequenceId, string $email): mixed
    {
        return $this->httpDelete("/api/v1/sequences/{$sequenceId}/remove_subscriber", ['email' => $email]);
    }

    public function listSubscribers(string|int $sequenceId, int $page = 1): mixed
    {
        return $this->httpGet("/api/v1/sequences/{$sequenceId}/list_subscribers", ['page' => $page]);
    }

    // --- Steps ---
    //
    // Steps hang off the sequences resource rather than a top-level one,
    // matching the nested routes.

    public function listSteps(string|int $sequenceId): mixed
    {
        return $this->httpGet("/api/v1/sequences/{$sequenceId}/steps");
    }

    public function getStep(string|int $sequenceId, string|int $stepId): mixed
    {
        return $this->httpGet("/api/v1/sequences/{$sequenceId}/steps/{$stepId}");
    }

    /** @param array<string,mixed> $attrs */
    public function createStep(string|int $sequenceId, array $attrs): mixed
    {
        return $this->httpPost("/api/v1/sequences/{$sequenceId}/steps", $attrs);
    }

    /** @param array<string,mixed> $attrs */
    public function updateStep(string|int $sequenceId, string|int $stepId, array $attrs): mixed
    {
        return $this->httpPatch("/api/v1/sequences/{$sequenceId}/steps/{$stepId}", $attrs);
    }

    /** Reorders a step to sit directly after $underId. */
    public function moveStep(string|int $sequenceId, string|int $stepId, string|int $underId): mixed
    {
        return $this->httpPost("/api/v1/sequences/{$sequenceId}/steps/{$stepId}/move", ['under_id' => $underId]);
    }

    public function deleteStep(string|int $sequenceId, string|int $stepId): mixed
    {
        return $this->httpDelete("/api/v1/sequences/{$sequenceId}/steps/{$stepId}");
    }
}
