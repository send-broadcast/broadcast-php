<?php

declare(strict_types=1);

namespace Broadcast\Resources;

final class EmailServers extends BaseResource
{
    /**
     * Fields the API returns bullet-masked. Round-tripping one of these from a
     * fetch into an update would replace a working credential with bullets, so
     * update() strips them. This is a data-loss guard, not a nicety.
     */
    public const REDACTED_FIELDS = [
        'smtp_password',
        'aws_access_key_id',
        'aws_secret_access_key',
        'outbound_aws_access_key_id',
        'outbound_aws_secret_access_key',
        'postmark_api_token',
        'inboxroad_api_token',
        'smtp_com_api_key',
    ];

    /** Matches the API's redaction shape: 8 bullets, or 4 chars + bullets + 4 chars. */
    private const REDACTED_PATTERN = '/\A(?:\x{2022}{8}|.{0,4}\x{2022}+.{0,4})\z/u';

    public function list(?int $limit = null, ?int $offset = null): mixed
    {
        return $this->httpGet('/api/v1/email_servers', self::compact(['limit' => $limit, 'offset' => $offset]));
    }

    public function get(string|int $id): mixed
    {
        return $this->httpGet("/api/v1/email_servers/{$id}");
    }

    /** @param array<string,mixed> $attrs */
    public function create(array $attrs): mixed
    {
        return $this->httpPost('/api/v1/email_servers', ['email_server' => $attrs]);
    }

    /**
     * CAUTION: API responses redact credential fields with bullet characters.
     * Never echo a fetched response back into update — this method scrubs
     * values matching the redaction pattern, but you should pass only the
     * fields you actually want to change.
     *
     * @param array<string,mixed> $attrs
     */
    public function update(string|int $id, array $attrs): mixed
    {
        return $this->httpPatch("/api/v1/email_servers/{$id}", ['email_server' => $this->scrub($attrs)]);
    }

    public function delete(string|int $id): mixed
    {
        return $this->httpDelete("/api/v1/email_servers/{$id}");
    }

    public function testConnection(string|int $id): mixed
    {
        return $this->httpPost("/api/v1/email_servers/{$id}/test_connection");
    }

    /**
     * Requires an admin/system token. In SaaS mode the target channel is scoped
     * to the token creator's account.
     */
    public function copyToChannel(string|int $id, string|int $targetChannelId): mixed
    {
        $body = ['target_channel_id' => $targetChannelId];

        return $this->httpPost("/api/v1/email_servers/{$id}/copy_to_channel", $body);
    }

    /**
     * @param array<string,mixed> $attrs
     * @return array<string,mixed>
     */
    private function scrub(array $attrs): array
    {
        $scrubbed = [];

        foreach ($attrs as $key => $value) {
            if (
                in_array($key, self::REDACTED_FIELDS, true)
                && is_string($value)
                && preg_match(self::REDACTED_PATTERN, $value) === 1
            ) {
                $this->warn(sprintf(
                    '[broadcast-php] Dropped redacted %s from update payload — pass the real credential '
                    . 'or omit the field',
                    $key
                ));

                continue;
            }
            $scrubbed[$key] = $value;
        }

        return $scrubbed;
    }
}
