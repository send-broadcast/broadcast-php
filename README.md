# broadcast/broadcast-php

Official PHP client for [Broadcast](https://sendbroadcast.net), the self-hosted email marketing platform.

Works with any Broadcast instance — self-hosted or SaaS. Covers **104/104 API operations**, verified against the API's generated OpenAPI document.

📖 **[PHP SDK documentation](https://sendbroadcast.net/docs/php-sdk)** · [API reference](https://sendbroadcast.net/docs/api-authentication) · [All docs](https://sendbroadcast.net/docs)

Also available: [Ruby](https://github.com/send-broadcast/broadcast-ruby) · [Node/TypeScript](https://github.com/send-broadcast/broadcast-node) · [Python](https://github.com/send-broadcast/broadcast-python)

## Installation

```bash
composer require broadcast/broadcast-php
```

PHP 8.1+ with `ext-curl` and `ext-json`. The only Composer dependency is
`psr/log`, so it drops into a Laravel app, a WordPress plugin, or a plain script
without pulling in a stack of transitive packages.

## Getting Your API Token

1. Log in to your Broadcast dashboard
2. Go to **Settings > API Keys**
3. Click **New API Key**
4. Name it, select the permissions you need (see [Permissions](#api-token-permissions) below), and save
5. Copy the token

## Quick Start

```php
use Broadcast\Client;

$client = new Client([
    'apiToken' => getenv('BROADCAST_API_TOKEN'),
    'host' => 'https://mail.example.com',   // required
]);

$client->subscribers->create(['email' => 'ada@example.com', 'first_name' => 'Ada']);

$client->transactionals->create([
    'to' => 'ada@example.com',
    'subject' => 'Welcome',
    'body' => '<p>Glad you are here.</p>',
]);
```

### `host` is required

There is no default. Broadcast is self-hosted-first, so every instance lives at
its own domain and any built-in guess would be wrong for nearly everyone.

`BROADCAST_HOST` and `BROADCAST_API_TOKEN` are the same names the Broadcast CLI
uses in `~/.config/broadcast/config`.

---

## Configuration

```php
new Client([
    'apiToken' => '...',
    'host' => 'https://...',
    'timeout' => 30,             // read timeout, seconds
    'openTimeout' => 10,
    'retryAttempts' => 3,
    'retryDelay' => 1,           // base backoff, multiplied by attempt number
    'maxRetryDelay' => 30,       // ceiling on a server-sent Retry-After
    'warningsMode' => 'log',     // 'log' | 'raise' | 'ignore'
    'logger' => $psr3Logger,
    'debug' => false,
    'broadcastChannelId' => 42,  // admin/system tokens
]);
```

A typo in an option name throws `ConfigurationException` rather than being
silently ignored.

---

## Responses

`Response` implements `ArrayAccess`, so a result reads like the parsed body
while carrying transport metadata as methods:

```php
$result = $client->subscribers->create(['email' => 'ada@example.com']);

$result['id'];                     // the body
$result->status();                 // 201
$result->warnings();               // list<Warning>
$result->rateLimit()?->remaining;
$result->isIdempotentReplay();
```

Array access reads the body, methods read metadata — so a body field named
`status` (which broadcasts have) stays reachable as `$result['status']`.

---

## Warnings

A 2xx response can carry warnings: the API accepted your request but ignored
part of it. A mistyped filter silently widens a result set unless you look.

```
'log'    (default) — warn through the PSR-3 logger, return normally
'raise'            — throw WarningException. NOTE: the write already happened.
'ignore'           — say nothing; read them off $result->warnings()
```

---

## Rate Limits

Every response carries the current limit state, and 429s are retried
automatically honouring the server's `Retry-After` (capped at `maxRetryDelay`).

```php
$result = $client->subscribers->list();

$result->rateLimit()->limit;      // 120
$result->rateLimit()->remaining;  // 118
$result->rateLimit()->reset;      // DateTimeImmutable

// Back off before you get throttled
if ($result->rateLimit()?->remaining < 10) {
    sleep(1);
}
```

If the retries are exhausted you get a `RateLimitException`, which carries
`->retryAfter` so you can requeue the job sensibly.

---

## Errors

```
BroadcastException
├── ConfigurationException
├── ApiException
│   ├── AuthenticationException   401
│   ├── AuthorizationException    403
│   ├── NotFoundException         404
│   ├── ConflictException         409  idempotency replay still in flight
│   └── RateLimitException        429  carries ->retryAfter
├── ValidationException           422
├── TimeoutException
├── DeliveryException
└── WarningException                   carries ->warnings and ->response
```

`ValidationException` and `TimeoutException` are siblings of `ApiException`, not
children — matching the Ruby gem, so `catch (ApiException)` does not swallow a
validation failure.

Timeouts, 429s, and 5xx are retried with backoff. A 422 is not: it is
deterministic, so retrying is pure latency.

---

## Common Tasks

### Subscribers

```php
$client->subscribers->list(['page' => 1, 'is_active' => true, 'tags' => ['vip']]);
$client->subscribers->find('ada@example.com');
$client->subscribers->create(['email' => 'ada@example.com']);
$client->subscribers->update('ada@example.com', ['first_name' => 'Ada']);
$client->subscribers->addTags('ada@example.com', ['beta']);
$client->subscribers->removeTags('ada@example.com', ['beta']);
$client->subscribers->activate('ada@example.com');
$client->subscribers->deactivate('ada@example.com');
$client->subscribers->unsubscribe('ada@example.com');
$client->subscribers->resubscribe('ada@example.com');
$client->subscribers->redact('ada@example.com');   // irreversible
```

`created_after` / `created_before` that fail to parse are **ignored** by the
server rather than rejected — they return a `parameter_ignored` warning, so a
bad timestamp silently widens your result set. Check `$result->warnings()`.

### Broadcasts

```php
$client->broadcasts->create(['subject' => 'Weekly', 'body' => '<p>Hi</p>']);
$client->broadcasts->send($id);                    // no undo
$client->broadcasts->schedule($id, '2026-08-01T09:00:00Z', 'UTC');
$client->broadcasts->cancelSchedule($id);
$client->broadcasts->statistics($id);
$client->broadcasts->statisticsTimeline($id);
$client->broadcasts->statisticsLinks($id);
```

### Sequences

```php
$client->sequences->get($id, includeSteps: true);
$client->sequences->addSubscriber($id, ['email' => 'ada@example.com']);
$client->sequences->removeSubscriber($id, 'ada@example.com');
$client->sequences->createStep($id, ['subject' => 'Day 1']);
$client->sequences->moveStep($id, $stepId, $underId);
```

### Segments, templates, opt-in forms

```php
$client->segments->create(['name' => 'VIPs']);
$client->templates->create(['label' => 'Welcome', 'subject' => 'Hi']);
$client->optInForms->analytics($id, new DateTimeImmutable('2026-01-01'));
$client->optInForms->createVariant($id, 'B', 50);
$client->optInForms->duplicate($id, 'Copy');
```

Reading a segment recounts its members server-side, so `segments->get` is not free.

### Email servers

**Credential redaction guard.** The API returns credentials bullet-masked
(`••••••••`). A naive fetch-modify-save would write those bullets back and
destroy a working SMTP password. `update()` strips any credential field whose
value matches the redaction pattern and warns:

```php
$server = $client->emailServers->get($id);
$server['smtp_password'];                     // '••••••••'
$client->emailServers->update($id, ['name' => 'Renamed', 'smtp_password' => $server['smtp_password']]);
// -> sends only ['name' => 'Renamed'], warns about the dropped field
```

### Autopilot

```php
$client->autopilots->create(['name' => 'Weekly', 'ai_model' => 'openai/gpt-4o']);
$client->autopilots->activate($id);
$client->autopilots->triggerRun($id);     // 202 — async, poll runs()
$client->autopilots->runs($id, ['limit' => 10]);
```

`activate` requires an active source, an API key, and a model. Sources and tone
samples have **no API endpoints** — they live in the web UI, so an autopilot
created entirely over the API cannot be activated until a source is added there.

### Transactional email

```php
$client->transactionals->create([
    'to' => 'ada@example.com',
    'subject' => 'Receipt',
    'body' => '<p>Thanks</p>',
    'idempotency_key' => "receipt-{$order->id}",
]);
```

The server stores the response for 24 hours keyed on (token, key) and replays it
rather than sending a second email. The key is part of a fingerprint over
method + path + body:

- same key, same payload, still running → `ConflictException` (409)
- same key, **different** payload → `ValidationException` (422)

That 422 means "this key was already used for something else", not that the
email was invalid. Do not retry it with the same key.

This is the only endpoint that accepts `Idempotency-Key`.

### Discovery

```php
$client->whoami();   // token identity and permissions
$client->status();   // channel readiness — check before a send
$client->prime();    // full capability manifest
$client->skill();    // plain-text agent skill manifest (a string)
```

### Migration / export

Read-only. **Admin tokens only**, and every call needs a `broadcast_channel_id`.

```php
$client = new Client([..., 'broadcastChannelId' => 42]);

$client->migration->manifest();
$client->migration->subscribers(['limit' => 250]);

foreach ($client->migration->eachRecord('subscribers') as $sub) {
    // auto-pages; advances by the limit the server actually applied
}

$bytes = $client->migration->downloadFileAsset($id);
```

On a demo instance this entire API returns **403 for every request**, valid
token or not — deliberately, so a public demo cannot be used as a token oracle.
It surfaces as `AuthorizationException`.

---

## Channel Scoping

```php
$client->withChannel(123, fn () => $client->emailServers->list());
```

The previous scope is restored afterwards, including when the callable throws.

---

## Webhooks

```php
use Broadcast\Webhook;

$valid = Webhook::verify(
    $rawBody,                    // the raw body, not a re-encoded array
    $_SERVER['HTTP_X_BROADCAST_SIGNATURE'] ?? null,
    $_SERVER['HTTP_X_BROADCAST_TIMESTAMP'] ?? null,
    getenv('WEBHOOK_SECRET') ?: null,
);

if (!$valid) {
    http_response_code(401);
    exit;
}
```

HMAC-SHA256 over `timestamp.payload`, `v1,<base64>` header format, 5-minute
timestamp tolerance, `hash_equals` comparison. `verify` returns `false` for
every rejection rather than distinguishing them.

Pass the **raw** request body (`file_get_contents('php://input')`). Re-encoding
a decoded array changes the bytes and verification will fail.

`Webhook::eventTypes()` lists all 32 event names.

---

## Using Your Own HTTP Client

The bundled `CurlHttpClient` keeps installation dependency-free. To route
requests through Guzzle, Symfony HttpClient, or a PSR-18 client, implement
`Broadcast\HttpClientInterface` — one method — and pass it as `httpClient`:

```php
$client = new Client([..., 'httpClient' => new MyPsr18Adapter($psr18)]);
```

The interface is deliberately smaller than PSR-18: PSR-18 would pull in
`psr/http-message`, `psr/http-factory` and a concrete implementation for what is
one request and one response.

---

## API Token Permissions

Each token can be scoped to specific resources. Use the minimum permissions your
integration requires.

| Resource | Read permission | Write permission |
|----------|----------------|------------------|
| Transactional Emails | `transactionals_read` -- get delivery status | `transactionals_write` -- send emails |
| Subscribers | `subscribers_read` -- list, find | `subscribers_write` -- create, update, tag, deactivate, unsubscribe, redact |
| Sequences | `sequences_read` -- list, get, list steps | `sequences_write` -- create, update, delete, manage steps, enroll subscribers |
| Broadcasts | `broadcasts_read` -- list, get, statistics | `broadcasts_write` -- create, update, delete, send, schedule |
| Segments | `segments_read` -- list, get | `segments_write` -- create, update, delete |
| Templates | `templates_read` -- list, get | `templates_write` -- create, update, delete |
| Opt-In Forms | `opt_in_forms_read` -- list, get, analytics | `opt_in_forms_write` -- create, update, delete, create_variant, duplicate |
| Email Servers | `email_servers_read` -- list, get | `email_servers_write` -- create, update, delete, test_connection, copy_to_channel (admin) |
| Webhook Endpoints | `webhook_endpoints_read` -- list, get, deliveries | `webhook_endpoints_write` -- create, update, delete, test |
| Autopilot | `autopilot_read` -- list, get, runs | `autopilot_write` -- create, update, delete, activate, pause, deactivate, trigger_run |

---

## Troubleshooting

### `AuthenticationException` (401)

- **Check the token:** it must be an API key from **Settings > API Keys**, not a
  password or a session cookie.
- **Check the host:** pointing at the wrong instance produces a valid-looking
  401, because the token is unknown there.

### `AuthorizationException` (403)

The token is valid but lacks the permission for that call. Check the table
above, then re-issue the key with the resource enabled — permissions are fixed
at creation.

On a demo instance, the entire migration API returns 403 for every request,
valid token or not.

### `ValidationException` (422) on a repeated send

If you reused an `idempotency_key` with a *different* payload, the API rejects
it: the key is fingerprinted over method, path and body. It means "this key was
already used for something else", not that the email was invalid. Use a new key.

### Emails accepted but never delivered

Call `$client->status()`. If `readiness.transactionals` is `false`, the channel
has no usable email server or sender identity — the API accepts the request and
the send stalls. On a demo instance, sends are always accepted and never
delivered.

### `ApiException` mentioning a redirect

Your `host` is wrong — usually `http` instead of `https`, or a bare apex that
redirects to `www`. The client refuses to follow redirects on writes, and never
across hosts, because every request carries your API token. Set `host` to the
final URL.

---

## Development

```bash
composer install
composer test          # mocked HTTP, no network
```

CI runs the suite on PHP 8.1 through 8.5, and against both the oldest and
newest allowed `psr/log`.

---

## Documentation

- **[PHP SDK guide](https://sendbroadcast.net/docs/php-sdk)** — the same material as this README, on the docs site
- **[API reference](https://sendbroadcast.net/docs/api-authentication)** — endpoints, parameters, and permissions
- **[API response warnings](https://sendbroadcast.net/docs/api-response-warnings)** — why a 2xx can still tell you something went wrong
- **[Webhook endpoints](https://sendbroadcast.net/docs/api-webhook-endpoints)** — signature format and event types
- **[Agents CLI](https://sendbroadcast.net/docs/agents-cli)** — the same credentials, from a terminal

### Other SDKs

| Language | Package | Repository |
|---|---|---|
| PHP | broadcast/broadcast-php | this repository |
| Ruby | [broadcast-ruby](https://rubygems.org/gems/broadcast-ruby) | [broadcast-ruby](https://github.com/send-broadcast/broadcast-ruby) |
| Node / TypeScript | @broadcast/sdk | [broadcast-node](https://github.com/send-broadcast/broadcast-node) |
| Python | broadcast-python | [broadcast-python](https://github.com/send-broadcast/broadcast-python) |

All four cover the same 104 operations and behave the same way on the wire — the transport contract (warnings, idempotency, rate-limit handling, redirect safety, credential redaction) is identical across languages.

---

## License

MIT
