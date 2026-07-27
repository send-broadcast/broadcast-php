<?php

declare(strict_types=1);

namespace Broadcast\Resources;

final class Subscribers extends BaseResource
{
    /**
     * List subscribers, 250 per page with `pagination` metadata.
     *
     * Filters: is_active, source, created_after, created_before, tags (AND
     * logic), email (partial, case-insensitive), confirmation_status,
     * custom_data (JSONB containment).
     *
     * An unparseable created_after/created_before is *ignored* by the server
     * rather than rejected, returning a `parameter_ignored` warning — so a bad
     * timestamp silently widens the result set unless you check warnings().
     *
     * @param array<string,mixed> $params
     */
    public function list(array $params = []): mixed
    {
        return $this->httpGet('/api/v1/subscribers.json', $params);
    }

    public function find(string $email): mixed
    {
        return $this->httpGet('/api/v1/subscribers/find.json', ['email' => $email]);
    }

    /**
     * Create or upsert a subscriber.
     *
     * Attributes are wrapped under `subscriber:`, except double_opt_in and
     * confirmation_template_id, which the API expects at the top level.
     *
     * confirmed_at is admin-token only — it backdates the confirmation
     * timestamp when migrating an already-confirmed list off another provider.
     *
     * unsubscribed_at is never settable here; use unsubscribe().
     *
     * @param array<string,mixed> $attrs
     */
    public function create(array $attrs): mixed
    {
        $doubleOptIn = $attrs['double_opt_in'] ?? null;
        $confirmationTemplateId = $attrs['confirmation_template_id'] ?? null;
        unset($attrs['double_opt_in'], $attrs['confirmation_template_id']);

        $payload = ['subscriber' => $attrs];
        if ($doubleOptIn !== null) {
            $payload['double_opt_in'] = $doubleOptIn;
        }
        if ($confirmationTemplateId !== null) {
            $payload['confirmation_template_id'] = $confirmationTemplateId;
        }

        return $this->httpPost('/api/v1/subscribers.json', $payload);
    }

    /** @param array<string,mixed> $attrs */
    public function update(string $email, array $attrs): mixed
    {
        return $this->httpPatch('/api/v1/subscribers.json', ['email' => $email, 'subscriber' => $attrs]);
    }

    /** @param list<string> $tags */
    public function addTags(string $email, array $tags): mixed
    {
        return $this->httpPost('/api/v1/subscribers/add_tag.json', ['email' => $email, 'tags' => $tags]);
    }

    /** @param list<string> $tags */
    public function removeTags(string $email, array $tags): mixed
    {
        return $this->httpDelete('/api/v1/subscribers/remove_tag.json', ['email' => $email, 'tags' => $tags]);
    }

    public function activate(string $email): mixed
    {
        return $this->httpPost('/api/v1/subscribers/activate.json', ['email' => $email]);
    }

    public function deactivate(string $email): mixed
    {
        return $this->httpPost('/api/v1/subscribers/deactivate.json', ['email' => $email]);
    }

    public function unsubscribe(string $email): mixed
    {
        return $this->httpPost('/api/v1/subscribers/unsubscribe.json', ['email' => $email]);
    }

    public function resubscribe(string $email): mixed
    {
        return $this->httpPost('/api/v1/subscribers/resubscribe.json', ['email' => $email]);
    }

    /** Irreversible: scrubs personal data while keeping aggregate counts. */
    public function redact(string $email): mixed
    {
        return $this->httpPost('/api/v1/subscribers/redact.json', ['email' => $email]);
    }
}
