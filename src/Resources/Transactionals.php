<?php

declare(strict_types=1);

namespace Broadcast\Resources;

use Broadcast\Exception\ValidationException;

final class Transactionals extends BaseResource
{
    public const MAX_IDEMPOTENCY_KEY_LENGTH = 255;

    /**
     * Send a transactional email.
     *
     * Required: `to`. One of subject/body or template_id is also required —
     * template_id resolves subject and body server-side, and subject/body
     * override the template.
     *
     * Optional: subject, body, preheader, reply_to, template_id,
     * include_unsubscribe_link, double_opt_in, confirmation_template_id,
     * subscriber, idempotency_key.
     *
     * Idempotency
     * -----------
     * Pass `idempotency_key` to make a retry safe. The server stores the
     * response for 24 hours keyed on (token, key) and replays it rather than
     * sending a second email. Check isIdempotentReplay() to tell a replay from
     * a fresh send.
     *
     * The key is part of a fingerprint over method + full path + body:
     *   - same key, same payload, still running -> ConflictException (409)
     *   - same key, *different* payload         -> ValidationException (422)
     *
     * That 422 means "this key was already used for something else", not that
     * the email was invalid — do not retry it with the same key.
     *
     * @param array<string,mixed> $params
     */
    public function create(array $params): mixed
    {
        if (!isset($params['to'])) {
            throw new ValidationException('to is required');
        }

        $idempotencyKey = $params['idempotency_key'] ?? null;
        unset($params['idempotency_key']);

        // Preserve the documented key order, then append anything extra.
        $ordered = [];
        foreach (
            ['to', 'subject', 'body', 'preheader', 'reply_to', 'template_id',
             'include_unsubscribe_link', 'double_opt_in', 'confirmation_template_id', 'subscriber'] as $key
        ) {
            if (array_key_exists($key, $params) && $params[$key] !== null) {
                $ordered[$key] = $params[$key];
            }
            unset($params[$key]);
        }
        $payload = array_merge($ordered, array_filter($params, static fn ($v) => $v !== null));

        $headers = self::idempotencyHeaders($idempotencyKey);

        return $this->httpPost('/api/v1/transactionals.json', $payload, $headers);
    }

    public function get(string|int $id): mixed
    {
        return $this->httpGet("/api/v1/transactionals/{$id}.json");
    }

    /** @return array<string,string> */
    private static function idempotencyHeaders(?string $key): array
    {
        if ($key === null) {
            return [];
        }

        $trimmed = trim($key);
        if ($trimmed === '') {
            return [];
        }

        if (mb_strlen($trimmed) > self::MAX_IDEMPOTENCY_KEY_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'idempotency_key must be %d characters or fewer (got %d)',
                self::MAX_IDEMPOTENCY_KEY_LENGTH,
                mb_strlen($trimmed)
            ));
        }

        return ['Idempotency-Key' => $trimmed];
    }
}
