<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Laravel;

use ClaimBridge\Medula\Client\CallResult;
use Illuminate\Support\Facades\Facade;

/**
 * Facade over the container-bound MedulaClient singleton.
 *
 * @method static CallResult hastaKabul(array<string, mixed> $payload)
 * @method static CallResult hizmetKayit(array<string, mixed> $payload)
 * @method static CallResult faturaKayit(array<string, mixed> $payload)
 * @method static CallResult call(string $methodId, array<string, mixed> $payload)
 */
final class ClaimBridge extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'claimbridge.client';
    }
}
