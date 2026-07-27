<?php

declare(strict_types=1);

namespace Broadcast\Resources;

final class Templates extends BaseResource
{
    /** @param array<string,mixed> $params */
    public function list(array $params = []): mixed
    {
        return $this->httpGet('/api/v1/templates', $params);
    }

    public function get(string|int $id): mixed
    {
        return $this->httpGet("/api/v1/templates/{$id}");
    }

    /**
     * Attributes are wrapped under `template:`.
     *
     * Content: label, subject, preheader, body, html_body.
     * Confirmation templates: template_purpose, confirmation_text,
     * default_confirmation, confirmation_page_settings (per-state page copy
     * keyed by state, each taking ['heading' => ..., 'body' => ...]).
     *
     * Anything the server does not recognise comes back as an
     * `unrecognized_parameter` warning rather than an error.
     *
     * @param array<string,mixed> $attrs
     */
    public function create(array $attrs): mixed
    {
        return $this->httpPost('/api/v1/templates', ['template' => $attrs]);
    }

    /** @param array<string,mixed> $attrs */
    public function update(string|int $id, array $attrs): mixed
    {
        return $this->httpPatch("/api/v1/templates/{$id}", ['template' => $attrs]);
    }

    public function delete(string|int $id): mixed
    {
        return $this->httpDelete("/api/v1/templates/{$id}");
    }
}
