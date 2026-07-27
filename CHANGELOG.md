# Changelog

All notable changes to this project will be documented in this file.

## [0.1.0] - 2026-07-27

First release. Feature parity with `broadcast-ruby` v0.3.0 — the reference
implementation — verified at **104/104 API operations** by the coverage report
in the `broadcast` repo.

### Transport
- Required explicit `host`, with `BROADCAST_HOST` / `BROADCAST_API_TOKEN` env
  fallbacks matching the Broadcast CLI's config keys
- Bearer auth, `User-Agent: broadcast-php/<version>`
- Response warnings surfaced, with `log` / `raise` / `ignore` modes
- `Idempotency-Key` request header and `isIdempotentReplay()` detection
- `X-RateLimit-*` parsing; 429 retry honouring `Retry-After`, bounded by
  `maxRetryDelay`
- Retries on timeout and 5xx with linear backoff; 422 is never retried
- Typed exceptions for 401/403/404/409/422/429/5xx
- Redirects followed on GET only, never across hosts — the request carries a
  bearer token, and `CURLOPT_FOLLOWLOCATION` would take it along
- Raw response path for `text/plain` (`/api/v1/skill`) and binary file assets
- Channel scoping via `broadcastChannelId` and `withChannel()`
- Debug logging that never emits credentials or request bodies

### Resources
Subscribers, broadcasts (incl. statistics), sequences (incl. steps), segments,
templates, opt-in forms, email servers, webhook endpoints, transactionals,
autopilot, discovery, and the 20 migration/export operations.

### Non-negotiables carried over from the Ruby gem
- **Credential redaction guard** on email servers and autopilot, so a
  fetch-modify-save cannot overwrite a real credential with bullet characters
- Webhook HMAC-SHA256 verification with a 5-minute window and `hash_equals`
- No credentials or subscriber emails in debug output

### Notes
- Booleans are serialised as `true`/`false` in query strings. PHP casts `true`
  to `"1"`, which Rails does not read as true.
- The transport seam is a one-method `HttpClientInterface` rather than PSR-18,
  so the package installs without `psr/http-message` and friends. A PSR-18
  client adapts onto it in a few lines.
