<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests;

use ClaimBridge\Medula\Client\Transport;
use ClaimBridge\Medula\Client\TransportException;
use ClaimBridge\Medula\Transport\LoggingTransport;
use ClaimBridge\Medula\Transport\RetryTransport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransportDecoratorsTest extends TestCase
{
    #[Test]
    public function logging_transport_logs_each_completed_call(): void
    {
        $logger = new RecordingLogger;
        $inner = new RecordingTransport(['sonucKodu' => '0000', 'sonucMesaji' => 'ok']);
        $decorated = new LoggingTransport($inner, $logger);

        $decorated->call('medula.hastane.hasta-kabul.hasta-kabul', []);

        $this->assertCount(1, $logger->records);
        $this->assertSame('claimbridge.transport', $logger->records[0]['message']);
        $this->assertSame(
            'medula.hastane.hasta-kabul.hasta-kabul',
            $logger->records[0]['context']['methodId'],
        );
    }

    #[Test]
    public function read_method_retries_on_transport_failure_then_succeeds(): void
    {
        $inner = new FlakyTransport(['sonucKodu' => '0000', 'sonucMesaji' => 'ok']);
        $decorated = new RetryTransport($inner);

        $result = $decorated->call('medula.hastane.hasta-kabul.hasta-kabul-oku', []);

        $this->assertSame('0000', $result['sonucKodu']);
        $this->assertSame(2, $inner->calls);
    }

    #[Test]
    public function non_idempotent_method_is_not_retried(): void
    {
        $inner = new FlakyTransport(['sonucKodu' => '0000', 'sonucMesaji' => 'ok']);
        $decorated = new RetryTransport($inner);

        try {
            $decorated->call('medula.hastane.hasta-kabul.hasta-kabul', []);
            $this->fail('expected TransportException');
        } catch (TransportException) {
        }

        $this->assertSame(1, $inner->calls);
    }

    #[Test]
    public function retries_stop_after_the_attempt_budget(): void
    {
        $inner = new FlakyTransport(['sonucKodu' => '0000', 'sonucMesaji' => 'ok'], alwaysFail: true);
        $decorated = new RetryTransport($inner, maxAttempts: 3);

        try {
            $decorated->call('medula.hastane.hasta-kabul.hasta-kabul-oku', []);
            $this->fail('expected TransportException');
        } catch (TransportException) {
        }

        $this->assertSame(3, $inner->calls);
    }
}

/**
 * Always returns the queued response; counts calls.
 */
final class RecordingTransport implements Transport
{
    public int $calls = 0;

    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(private readonly array $response) {}

    public function call(string $methodId, array $payload): array
    {
        $this->calls++;

        return $this->response;
    }
}

/**
 * Fails on the first N calls with a TransportException.
 */
final class FlakyTransport implements Transport
{
    public int $calls = 0;

    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        private readonly array $response,
        private readonly bool $alwaysFail = false,
    ) {}

    public function call(string $methodId, array $payload): array
    {
        $this->calls++;

        if ($this->alwaysFail || $this->calls === 1) {
            throw new TransportException('transient failure');
        }

        return $this->response;
    }
}
