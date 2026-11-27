<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests\Integration;

use ClaimBridge\Medula\Client\ClaimSession;
use ClaimBridge\Medula\Client\MedulaClient;
use ClaimBridge\Medula\Dto\FaturaKayit\FaturaCevapDVO;
use ClaimBridge\Medula\Dto\HastaKabul\ProvizyonCevapDVO;
use ClaimBridge\Medula\Dto\HizmetKayit\HizmetKayitCevapDVO;
use ClaimBridge\Medula\Enum\SonucKodu;
use ClaimBridge\Medula\Spec\BundleLoader;
use ClaimBridge\Medula\Transport\SandboxHttpTransport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Output-DTO enrichment: the sandbox's DVO-driven generic response
 * composition must stay compatible with the generated output DTO
 * contracts — every HTTP result parses back into its output DVO via
 * fromArray with the guide fields populated. Skipped unless SANDBOX_URL
 * is set (CI e2e job / local compose flow).
 */
final class OutputDtoTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (getenv('SANDBOX_URL') === false || getenv('SANDBOX_URL') === '') {
            self::markTestSkipped('SANDBOX_URL not set — sandbox HTTP service not running');
        }
    }

    private function session(): ClaimSession
    {
        $transport = SandboxHttpTransport::discover((string) getenv('SANDBOX_URL'));
        $bundle = BundleLoader::load(__DIR__.'/../../resources/bundle.json');

        return new ClaimSession(new MedulaClient($transport, $bundle), $bundle);
    }

    private function admit(ClaimSession $session): string
    {
        $admission = $session->hastaKabul([
            'saglikTesisKodu' => 1,
            'hastaTCKimlikNo' => 'SYNTHETIC_TC_000000001',
            'sigortaliTuru' => '1',
            'devredilenKurum' => '1',
            'provizyonTarihi' => '2026-11-27',
            'provizyonTipi' => 'N',
            'tedaviTuru' => 'A',
            'tedaviTipi' => '0',
            'takipTipi' => 'N',
            'bransKodu' => '1',
            'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
        ]);
        $this->assertSame('0000', $admission->sonucKodu);

        return $session->identifiers()['takipNo'] ?? '';
    }

    #[Test]
    public function admission_response_parses_into_provizyon_cevap_dvo(): void
    {
        $session = $this->session();
        $admission = $session->hastaKabul([
            'saglikTesisKodu' => 1,
            'hastaTCKimlikNo' => 'SYNTHETIC_TC_000000001',
            'sigortaliTuru' => '1',
            'devredilenKurum' => '1',
            'provizyonTarihi' => '2026-11-27',
            'provizyonTipi' => 'N',
            'tedaviTuru' => 'A',
            'tedaviTipi' => '0',
            'takipTipi' => 'N',
            'bransKodu' => '1',
            'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
        ]);

        $dto = ProvizyonCevapDVO::fromArray($admission->raw());

        $this->assertSame('0000', $dto->sonucKodu);
        $this->assertNotEmpty($dto->hastaBasvuruNo);
        $this->assertNotEmpty($dto->takipNo);
        // M1 composite: the takipler rows echo the generated takip state.
        $this->assertNotEmpty($dto->takipler);
        $row = $dto->takipler[0] ?? null;
        $this->assertIsArray($row);
        $this->assertSame($dto->takipNo, $row['takipNo'] ?? null);
    }

    #[Test]
    public function service_registration_response_parses_into_hizmet_kayit_cevap_dvo(): void
    {
        $session = $this->session();
        $takipNo = $this->admit($session);

        $hizmet = $session->hizmetKayit([
            'saglikTesisKodu' => 1,
            'takipNo' => $takipNo,
            'hastaBasvuruNo' => $session->identifiers()['hastaBasvuruNo'] ?? '',
            'tanilar' => [['taniKodu' => 'J06.9', 'taniTipi' => '1']],
            'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
        ]);

        $dto = HizmetKayitCevapDVO::fromArray($hizmet->raw());

        $this->assertSame('0000', $dto->sonucKodu);
        $this->assertNotEmpty($dto->takipNo);
        // Guide composite: per-service rows (IslemBilgileriDVO) — the
        // sandbox's generic composition ships them empty for now; the
        // generated islemSiraNo is tracked on the session.
        $this->assertSame([], $dto->islemBilgileri);
        $this->assertNotEmpty($session->identifiers()['islemSiraNo'] ?? null);
        // M2 composite: hataliKayitlar is an empty array on success.
        $this->assertSame([], $dto->hataliKayitlar);
    }

    #[Test]
    public function invoice_response_parses_into_fatura_cevap_dvo(): void
    {
        $session = $this->session();
        $takipNo = $this->admit($session);

        $hizmet = $session->hizmetKayit([
            'saglikTesisKodu' => 1,
            'takipNo' => $takipNo,
            'hastaBasvuruNo' => $session->identifiers()['hastaBasvuruNo'] ?? '',
            'tanilar' => [['taniKodu' => 'J06.9', 'taniTipi' => '1']],
            'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
        ]);
        $this->assertSame('0000', $hizmet->sonucKodu);

        $fatura = $session->faturaKayit([
            'saglikTesisKodu' => 1,
            'faturaTarihi' => '2026-11-27',
            'faturaRefNo' => 'SYNTHETIC_FATURA_REF_E2E',
            'hizmetDetaylari' => [],
            'trafikKazasiOdemeYuzdesi' => 0,
            'ilaveUcret' => 0,
            'muvafakatVar' => 'E',
        ]);

        $dto = FaturaCevapDVO::fromArray($fatura->raw());

        $this->assertSame('0000', $dto->sonucKodu);
        $this->assertNotEmpty($dto->faturaTeslimNo);
    }

    #[Test]
    public function sonuc_kodu_enum_carries_the_9000_warning_code(): void
    {
        // 9000 is the guide's success-with-warning code (spec s.103/143):
        // the generated enum carries a dedicated case for it.
        $values = array_map(static fn ($case) => $case->value, SonucKodu::cases());
        $names = array_map(static fn ($case) => $case->name, SonucKodu::cases());
        $this->assertContains('0000', $values);
        $this->assertContains('9000', $values);
        $this->assertContains('UyariMesajiIleBasarili', $names);
    }
}
