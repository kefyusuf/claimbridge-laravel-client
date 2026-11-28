<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

/**
 * Replay transport: returns sandbox-verbatim fixture responses per
 * method id. Records every call for assertions.
 */
final class FakeTransport implements Transport
{
    /** @var array<string, array<string, mixed>|list<array<string, mixed>>> */
    private array $responses;

    /** @var list<array{methodId: string, payload: array<string, mixed>, scenarioId: string|null}> */
    private array $calls = [];

    private ?string $lastScenarioId = null;

    /**
     * @param  array<string, array<string, mixed>|list<array<string, mixed>>>  $responses
     */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function call(string $methodId, array $payload, ?string $scenarioId = null): array
    {
        $this->calls[] = ['methodId' => $methodId, 'payload' => $payload, 'scenarioId' => $scenarioId];
        $this->lastScenarioId = $scenarioId;

        $queue = $this->responses[$methodId] ?? [];
        // A single response map is treated as a one-entry queue.
        $list = array_is_list($queue) ? $queue : [$queue];
        /** @var array<string, mixed>|null $next */
        $next = array_shift($list);

        return $next ?? [
            'sonucKodu' => 'SANDBOX_1001',
            'sonucMesaji' => 'Takip bulunamadi.',
        ];
    }

    /**
     * @return list<array{methodId: string, payload: array<string, mixed>, scenarioId: string|null}>
     */
    public function recordedCalls(): array
    {
        return $this->calls;
    }

    public function lastScenarioId(): ?string
    {
        return $this->lastScenarioId;
    }
}
