<?php

declare(strict_types=1);

namespace Broadcast\Resources;

final class OptInForms extends BaseResource
{
    /**
     * Up to 250 per page with `pagination` metadata. Variants are excluded.
     *
     * Optional filters: filter (label substring), widget_type, enabled.
     *
     * @param array<string,mixed> $params
     */
    public function list(array $params = []): mixed
    {
        return $this->httpGet('/api/v1/opt_in_forms', $params);
    }

    public function get(string|int $id): mixed
    {
        return $this->httpGet("/api/v1/opt_in_forms/{$id}");
    }

    /**
     * Attributes are wrapped under `opt_in_form:`. Nested settings arrays
     * (theme_settings, automation_settings, security_settings, trigger_settings,
     * widget_settings) and the block arrays are passed through verbatim.
     *
     * @param array<string,mixed> $attrs
     */
    public function create(array $attrs): mixed
    {
        return $this->httpPost('/api/v1/opt_in_forms', ['opt_in_form' => $attrs]);
    }

    /** @param array<string,mixed> $attrs */
    public function update(string|int $id, array $attrs): mixed
    {
        return $this->httpPatch("/api/v1/opt_in_forms/{$id}", ['opt_in_form' => $attrs]);
    }

    public function delete(string|int $id): mixed
    {
        return $this->httpDelete("/api/v1/opt_in_forms/{$id}");
    }

    /**
     * Performance analytics. Dates accept DateTimeInterface or ISO-8601
     * strings; the server defaults to the last 30 days.
     */
    public function analytics(
        string|int $id,
        \DateTimeInterface|string|null $startDate = null,
        \DateTimeInterface|string|null $endDate = null
    ): mixed {
        $params = self::compact([
            'start_date' => $startDate === null ? null : self::coerceDate($startDate),
            'end_date' => $endDate === null ? null : self::coerceDate($endDate),
        ]);

        return $this->httpGet("/api/v1/opt_in_forms/{$id}/analytics", $params);
    }

    public function createVariant(string|int $id, ?string $name = null, ?int $weight = null): mixed
    {
        $body = self::compact(['name' => $name, 'weight' => $weight]);

        return $this->httpPost("/api/v1/opt_in_forms/{$id}/variants", $body);
    }

    public function duplicate(string|int $id, ?string $label = null): mixed
    {
        $body = self::compact(['label' => $label]);

        return $this->httpPost("/api/v1/opt_in_forms/{$id}/duplicate", $body);
    }

    private static function coerceDate(\DateTimeInterface|string $value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format(\DateTimeInterface::ATOM) : $value;
    }
}
