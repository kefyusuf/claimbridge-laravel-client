<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

use ClaimBridge\Medula\Spec\Bundle;
use ClaimBridge\Medula\Spec\BundleLoader;

/**
 * Typed client for the MEDULA-compatible claim flow. Validates payloads
 * against the guide-verified DVO contracts (client-side, fail-fast) and
 * routes calls through the configured transport.
 */
final class MedulaClient
{
    private readonly PayloadValidator $validator;

    public function __construct(
        private readonly Transport $transport,
        private readonly Bundle $bundle,
    ) {
        $this->validator = new PayloadValidator($bundle);
    }

    /** @param array<string, mixed> $payload */
    public static function create(Transport $transport, string $bundlePath = __DIR__.'/../../resources/bundle.json'): self
    {
        return new self($transport, BundleLoader::load($bundlePath));
    }

    /**
     * Patient admission (hasta-kabul): provizyon girişi.
     *
     * @param array<string, mixed> $payload
     */
    public function hastaKabul(array $payload): CallResult
    {
        return $this->call('medula.hastane.hasta-kabul.hasta-kabul', $payload);
    }

    /**
     * Service registration (hizmet-kayit).
     *
     * @param array<string, mixed> $payload
     */
    public function hizmetKayit(array $payload): CallResult
    {
        return $this->call('medula.hastane.hizmet-kayit.hizmet-kayit', $payload);
    }

    /**
     * Invoice registration (fatura-kayit).
     *
     * @param array<string, mixed> $payload
     */
    public function faturaKayit(array $payload): CallResult
    {
        return $this->call('medula.hastane.fatura-kayit.fatura-kayit', $payload);
    }

    /**
     * Routes any spec-bundle method through validation + transport.
     *
     * @param array<string, mixed> $payload
     */
    public function call(string $methodId, array $payload): CallResult
    {
        $this->validator->validate($methodId, $payload);

        return CallResult::from($this->transport->call($methodId, $payload));
    }
}
