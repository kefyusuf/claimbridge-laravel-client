<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests;

use ClaimBridge\Medula\Client\FakeTransport;
use ClaimBridge\Medula\Client\MedulaClient;
use ClaimBridge\Medula\Client\PayloadValidationException;
use ClaimBridge\Medula\Spec\BundleLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MedulaClientTest extends TestCase
{
    #[Test]
    public function hasta_kabul_round_trips_through_the_transport(): void
    {
        $transport = new FakeTransport([
            'medula.hastane.hasta-kabul.hasta-kabul' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
                'hastaBasvuruNo' => 'SYNTHETIC_HASTABASVURUNO_000001',
                'takipNo' => 'SYNTHETIC_TAKIPNO_000001',
                'takipler' => [
                    [
                        'takipNo' => 'SYNTHETIC_TAKIPNO_000001',
                        'takipTipi' => 'N',
                        'takipDurumu' => '0',
                        'hastaBasvuruNo' => 'SYNTHETIC_HASTABASVURUNO_000001',
                    ],
                ],
            ],
        ]);
        $client = new MedulaClient($transport, BundleLoader::load(__DIR__.'/../resources/bundle.json'));

        $result = $client->hastaKabul($this->admissionPayload());

        $this->assertSame('0000', $result->sonucKodu);
        $this->assertSame('SYNTHETIC_TAKIPNO_000001', $result->takipNo);
        $this->assertSame('SYNTHETIC_HASTABASVURUNO_000001', $result->hastaBasvuruNo);
        $takipler = $result->takipler;
        $this->assertIsArray($takipler);
        $this->assertCount(1, $takipler);
    }

    #[Test]
    public function validation_fails_before_anything_reaches_the_transport(): void
    {
        $transport = new FakeTransport([]);
        $client = new MedulaClient($transport, BundleLoader::load(__DIR__.'/../resources/bundle.json'));

        $payload = $this->admissionPayload();
        unset($payload['bransKodu']);

        try {
            $client->hastaKabul($payload);
            $this->fail('expected PayloadValidationException');
        } catch (PayloadValidationException $e) {
            $this->assertSame('bransKodu', $e->missingFieldPath);
        }

        $this->assertSame([], $transport->recordedCalls());
    }

    #[Test]
    public function hizmet_kayit_carries_the_takip_from_admission(): void
    {
        $transport = new FakeTransport([
            'medula.hastane.hizmet-kayit.hizmet-kayit' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
                'takipNo' => 'SYNTHETIC_TAKIPNO_000001',
                'islemSiraNo' => 'SYNTHETIC_ISLEMSIRANO_000002',
            ],
        ]);
        $client = new MedulaClient($transport, BundleLoader::load(__DIR__.'/../resources/bundle.json'));

        $result = $client->hizmetKayit([
            'saglikTesisKodu' => 1,
            'takipNo' => 'SYNTHETIC_TAKIPNO_000001',
            'hastaBasvuruNo' => 'SYNTHETIC_HASTABASVURUNO_000001',
            'tanilar' => [['taniKodu' => 'J06.9', 'taniTipi' => '1']],
            'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
        ]);

        $this->assertSame('0000', $result->sonucKodu);
        $this->assertSame('SYNTHETIC_ISLEMSIRANO_000002', $result->islemSiraNo);

        $sent = $transport->recordedCalls();
        $this->assertCount(1, $sent);
        $this->assertSame('SYNTHETIC_TAKIPNO_000001', $sent[0]['payload']['takipNo']);
    }

    #[Test]
    public function sandbox_error_codes_pass_through_unchanged(): void
    {
        $transport = new FakeTransport([
            'medula.hastane.fatura-kayit.fatura-kayit' => [
                'sonucKodu' => 'SANDBOX_3001',
                'sonucMesaji' => 'Bu basvuru icin fatura zaten olusturulmus.',
            ],
        ]);
        $client = new MedulaClient($transport, BundleLoader::load(__DIR__.'/../resources/bundle.json'));

        $result = $client->faturaKayit($this->faturaPayload());

        $this->assertSame('SANDBOX_3001', $result->sonucKodu);
        $this->assertSame('Bu basvuru icin fatura zaten olusturulmus.', $result->sonucMesaji);
    }

    #[Test]
    public function generic_call_routes_any_bundle_method(): void
    {
        $transport = new FakeTransport([
            'medula.hastane.hasta-kabul.hasta-kabul-oku' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
            ],
        ]);
        $client = new MedulaClient($transport, BundleLoader::load(__DIR__.'/../resources/bundle.json'));

        $result = $client->call('medula.hastane.hasta-kabul.hasta-kabul-oku', [
            'saglikTesisKodu' => 1,
            'takipNo' => 'SYNTHETIC_TAKIPNO_000001',
            'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
        ]);

        $this->assertSame('0000', $result->sonucKodu);
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

    /**
     * @return array<string, mixed>
     */
    private function faturaPayload(): array
    {
        return [
            'saglikTesisKodu' => 1,
            'faturaTarihi' => '2026-11-19',
            'hastaBasvuruNo' => 'SYNTHETIC_BASVURU_000001',
            'faturaRefNo' => 'SYNTHETIC_FATURA_REF_001',
            'hizmetDetaylari' => [],
            'trafikKazasiOdemeYuzdesi' => 0,
            'ilaveUcret' => 0,
            'muvafakatVar' => 'E',
        ];
    }
}
