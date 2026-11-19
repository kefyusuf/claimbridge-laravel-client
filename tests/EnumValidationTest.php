<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests;

use ClaimBridge\Medula\Client\FakeTransport;
use ClaimBridge\Medula\Client\MedulaClient;
use ClaimBridge\Medula\Client\PayloadEnumException;
use ClaimBridge\Medula\Spec\BundleLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnumValidationTest extends TestCase
{
    #[Test]
    public function valid_enum_values_pass_validation(): void
    {
        $bundle = BundleLoader::load(__DIR__.'/../resources/bundle.json');
        $transport = new FakeTransport([
            'medula.hastane.hasta-kabul.hasta-kabul' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
            ],
        ]);
        $client = new MedulaClient($transport, $bundle);

        $result = $client->hastaKabul($this->admissionPayload());

        $this->assertSame('0000', $result->sonucKodu);
    }

    #[Test]
    public function invalid_enum_value_fails_fast_before_transport(): void
    {
        $bundle = BundleLoader::load(__DIR__.'/../resources/bundle.json');
        $transport = new FakeTransport([]);
        $client = new MedulaClient($transport, $bundle);

        $payload = $this->admissionPayload();
        $payload['sigortaliTuru'] = '9';

        try {
            $client->hastaKabul($payload);
            $this->fail('expected PayloadEnumException');
        } catch (PayloadEnumException $e) {
            $this->assertSame('sigortaliTuru', $e->fieldPath);
            $this->assertSame('sigortali-turu', $e->enumId);
            $this->assertSame([], $transport->recordedCalls());
        }
    }

    #[Test]
    public function null_enum_value_is_skipped(): void
    {
        $bundle = BundleLoader::load(__DIR__.'/../resources/bundle.json');
        $transport = new FakeTransport([
            'medula.hastane.hasta-kabul.hasta-kabul' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
            ],
        ]);
        $client = new MedulaClient($transport, $bundle);

        $payload = $this->admissionPayload();
        $payload['ekdsYontemGerekce'] = null;

        $client->hastaKabul($payload);

        $this->assertCount(1, $transport->recordedCalls());
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
