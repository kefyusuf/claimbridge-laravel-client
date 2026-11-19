<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests;

use ClaimBridge\Medula\Client\TransportException;
use ClaimBridge\Medula\Transport\SandboxHttpTransport;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class HttpTransportTest extends TestCase
{
    #[Test]
    public function creates_session_lazily_and_delivers_the_call(): void
    {
        [$transport, $fake] = $this->transport();

        $fake->queue[] = new Response(
            200,
            [],
            '{"sessionId": "S000001"}',
        );
        $fake->queue[] = new Response(
            200,
            [],
            '{"sonucKodu": "0000", "sonucMesaji": "Islem basari ile tamamlandi.", "takipNo": "T1"}',
        );

        $result = $transport->call('medula.hastane.hasta-kabul.hasta-kabul', [
            'saglikTesisKodu' => 1,
        ]);

        $this->assertSame('0000', $result['sonucKodu']);
        $this->assertCount(2, $fake->requests);
        $this->assertSame('POST', $fake->requests[0]->getMethod());
        $this->assertSame('http://sandbox.test:8123/sessions', (string) $fake->requests[0]->getUri());
        $this->assertSame(
            'http://sandbox.test:8123/sessions/S000001/call',
            (string) $fake->requests[1]->getUri(),
        );
        $body = (string) $fake->requests[1]->getBody();
        $this->assertStringContainsString('hasta-kabul', $body);
        $this->assertStringContainsString('saglikTesisKodu', $body);
    }

    #[Test]
    public function reuses_the_session_across_calls(): void
    {
        [$transport, $fake] = $this->transport();

        $fake->queue[] = new Response(200, [], '{"sessionId": "S000001"}');
        $fake->queue[] = new Response(200, [], '{"sonucKodu": "0000", "sonucMesaji": "ok"}');
        $fake->queue[] = new Response(200, [], '{"sonucKodu": "SANDBOX_3001", "sonucMesaji": "duplicate"}');

        $transport->call('medula.hastane.hasta-kabul.hasta-kabul', []);
        $transport->call('medula.hastane.hasta-kabul.hasta-kabul', []);

        $sessionCreates = array_filter(
            $fake->requests,
            static fn (RequestInterface $r): bool => str_ends_with((string) $r->getUri(), '/sessions'),
        );
        $this->assertCount(1, $sessionCreates);
        $this->assertCount(3, $fake->requests);
    }

    #[Test]
    public function non_2xx_response_throws_transport_exception(): void
    {
        [$transport, $fake] = $this->transport();

        $fake->queue[] = new Response(200, [], '{"sessionId": "S000001"}');
        $fake->queue[] = new Response(500, [], 'boom');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('500');

        $transport->call('medula.hastane.hasta-kabul.hasta-kabul', []);
    }

    #[Test]
    public function network_error_wraps_in_transport_exception(): void
    {
        [$transport, $fake] = $this->transport();

        $fake->queue[] = new Response(200, [], '{"sessionId": "S000001"}');
        $request = new Request('POST', 'http://sandbox.test:8123/sessions/S000001/call');
        $fake->queue[] = new ConnectException('connection reset', $request);

        try {
            $transport->call('medula.hastane.hasta-kabul.hasta-kabul', []);
            $this->fail('expected TransportException');
        } catch (TransportException $e) {
            $this->assertNotNull($e->getPrevious());
            $this->assertStringContainsString('connection reset', $e->getMessage());
        }
    }

    #[Test]
    public function session_creation_failure_throws_transport_exception(): void
    {
        [$transport, $fake] = $this->transport();

        $fake->queue[] = new Response(500, [], 'session service down');

        $this->expectException(TransportException::class);

        $transport->call('medula.hastane.hasta-kabul.hasta-kabul', []);
    }

    /**
     * @return array{0: SandboxHttpTransport, 1: FakePsrClient}
     */
    private function transport(): array
    {
        $fake = new FakePsrClient;
        $transport = new SandboxHttpTransport(
            $fake,
            new HttpFactory,
            'http://sandbox.test:8123',
        );

        return [$transport, $fake];
    }
}

/**
 * Fake PSR-18 client: queues responses/exceptions, records requests.
 */
final class FakePsrClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|\Throwable> */
    public array $queue = [];

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \LogicException('FakePsrClient queue is empty');
        }
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }
}
