<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

/**
 * Replay transport: returns sandbox-verbatim fixture responses per
 * method id. Records every call for assertions.
 */
final class FakeTransport implements Transport
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $responses;

    /** @var list<array{methodId: string, payload: array<string, mixed>}> */
    private array $calls = [];

    /**
     * @param array<string, array<string, mixed>|list<array<string, mixed>>> $responses
     */
    public function __construct(array $responses)
    {
        $this->responses = [];
        foreach ($responses as $methodId => $response) {
            $this->responses[$methodId] = array_is_list($response)
                ? $response
                : [$response];
        }
    }

    public function call(string $methodId, array $payload): array
    {
        $this->calls[] = ['methodId' => $methodId, 'payload' => $payload];

        $queue = $this->responses[$methodId] ?? [];
        $next = array_shift($queue);

        return $next ?? [
            'sonucKodu' => 'SANDBOX_1001',
            'sonucMesaji' => 'Takip bulunamadi.',
        ];
    }

    /**
     * @return list<array{methodId: string, payload: array<string, mixed>}>
     */
    public function recordedCalls(): array
    {
        return $this->calls;
    }
}
