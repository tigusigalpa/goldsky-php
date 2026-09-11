# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial release of `goldsky-php`.
- Full coverage of the Goldsky REST API v1.2.0 (40 operations, OpenAPI 3.1.0).
- REST services: `Pipelines`, `Subgraphs`, `Webhooks`, `Edge`, `Catalogs`.
- Data-plane clients: `GraphQLClient` (public/private Subgraph endpoints),
  `RPCClient` (Edge JSON-RPC single/batch).
- `WebhookVerifier` with constant-time `goldsky-webhook-secret` comparison.
- `ProblemDetails` (RFC 9457) error parsing with classification predicates
  (`isNotFound`, `isRateLimited`, `isValidation`, etc.) and `Retry-After`
  support.
- `TransportException` for network/decode failures.
- `Config` fluent builder with base URL, user agent, Edge key, retry policy,
  timeout, and TLS verification options.
- `Requester` with centralized HTTP execution, Bearer authentication, retry
  with capped exponential backoff and jitter, `Retry-After` honouring, and
  credential redaction.
- `Page` and `Pager` for paginated list endpoints; completion inferred from
  `next_page_token` alone.
- Multipart subgraph deployment via streaming `multipart/form-data`.
- Laravel integration: `GoldskyServiceProvider`, `Goldsky` facade, and
  publishable `config/goldsky.php`.
- 60 PHPUnit tests covering all 40 REST operations plus edge cases
  (pagination, URL encoding, retries, redaction, problem parsing, GraphQL,
  Edge RPC, webhook verification).
- 8 runnable examples.
- Documentation: API coverage, upgrade guide, security guide.
- GitHub Actions CI workflow.
