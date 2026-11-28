<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

use ClaimBridge\Medula\Spec\Bundle;
use ClaimBridge\Medula\Spec\BundleLoader;
use Psr\Log\LoggerInterface;

/**
 * Typed client for the MEDULA-compatible claim flow. Validates payloads
 * against the guide-verified DVO contracts (client-side, fail-fast),
 * enforces the response contract, and routes calls through the
 * configured transport.
 */
final class MedulaClient
{
    private readonly PayloadValidator $validator;

    public function __construct(
        private readonly Transport $transport,
        private readonly Bundle $bundle,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->validator = new PayloadValidator($bundle);
    }

    public static function create(Transport $transport, string $bundlePath = __DIR__.'/../../resources/bundle.json'): self
    {
        return new self($transport, BundleLoader::load($bundlePath));
    }

    /**
     * Patient admission (hasta-kabul): provizyon girişi.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws PayloadValidationException
     * @throws PayloadEnumException
     * @throws UnexpectedResultException
     */
    public function hastaKabul(array $payload): CallResult
    {
        return $this->call('medula.hastane.hasta-kabul.hasta-kabul', $payload);
    }

    /**
     * Service registration (hizmet-kayit).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws PayloadValidationException
     * @throws PayloadEnumException
     * @throws UnexpectedResultException
     */
    public function hizmetKayit(array $payload): CallResult
    {
        return $this->call('medula.hastane.hizmet-kayit.hizmet-kayit', $payload);
    }

    /**
     * Invoice registration (fatura-kayit).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws PayloadValidationException
     * @throws PayloadEnumException
     * @throws UnexpectedResultException
     */
    public function faturaKayit(array $payload): CallResult
    {
        return $this->call('medula.hastane.fatura-kayit.fatura-kayit', $payload);
    }

    /**
     * Routes any spec-bundle method through validation + transport.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws PayloadValidationException
     * @throws PayloadEnumException
     * @throws UnexpectedResultException
     */
    public function call(string $methodId, array $payload): CallResult
    {
        $this->validator->validate($methodId, $payload);

        $result = CallResult::from($this->transport->call($methodId, $payload));

        $this->logger?->info('claimbridge.call', [
            'methodId' => $methodId,
            'sonucKodu' => $result->sonucKodu,
        ]);

        return $result;
    }

    /**
     * Replays a spec-bundle scenario verbatim (sandbox v0.7.4+). The
     * scenario contract comes from the spec bundle, so payload
     * validation is deliberately skipped — an empty payload is the
     * normal replay form.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws UnexpectedResultException
     */
    public function callScenario(string $methodId, string $scenarioId, array $payload = []): CallResult
    {
        $result = CallResult::from($this->transport->call($methodId, $payload, $scenarioId));

        $this->logger?->info('claimbridge.scenario', [
            'methodId' => $methodId,
            'scenarioId' => $scenarioId,
            'sonucKodu' => $result->sonucKodu,
        ]);

        return $result;
    }
}
