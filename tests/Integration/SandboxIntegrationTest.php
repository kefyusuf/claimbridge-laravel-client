<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests\Integration;

use ClaimBridge\Medula\Client\ClaimSession;
use ClaimBridge\Medula\Client\MedulaClient;
use ClaimBridge\Medula\Spec\BundleLoader;
use ClaimBridge\Medula\Transport\SandboxHttpTransport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Real end-to-end against the sandbox HTTP service (sandbox repo,
 * `pnpm serve`). Skipped unless SANDBOX_URL is set — the CI e2e job
 * and the local compose flow provide it.
 */
final class SandboxIntegrationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (getenv('SANDBOX_URL') === false || getenv('SANDBOX_URL') === '') {
            self::markTestSkipped('SANDBOX_URL not set — sandbox HTTP service not running');
        }
    }

    #[Test]
    public function full_claim_flow_over_http(): void
    {
        $transport = SandboxHttpTransport::discover((string) getenv('SANDBOX_URL'));
        $bundle = BundleLoader::load(__DIR__.'/../../resources/bundle.json');
        $client = new MedulaClient($transport, $bundle);
        $session = new ClaimSession($client, $bundle);

        $admission = $session->hastaKabul([
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
        ]);

        $this->assertSame('0000', $admission->sonucKodu);
        $this->assertNotEmpty($session->identifiers()['takipNo'] ?? null);

        $hizmet = $session->hizmetKayit([
            'saglikTesisKodu' => 1,
            'tanilar' => [['taniKodu' => 'J06.9', 'taniTipi' => '1']],
            'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
        ]);

        $this->assertSame('0000', $hizmet->sonucKodu);
        $this->assertNotEmpty($session->identifiers()['islemSiraNo'] ?? null);

        $fatura = $session->faturaKayit([
            'saglikTesisKodu' => 1,
            'faturaTarihi' => '2026-11-19',
            'faturaRefNo' => 'SYNTHETIC_FATURA_REF_E2E',
            'hizmetDetaylari' => [],
            'trafikKazasiOdemeYuzdesi' => 0,
            'ilaveUcret' => 0,
            'muvafakatVar' => 'E',
        ]);

        $this->assertSame('0000', $fatura->sonucKodu);
        $this->assertNotEmpty($session->identifiers()['faturaTeslimNo'] ?? null);

        // The sandbox's duplicate-invoice rule fires through the HTTP layer.
        $duplicate = $session->faturaKayit([
            'saglikTesisKodu' => 1,
            'faturaTarihi' => '2026-11-19',
            'faturaRefNo' => 'SYNTHETIC_FATURA_REF_E2E',
            'hizmetDetaylari' => [],
            'trafikKazasiOdemeYuzdesi' => 0,
            'ilaveUcret' => 0,
            'muvafakatVar' => 'E',
        ]);

        $this->assertSame('SANDBOX_3001', $duplicate->sonucKodu);
        $this->assertSame('Bu basvuru icin fatura zaten olusturulmus.', $duplicate->sonucMesaji);
    }
}
