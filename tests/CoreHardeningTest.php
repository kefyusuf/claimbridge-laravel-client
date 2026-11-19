<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests;

use ClaimBridge\Medula\Client\FakeTransport;
use ClaimBridge\Medula\Client\MedulaClient;
use ClaimBridge\Medula\Client\PayloadValidationException;
use ClaimBridge\Medula\Client\TransportException;
use ClaimBridge\Medula\Client\UnexpectedResultException;
use ClaimBridge\Medula\Exception\ClaimBridgeException;
use ClaimBridge\Medula\Spec\BundleLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class CoreHardeningTest extends TestCase
{
    #[Test]
    public function every_client_exception_implements_the_package_marker(): void
    {
        $bundle = BundleLoader::load(__DIR__.'/../resources/bundle.json');
        $client = new MedulaClient(new FakeTransport([]), $bundle);

        try {
            $client->hastaKabul([]);
            $this->fail('expected PayloadValidationException');
        } catch (PayloadValidationException $e) {
            $this->assertContains(
                ClaimBridgeException::class,
                class_implements($e) ?: [],
                'PayloadValidationException must implement the package marker',
            );
        }
    }

    #[Test]
    public function transport_exception_carries_the_previous_cause(): void
    {
        $cause = new \RuntimeException('connection reset');
        $e = new TransportException('transport failed', $cause);

        $this->assertContains(
            ClaimBridgeException::class,
            class_implements($e) ?: [],
            'TransportException must implement the package marker',
        );
        $this->assertSame($cause, $e->getPrevious());
        $this->assertStringContainsString('transport failed', $e->getMessage());
    }

    #[Test]
    public function unexpected_result_exception_implements_the_marker(): void
    {
        $e = new UnexpectedResultException('response contract violated');

        $this->assertContains(
            ClaimBridgeException::class,
            class_implements($e) ?: [],
            'UnexpectedResultException must implement the package marker',
        );
    }

    #[Test]
    public function malformed_response_without_sonuc_kodu_is_rejected(): void
    {
        $transport = new FakeTransport([
            'medula.hastane.hasta-kabul.hasta-kabul' => [
                'unexpected' => 'shape',
            ],
        ]);
        $client = new MedulaClient($transport, BundleLoader::load(__DIR__.'/../resources/bundle.json'));

        $this->expectException(UnexpectedResultException::class);
        $this->expectExceptionMessage('sonucKodu');

        $client->hastaKabul($this->admissionPayload());
    }

    #[Test]
    public function response_without_sonuc_mesaji_is_rejected(): void
    {
        $transport = new FakeTransport([
            'medula.hastane.hasta-kabul.hasta-kabul' => [
                'sonucKodu' => '0000',
            ],
        ]);
        $client = new MedulaClient($transport, BundleLoader::load(__DIR__.'/../resources/bundle.json'));

        $this->expectException(UnexpectedResultException::class);
        $this->expectExceptionMessage('sonucMesaji');

        $client->hastaKabul($this->admissionPayload());
    }

    #[Test]
    public function completed_calls_are_logged_with_method_and_result(): void
    {
        $logger = new RecordingLogger;
        $transport = new FakeTransport([
            'medula.hastane.hasta-kabul.hasta-kabul' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
                'takipNo' => 'SYNTHETIC_TAKIPNO_000001',
            ],
        ]);
        $client = new MedulaClient(
            $transport,
            BundleLoader::load(__DIR__.'/../resources/bundle.json'),
            $logger,
        );

        $client->hastaKabul($this->admissionPayload());

        $records = $logger->records;
        $this->assertCount(1, $records);
        $this->assertSame('info', $records[0]['level']);
        $this->assertSame('claimbridge.call', $records[0]['message']);
        $this->assertSame('medula.hastane.hasta-kabul.hasta-kabul', $records[0]['context']['methodId']);
        $this->assertSame('0000', $records[0]['context']['sonucKodu']);
    }

    #[Test]
    public function client_works_without_a_logger(): void
    {
        $transport = new FakeTransport([
            'medula.hastane.hasta-kabul.hasta-kabul' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
            ],
        ]);
        $client = new MedulaClient(
            $transport,
            BundleLoader::load(__DIR__.'/../resources/bundle.json'),
        );

        $result = $client->hastaKabul($this->admissionPayload());

        $this->assertTrue($result->isSuccess());
    }

    /**
     * @return array<string, mixed>
     */
    private function admissionPayload(): array
    {
        return [
            'saglikTesisKodu' => 1,
            'hastaTCKimlikNo' => 'SYNTHETIC_TC_000000001',
            'sigortaliTuru' => '1',
            'devredilenKurum' => '1',
            'provizyonTarihi' => '2026-11-19',
            'provizyonTipi' => 'N',
            'tedaviTuru' => 'A',
            'tedaviTipi' => '0',
            'takipTipi' => 'N',
            'bransKodu' => '1',
            'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
        ];
    }
}

/**
 * Minimal PSR-3 recorder for assertions.
 */
final class RecordingLogger implements LoggerInterface
{
    use LoggerTrait;

    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => is_string($level) ? $level : 'unknown',
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
