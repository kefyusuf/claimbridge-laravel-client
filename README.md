# claimbridge-laravel-client

Laravel-ready PHP client for the MEDULA-compatible claim flow defined by
[claimbridge-medula-spec](https://github.com/kefyusuf/claimbridge-medula-spec)
and emulated by
[claimbridge-sandbox](https://github.com/kefyusuf/claimbridge-sandbox).

Loads the pinned spec bundle (`resources/bundle.json`, spec v0.4.0),
validates outgoing payloads against the guide-verified DVO contracts,
and returns sandbox-verbatim result contracts.

> **No live SGK connection. No real patient data.** See the spec repo's
> [DISCLAIMER](https://github.com/kefyusuf/claimbridge-medula-spec/blob/main/DISCLAIMER.md).

## Status

**L2 (contract completion)**: enum validation (23 guide-verified enums) and
`ClaimSession` — the episode-scoped session that mirrors the sandbox's
workflow semantics (identifier adoption + auto-injection). On top of
**L1 (core hardening)**: exception hierarchy (`ClaimBridgeException` marker —
SANDBOX_* codes stay data, never exceptions), response contract enforcement,
optional PSR-3 logging hook, PHPStan (max level) + Laravel Pint quality gates.
On top of **L0 (contract layer)**: bundle loading, payload validation against
DVO required fields, transport abstraction, fake transport with
sandbox-verbatim fixtures, and the core client
(hasta-kabul → hizmet-kayit → fatura-kayit).

## Development (Docker)

PHP runs in containers — no host PHP installation required.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 install
docker run --rm -v "$PWD":/app -w /app composer:2 vendor/bin/phpunit
```

## Quick start

```php
use ClaimBridge\Medula\Client\MedulaClient;
use ClaimBridge\Medula\Client\FakeTransport;

$transport = new FakeTransport([
    'medula.hastane.hasta-kabul.hasta-kabul' => [
        'sonucKodu' => '0000',
        'sonucMesaji' => 'Islem basari ile tamamlandi.',
        'hastaBasvuruNo' => 'SYNTHETIC_...',
        'takipNo' => 'SYNTHETIC_...',
    ],
]);
$client = new MedulaClient($transport);

$result = $client->hastaKabul([
    'saglikTesisKodu' => 1,
    'hastaTCKimlikNo' => '...',
    'sigortaliTuru' => '1',
    'devredilenKurum' => '1',
    'provizyonTipi' => 'N',
    'tedaviTuru' => 'A',
    'takipTipi' => 'N',
    'bransKodu' => '1',
    'ktsHbysKodu' => '...',
]);

$result->sonucKodu;   // '0000'
$result->takipNo;     // carried from the response
```

Missing required fields fail fast client-side with the same
`missingFieldPath` semantics the sandbox uses, before anything
is sent over the transport.
