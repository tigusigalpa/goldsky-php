# Security Guide

This document describes the security posture of the goldsky-php SDK and the
responsibilities of the caller.

## Secrets

### REST project API token

- Scoped to a single Goldsky project.
- Sent as `Authorization: Bearer <token>` on every REST request.
- Stored as a private field on the `Requester`; never exposed via getters that
  could leak into logs.
- Never included in exception messages, error bodies, or log output.

### Edge endpoint API key

- A separate secret from the REST token.
- Sent in the Edge RPC `X-ERPC-Secret-Token` request header.
- Stored as a private field on `RPCClient`; never placed in a URL.
- Never included in exception messages or log output.
- Obtain from `Edge::create()` (one-time) or `Edge::revealKey()`.

### Webhook delivery secret

- Returned once at webhook creation time in `CreateWebhookResponse`.
- Sent by Goldsky as the `goldsky-webhook-secret` header on every delivery.
- Verify with `WebhookVerifier::verifySecret()` using constant-time
  comparison (`hash_equals`).
- Never log the secret value.

## Credential redaction

- The SDK does not log credentials. The default logger is `NullLogger`.
- Exception messages never include the Bearer token or Edge API key.
- `ProblemDetails` messages are server-authored and do not echo secrets.

## TLS

- TLS verification is enabled by default (`verify_tls = true`).
- Disable only for local testing via `Config::withVerifyTls(false)` or
  `GOLDSKY_VERIFY_TLS=false`.
- The Edge RPC endpoint is HTTPS only; there is no WebSocket/subscription
  support.

## Retry safety

- Only safe reads (GET, HEAD, OPTIONS) are retried by default.
- Mutations are not retried automatically because Goldsky does not document
  idempotency keys.
- Opt-in mutation retry via `Config::withRetryMutations(true)` is unsafe and
  may cause duplicate resource creation.
- Streaming multipart subgraph deployments are never retried because their
  input streams cannot be replayed safely.

## Response limits

- REST, GraphQL, and Edge RPC responses are limited to 16 MiB by default.
- Configure a different positive limit with
  `Config::withMaxResponseBodyBytes(...)` only when the expected response size
  is known and appropriate for the application.

## Reporting security issues

Report security vulnerabilities in the Goldsky platform to Goldsky directly.
Report SDK-specific issues via the [GitHub issue tracker](https://github.com/tigusigalpa/goldsky-php/issues).

## Responsible disclosure

Do not include real API tokens, Edge keys, or webhook secrets in bug reports,
test cases, or example code. Use placeholder values like `test-token` or
`edge-key`.
