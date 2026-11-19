<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Transport;

use ClaimBridge\Medula\Client\Transport;
use ClaimBridge\Medula\Client\TransportException;

/**
 * Retry decorator: retries transport failures for idempotent read
 * methods (id contains "oku" or "sorgu" — the spec's read/sorgu
 * naming convention) up to maxAttempts, then rethrows.
 */
final class RetryTransport implements Transport
{
    public function __construct(
        private readonly Transport $inner,
        private readonly int $maxAttempts = 2,
    ) {}

    public function call(string $methodId, array $payload): array
    {
        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                return $this->inner->call($methodId, $payload);
            } catch (TransportException $e) {
                if (! $this->isIdempotent($methodId) || $attempt >= $this->maxAttempts) {
                    throw $e;
                }
            }
        }
    }

    private function isIdempotent(string $methodId): bool
    {
        return str_contains($methodId, 'oku') || str_contains($methodId, 'sorgu');
    }
}
