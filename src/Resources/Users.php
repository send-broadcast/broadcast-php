<?php

declare(strict_types=1);

namespace Broadcast\Resources;

/**
 * User management. All operations require an admin (system) API token, scoped
 * further by `users_read` (GETs) and `users_write` (everything else) — a
 * channel token or a missing scope gets a 401/403. No `broadcast_channel_id`
 * is needed; an SDK-injected one is accepted and silently ignored.
 *
 * Sudo users are read-only through this API: update/deactivate/activate/
 * delete/permission writes on one return 403. Sudo can never be granted.
 */
final class Users extends BaseResource
{
    /**
     * @param array{limit?: int, offset?: int, q?: string, status?: 'active'|'inactive'} $params
     */
    public function list(array $params = []): mixed
    {
        return $this->httpGet('/api/v1/users', self::compact($params));
    }

    public function get(int|string $id): mixed
    {
        return $this->httpGet("/api/v1/users/{$id}");
    }

    /**
     * @param array{email: string, first_name?: string, last_name?: string, password?: string, send_password_reset?: bool} $attrs
     *        One of `password` or `send_password_reset: true` is required.
     */
    public function create(array $attrs): mixed
    {
        return $this->httpPost('/api/v1/users', ['user' => $attrs]);
    }

    /**
     * @param array{email?: string, first_name?: string, last_name?: string, password?: string} $attrs
     */
    public function update(int|string $id, array $attrs): mixed
    {
        return $this->httpPatch("/api/v1/users/{$id}", ['user' => $attrs]);
    }

    public function deactivate(int|string $id): mixed
    {
        return $this->httpPost("/api/v1/users/{$id}/deactivate");
    }

    /** Also clears any account lockout. */
    public function activate(int|string $id): mixed
    {
        return $this->httpPost("/api/v1/users/{$id}/activate");
    }

    public function delete(int|string $id): mixed
    {
        return $this->httpDelete("/api/v1/users/{$id}");
    }

    public function channelPermissions(int|string $id): mixed
    {
        return $this->httpGet("/api/v1/users/{$id}/channel_permissions");
    }

    /**
     * Replaces the user's whole permission record for one channel — unlisted
     * permission flags become false. `$grant` must contain exactly one of
     * `permissions`, `role`, or `preset_id`.
     *
     * @param array{permissions?: array<string, bool>, role?: 'Viewer'|'Editor'|'Manager', preset_id?: int} $grant
     */
    public function setChannelPermissions(int|string $id, int|string $broadcastChannelId, array $grant): mixed
    {
        self::assertExactlyOneGrantKey($grant);

        return $this->httpPut("/api/v1/users/{$id}/channel_permissions/{$broadcastChannelId}", $grant);
    }

    public function removeChannelPermissions(int|string $id, int|string $broadcastChannelId): mixed
    {
        return $this->httpDelete("/api/v1/users/{$id}/channel_permissions/{$broadcastChannelId}");
    }

    /**
     * Applies the same grant to several channels at once. `$grant` must
     * contain exactly one of `permissions`, `role`, or `preset_id`.
     *
     * @param list<int|string> $broadcastChannelIds
     * @param array{permissions?: array<string, bool>, role?: 'Viewer'|'Editor'|'Manager', preset_id?: int} $grant
     */
    public function bulkChannelPermissions(int|string $id, array $broadcastChannelIds, array $grant): mixed
    {
        self::assertExactlyOneGrantKey($grant);

        $body = array_merge(['broadcast_channel_ids' => $broadcastChannelIds], $grant);

        return $this->httpPost("/api/v1/users/{$id}/channel_permissions/bulk", $body);
    }

    public function systemPermissions(int|string $id): mixed
    {
        return $this->httpGet("/api/v1/users/{$id}/system_permissions");
    }

    /**
     * PATCH semantics: only the flags named are changed. `sudo_access` can
     * never be set here — sending it is a 422.
     *
     * @param array<string, bool> $permissions
     */
    public function updateSystemPermissions(int|string $id, array $permissions): mixed
    {
        return $this->httpPatch("/api/v1/users/{$id}/system_permissions", ['permissions' => $permissions]);
    }

    /** @param array<string,mixed> $grant */
    private static function assertExactlyOneGrantKey(array $grant): void
    {
        $known = array_intersect_key($grant, array_flip(['permissions', 'role', 'preset_id']));

        if (count($known) !== 1) {
            throw new \InvalidArgumentException(
                'Grant must contain exactly one of permissions, role, or preset_id'
            );
        }
    }
}
