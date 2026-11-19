# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
