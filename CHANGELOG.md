# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.0] — 2026-11-19

### Added
- **Laravel adapter** (the thin shell on the stable core, per the
  four-layer architecture):
  - `ClaimBridgeServiceProvider` — container bindings driven by
    `config/claimbridge.php`: `claimbridge.bundle` (singleton),
    `claimbridge.client` (singleton, driver-selected transport:
    `fake` | `sandbox-http`), `claimbridge.session` (fresh per
    resolution). Publishable config via `php artisan
    vendor:publish --tag=claimbridge-config`.
  - `ClaimBridge` facade over the client singleton
    (`ClaimBridge::hastaKabul(...)`, `hizmetKayit(...)`,
    `faturaKayit(...)`, `call(...)`).
  - Auto-discovery via composer `extra.laravel` (provider + alias).
- Adapter tested with `orchestra/testbench` (5 tests): singleton
  semantics, default fake driver, facade proxying, sandbox-http driver
  binding, per-resolution session factory.

## [0.4.0] — 2026-11-19

### Added
- **`SandboxHttpTransport`** (PSR-18): session-aware HTTP transport for the
  sandbox's session-based service (sandbox v0.5.0+) — lazily creates a
  session, routes calls through `POST /sessions/:id/call`, wraps network/HTTP
  failures in `TransportException` with the previous throwable. Ships with
  `SandboxHttpTransport::discover()` (php-http/discovery) so consumers can
  use any PSR-18 client without hard-coding one.
- **`LoggingTransport`** (PSR-3 decorator) and **`RetryTransport`** (retries
  transport failures for idempotent read/sorgu methods only — registration
  and invoice submissions are never retried).
- **Real end-to-end**: a dedicated e2e flow (admission → service → invoice →
  duplicate rejection over HTTP, sandbox `SANDBOX_3001` verbatim) both locally
  and as a CI job that clones the sandbox, serves it and runs the client
  against it.

## [0.3.0] — 2026-11-19

### Added
- **Enum validation**: enum-bound fields (7 on `ProvizyonGirisDVO` alone; 23
  enums in the bundle) are validated against the guide-verified code lists —
  invalid values fail fast with `PayloadEnumException` (`fieldPath` + `enumId`)
  before anything reaches the transport. Null values and dangling enumRefs are
  skipped defensively.
- **`ClaimSession`**: episode-scoped session mirroring the sandbox's workflow
  semantics — successful results adopt their identifiers (takipNo,
  hastaBasvuruNo, islemSiraNo, faturaTeslimNo, ...), later calls auto-inject
  them into payloads for fields the method's input DVO declares, explicit
  payload values win, and failing results never poison the session.

## [0.2.0] — 2026-11-19

### Added
- Exception hierarchy: `ClaimBridgeException` marker interface;
  `PayloadValidationException`, `TransportException` and
  `UnexpectedResultException` implement it. SANDBOX_* result codes remain
  data, not exceptions.
- Response contract enforcement: responses without `sonucKodu` /
  `sonucMesaji` strings now fail fast with `UnexpectedResultException`.
- Optional PSR-3 logging hook: `MedulaClient` logs every completed call at
  info level (`claimbridge.call`) with `methodId` and `sonucKodu` context.
- Quality gates: PHPStan (max level) and Laravel Pint, wired into
  `composer check` and CI.
- Governance: `SECURITY.md`, `CHANGELOG.md`, `CITATION.cff`.

## [0.1.0] — 2026-11-19

### Added
- Contract layer: pinned spec bundle (v0.4.0) loading, DVO required-field
  payload validation (client-side fail-fast, `missingFieldPath` semantics,
  linked-takip `hastaTCKimlikNo` exception).
- `Transport` interface with `FakeTransport` replay transport.
- `CallResult` with property access and `isSuccess()`.
- `MedulaClient` typed methods: `hastaKabul()`, `hizmetKayit()`,
  `faturaKayit()`, plus generic `call()`.
- Docker-based development loop (composer:2 image, no host PHP).
