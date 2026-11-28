<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Transport;

use ClaimBridge\Medula\Client\Transport;
use ClaimBridge\Medula\Client\TransportException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * HTTP transport for the sandbox's session-based service (sandbox repo
 * v0.5.0+): lazily creates a session per transport instance and routes
 * every call through POST /sessions/:id/call.
 */
final class SandboxHttpTransport implements Transport
{
    private ?string $sessionId = null;

    public function __construct(
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly string $baseUrl,
    ) {}

    /**
     * Discovers a PSR-18 client + request factory in the runtime
     * environment (php-http/discovery).
     */
    public static function discover(string $baseUrl): self
    {
        return new self(
            Psr18ClientDiscovery::find(),
            Psr17FactoryDiscovery::findRequestFactory(),
            $baseUrl,
        );
    }

    public function call(string $methodId, array $payload, ?string $scenarioId = null): array
    {
        if ($this->sessionId === null) {
            $this->createSession();
        }

        $body = ['methodId' => $methodId, 'request' => $payload];
        if ($scenarioId !== null) {
            $body['scenarioId'] = $scenarioId;
        }

        return $this->post(
            "/sessions/{$this->sessionId}/call",
            $body,
        );
    }

    private function createSession(): void
    {
        $raw = $this->post('/sessions', []);

        $sessionId = $raw['sessionId'] ?? null;
        if (! is_string($sessionId) || $sessionId === '') {
            throw new TransportException('session creation returned no sessionId');
        }

        $this->sessionId = $sessionId;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        $request = $this->requests->createRequest(
            'POST',
            $this->baseUrl.$path,
        )->withHeader('content-type', 'application/json');

        $request->getBody()->write((string) json_encode($body, JSON_THROW_ON_ERROR));

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException(
                "transport failure on {$path}: {$e->getMessage()}",
                $e,
            );
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new TransportException(
                "transport failure on {$path}: HTTP {$status}",
            );
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode(
                (string) $response->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $e) {
            throw new TransportException(
                "transport failure on {$path}: invalid JSON response",
                $e,
            );
        }

        return $decoded;
    }
}
