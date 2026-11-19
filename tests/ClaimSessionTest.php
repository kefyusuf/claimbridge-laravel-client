<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests;

use ClaimBridge\Medula\Client\ClaimSession;
use ClaimBridge\Medula\Client\FakeTransport;
use ClaimBridge\Medula\Client\MedulaClient;
use ClaimBridge\Medula\Spec\BundleLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClaimSessionTest extends TestCase
{
    #[Test]
    public function hasta_kabul_stores_identifiers_from_successful_result(): void
    {
        $env = $this->env([
            'medula.hastane.hasta-kabul.hasta-kabul' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
                'hastaBasvuruNo' => 'SYNTHETIC_HASTABASVURUNO_000001',
                'takipNo' => 'SYNTHETIC_TAKIPNO_000001',
            ],
        ]);

        $env['session']->hastaKabul($this->admissionPayload());

        $this->assertSame('SYNTHETIC_TAKIPNO_000001', $env['session']->identifiers()['takipNo']);
        $this->assertSame('SYNTHETIC_HASTABASVURUNO_000001', $env['session']->identifiers()['hastaBasvuruNo']);
    }

    #[Test]
    public function hizmet_kayit_auto_injects_session_identifiers(): void
    {
        $env = $this->env([
            'medula.hastane.hasta-kabul.hasta-kabul' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
                'hastaBasvuruNo' => 'SYNTHETIC_HASTABASVURUNO_000001',
                'takipNo' => 'SYNTHETIC_TAKIPNO_000001',
            ],
            'medula.hastane.hizmet-kayit.hizmet-kayit' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
                'islemSiraNo' => 'SYNTHETIC_ISLEMSIRANO_000002',
            ],
        ]);

        $env['session']->hastaKabul($this->admissionPayload());
        $env['session']->hizmetKayit([
            'saglikTesisKodu' => 1,
            'tanilar' => [['taniKodu' => 'J06.9', 'taniTipi' => '1']],
            'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
        ]);

        $calls = $env['transport']->recordedCalls();
        $this->assertCount(2, $calls);
        $sent = $calls[1]['payload'];
        $this->assertSame('SYNTHETIC_TAKIPNO_000001', $sent['takipNo']);
        $this->assertSame('SYNTHETIC_HASTABASVURUNO_000001', $sent['hastaBasvuruNo']);
    }

    #[Test]
    public function explicit_payload_value_wins_over_injection(): void
    {
        $env = $this->env([
            'medula.hastane.hasta-kabul.hasta-kabul' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
                'hastaBasvuruNo' => 'SYNTHETIC_HASTABASVURUNO_000001',
                'takipNo' => 'SYNTHETIC_TAKIPNO_000001',
            ],
            'medula.hastane.hizmet-kayit.hizmet-kayit' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
            ],
        ]);

        $env['session']->hastaKabul($this->admissionPayload());
        $env['session']->hizmetKayit([
            'saglikTesisKodu' => 1,
            'takipNo' => 'EXPLICIT_TAKIP',
            'tanilar' => [['taniKodu' => 'J06.9']],
            'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
        ]);

        $calls = $env['transport']->recordedCalls();
        $this->assertSame('EXPLICIT_TAKIP', $calls[1]['payload']['takipNo']);
    }

    #[Test]
    public function fatura_kayit_injects_hasta_basvuru_no(): void
    {
        $env = $this->env([
            'medula.hastane.hasta-kabul.hasta-kabul' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
                'hastaBasvuruNo' => 'SYNTHETIC_HASTABASVURUNO_000001',
                'takipNo' => 'SYNTHETIC_TAKIPNO_000001',
            ],
            'medula.hastane.fatura-kayit.fatura-kayit' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
                'faturaTeslimNo' => 'SYNTHETIC_FATURATESLIMNO_000003',
            ],
        ]);

        $env['session']->hastaKabul($this->admissionPayload());
        $result = $env['session']->faturaKayit([
            'saglikTesisKodu' => 1,
            'faturaTarihi' => '2026-11-19',
            'faturaRefNo' => 'SYNTHETIC_FATURA_REF_001',
            'hizmetDetaylari' => [],
            'trafikKazasiOdemeYuzdesi' => 0,
            'ilaveUcret' => 0,
            'muvafakatVar' => 'E',
        ]);

        $calls = $env['transport']->recordedCalls();
        $this->assertSame('SYNTHETIC_HASTABASVURUNO_000001', $calls[1]['payload']['hastaBasvuruNo']);
        $this->assertSame('SYNTHETIC_FATURATESLIMNO_000003', $result->faturaTeslimNo);
    }

    #[Test]
    public function failing_result_does_not_adopt_identifiers(): void
    {
        $env = $this->env([
            'medula.hastane.hasta-kabul.hasta-kabul' => [
                'sonucKodu' => 'SANDBOX_1001',
                'sonucMesaji' => 'Takip bulunamadi.',
                'takipNo' => 'POISON_TAKIP',
            ],
        ]);

        $env['session']->hastaKabul($this->admissionPayload());

        $this->assertSame([], $env['session']->identifiers());
    }

    #[Test]
    public function identifiers_snapshot_is_returned_by_value(): void
    {
        $env = $this->env([
            'medula.hastane.hasta-kabul.hasta-kabul' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
                'takipNo' => 'SYNTHETIC_TAKIPNO_000001',
            ],
        ]);

        $env['session']->hastaKabul($this->admissionPayload());
        $snapshot = $env['session']->identifiers();
        $snapshot['takipNo'] = 'MUTATED';

        $this->assertSame('SYNTHETIC_TAKIPNO_000001', $env['session']->identifiers()['takipNo']);
    }

    /**
     * Shared fixture bundle+transport+client+session.
     *
     * @param  array<string, array<string, mixed>|list<array<string, mixed>>>  $responses
     * @return array{session: ClaimSession, transport: FakeTransport}
     */
    private function env(array $responses): array
    {
        $bundle = BundleLoader::load(__DIR__.'/../resources/bundle.json');
        $transport = new FakeTransport($responses);
        $client = new MedulaClient($transport, $bundle);
        $session = new ClaimSession($client, $bundle);

        return ['session' => $session, 'transport' => $transport];
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
