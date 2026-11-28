<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Transport;

use ClaimBridge\Medula\Client\Transport;
use Psr\Log\LoggerInterface;

/**
 * PSR-3 logging decorator: logs every completed transport call at info
 * level with the method id and result code as context.
 */
final class LoggingTransport implements Transport
{
    public function __construct(
        private readonly Transport $inner,
        private readonly LoggerInterface $logger,
    ) {}

    public function call(string $methodId, array $payload, ?string $scenarioId = null): array
    {
        $raw = $this->inner->call($methodId, $payload, $scenarioId);

        $this->logger->info('claimbridge.transport', [
            'methodId' => $methodId,
            'scenarioId' => $scenarioId,
            'sonucKodu' => is_string($raw['sonucKodu'] ?? null) ? $raw['sonucKodu'] : null,
        ]);

        return $raw;
    }
}
