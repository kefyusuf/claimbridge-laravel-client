<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

use ClaimBridge\Medula\Spec\Bundle;

/**
 * Episode-scoped session mirroring the sandbox's workflow semantics:
 * successful results adopt their identifiers (takipNo, hastaBasvuruNo,
 * islemSiraNo, faturaTeslimNo, ...) and later calls auto-inject them
 * into payloads for fields the method's input DVO actually declares —
 * explicit payload values always win.
 */
final class ClaimSession
{
    /**
     * Identifier keys adopted from successful results (mirrors the
     * sandbox's EpisodeState identifier set).
     */
    private const IDENTIFIER_NAMES = [
        'hastaBasvuruNo',
        'takipNo',
        'islemSiraNo',
        'islemSiraNumaralari',
        'hizmetKayitReferansNo',
        'faturaTeslimNo',
        'faturaTutari',
        'bildirimNo',
    ];

    /** @var array<string, string> */
    private array $identifiers = [];

    public function __construct(
        private readonly MedulaClient $client,
        private readonly Bundle $bundle,
    ) {}

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
     * Routes any spec-bundle method: injects session identifiers for
     * fields the input DVO declares, then delegates to the client.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws PayloadValidationException
     * @throws PayloadEnumException
     * @throws UnexpectedResultException
     */
    public function call(string $methodId, array $payload): CallResult
    {
        $payload = $this->injectIdentifiers($methodId, $payload);

        $result = $this->client->call($methodId, $payload);

        if ($result->isSuccess()) {
            $this->adoptIdentifiers($result);
        }

        return $result;
    }

    /**
     * Current identifier snapshot (a copy — mutations never leak back).
     *
     * @return array<string, string>
     */
    public function identifiers(): array
    {
        return $this->identifiers;
    }

    /**
     * Pre-fills the payload with session identifiers for fields the
     * method's input DVO declares; explicit payload values win.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function injectIdentifiers(string $methodId, array $payload): array
    {
        if ($this->identifiers === []) {
            return $payload;
        }

        $fieldNames = $this->inputDvoFieldNames($methodId);
        if ($fieldNames === []) {
            return $payload;
        }

        foreach ($this->identifiers as $name => $value) {
            if (in_array($name, $fieldNames, true) && ! array_key_exists($name, $payload)) {
                $payload[$name] = $value;
            }
        }

        return $payload;
    }

    /**
     * Adopts identifier keys from a successful result contract.
     */
    private function adoptIdentifiers(CallResult $result): void
    {
        foreach (self::IDENTIFIER_NAMES as $name) {
            $value = $result->raw()[$name] ?? null;
            if (is_string($value)) {
                $this->identifiers[$name] = $value;
            }
        }
    }

    /**
     * Field names declared by the method's input DVO (empty when the
     * method has no input contract or the DVO is malformed).
     *
     * @return list<string>
     */
    private function inputDvoFieldNames(string $methodId): array
    {
        $method = $this->bundle->methodById($methodId);
        if ($method === null) {
            return [];
        }

        $contract = $method['contract'] ?? null;
        $inputDvoId = is_array($contract) ? ($contract['inputDvo'] ?? null) : null;
        if (! is_string($inputDvoId)) {
            return [];
        }

        $dvo = $this->bundle->dvoById($inputDvoId);
        if ($dvo === null) {
            return [];
        }

        $fields = $dvo['fields'] ?? null;
        if (! is_array($fields)) {
            return [];
        }

        $names = [];
        foreach ($fields as $field) {
            if (is_array($field) && is_string($field['name'] ?? null)) {
                $names[] = $field['name'];
            }
        }

        return $names;
    }
}
