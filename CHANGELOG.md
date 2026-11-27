# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.8.2] — 2026-11-28

### Changed

- **Synced package updated to spec v0.11.0** (code-list visual pass):
  `IstisnaiHal` gained the four letter codes I/M/B/V (I İş kazası,
  M Meslek Hastalığı, B Madde Bağımlılığı, V Acil Hal — guide s.158);
  `DevredilenKurum` is now the numeric-only 31-value list (the letter
  codes were misattributed there earlier); `ProvizyonGirisDVORules`
  in: list follows the corrected enum.

## [0.8.1] — 2026-11-27

### Changed

- **islemBilgileri composite pin** (tracks sandbox v0.7.2): the
  sandbox's service-registration response now composes one
  KayitliIslemBilgisiDVO row per submitted service line
  (guide Tablo 4.2.1.m, s.235) — the e2e test asserts the rows, the
  refNo echo and the session's last islemSiraNo instead of the old
  empty-array pin.

## [0.8.0] — 2026-11-27

### Added

- **Output-DTO e2e tests** (works with sandbox v0.7.1): the sandbox's
  DVO-driven generic response composition is now proven compatible with
  the generated output DTO contracts — every HTTP result parses back
  into its output DVO via `fromArray` (`ProvizyonCevapDVO` with the
  `takipler` echo composite, `HizmetKayitCevapDVO` with `hataliKayitlar`
  and the `islemBilgileri` composite, `FaturaCevapDVO` with
  `faturaTeslimNo`).

### Changed

- **Synced package updated to spec v0.10.0**: `SonucKodu` gained the
  guide-verified `9000` case (`UyariMesajiIleBasarili`) — the guide's
  success-with-warning code, not an error (spec pages 103/143).

## [0.7.0] — 2026-11-21

### Added

- **Generated rules integration** (works with spec v0.8.0): the synced
  package now carries `ClaimBridge\Medula\Rules\<Domain>\` — one Laravel
  rule class per input DVO — consumed directly via
  `Validator::make($payload, ProvizyonGirisDVORules::rules())`.
- **Rules integration tests** (5): valid payload passes, missing required
  fields report through `failed()`, enum-bound fields reject codes outside
  the guide's `in:` list, optional fields stay nullable.

## [0.6.0] — 2026-11-21

### Added

- **Generated DTO package integration**: `scripts/sync-dto.mjs` fetches the
  spec repository's generated PHP package (81 string-backed enums + 212
  readonly DTO classes, guide 3.1.1) into `generated/` through the git
  blobs API (fresh content, never CDN-stale; uses `GITHUB_TOKEN` /
  `gh auth token` to avoid rate limits). PSR-4 autoload now maps
  `ClaimBridge\Medula\` over both `src/` and `generated/`.
- **DTO integration tests**: `fromArray()` enum coercion
  (`TakipTipi::Normal` from `"N"`), unknown-code → null semantics,
  `toArray()` round-trip back to codes, and a typed-payload flow through
  `MedulaClient::hastaKabul()`.
- CI: DTO sync + autoloader regeneration before every test and e2e run.

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
