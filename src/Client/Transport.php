<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

/**
 * Transport abstraction: how payloads reach the MEDULA-compatible
 * endpoint (sandbox, SOAP gateway, HTTP service...).
 */
interface Transport
{
    /**
     * Sends a payload for a spec method and returns the raw response
     * contract (sonucKodu, sonucMesaji, ...).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function call(string $methodId, array $payload): array;
}
