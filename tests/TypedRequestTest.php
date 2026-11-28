<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests;

use ClaimBridge\Medula\Client\ClaimSession;
use ClaimBridge\Medula\Client\FakeTransport;
use ClaimBridge\Medula\Client\MedulaClient;
use ClaimBridge\Medula\Dto\HastaKabul\ProvizyonGirisDVO;
use ClaimBridge\Medula\Dto\HizmetKayit\HizmetKayitGirisDVO;
use ClaimBridge\Medula\Enum\DevredilenKurum;
use ClaimBridge\Medula\Enum\ProvizyonTipi;
use ClaimBridge\Medula\Enum\SigortaliTuru;
use ClaimBridge\Medula\Enum\TakipTipi;
use ClaimBridge\Medula\Enum\TedaviTipi;
use ClaimBridge\Medula\Enum\TedaviTuru;
use ClaimBridge\Medula\Spec\BundleLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Typed request builders (client backlog): the session methods accept
 * the generated input-DVO objects directly — they are normalized via
 * toArray() before validation, and identifier injection treats
 * explicit NULLs (which every DTO carries for unset fields) as
 * unset rather than as winning values.
 */
final class TypedRequestTest extends TestCase
{
    private const ADMISSION_METHOD = 'medula.hastane.hasta-kabul.hasta-kabul';

    private const SERVICE_METHOD = 'medula.hastane.hizmet-kayit.hizmet-kayit';

    private function session(FakeTransport $transport): ClaimSession
    {
        $bundle = BundleLoader::load(__DIR__.'/../resources/bundle.json');

        return new ClaimSession(new MedulaClient($transport, $bundle), $bundle);
    }

    #[Test]
    public function session_methods_accept_generated_input_dvo_objects(): void
    {
        // Enum-bound fields take generated enum objects — the typed
        // builder surface; toArray maps them back to guide codes.
        $dto = new ProvizyonGirisDVO(
            saglikTesisKodu: 1,
            hastaTCKimlikNo: 'SYNTHETIC_TC_000000001',
            sigortaliTuru: SigortaliTuru::from('1'),
            devredilenKurum: DevredilenKurum::from('1'),
            provizyonTarihi: '2026-11-28',
            provizyonTipi: ProvizyonTipi::from('N'),
            tedaviTuru: TedaviTuru::from('A'),
            tedaviTipi: TedaviTipi::from('0'),
            takipTipi: TakipTipi::from('N'),
            bransKodu: '1',
            ktsHbysKodu: 'SYNTHETIC_HBYS_001',
        );

        $transport = new FakeTransport([
            self::ADMISSION_METHOD => ['sonucKodu' => '0000', 'sonucMesaji' => 'ok'],
        ]);
        $session = $this->session($transport);

        $result = $session->hastaKabul($dto);

        $this->assertSame('0000', $result->sonucKodu);
        // The DTO was normalized to an array payload before the call.
        $this->assertSame(
            'SYNTHETIC_TC_000000001',
            $transport->recordedCalls()[0]['payload']['hastaTCKimlikNo'] ?? null,
        );
        $this->assertSame(
            'N',
            $transport->recordedCalls()[0]['payload']['provizyonTipi'] ?? null,
        );
    }

    #[Test]
    public function explicit_null_dto_fields_are_treated_as_unset_for_injection(): void
    {
        $transport = new FakeTransport([
            self::ADMISSION_METHOD => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'ok',
                'takipNo' => 'SYNTHETIC_TAKIPNO_000099',
                'hastaBasvuruNo' => 'SYNTHETIC_HASTABASVURUNO_000099',
            ],
            self::SERVICE_METHOD => ['sonucKodu' => '0000', 'sonucMesaji' => 'ok'],
        ]);
        $session = $this->session($transport);

        // A prior admission adopts the identifiers on the session.
        $session->hastaKabul([
            'saglikTesisKodu' => 1,
            'hastaTCKimlikNo' => 'SYNTHETIC_TC_000000001',
            'sigortaliTuru' => '1',
            'devredilenKurum' => '1',
            'provizyonTarihi' => '2026-11-28',
            'provizyonTipi' => 'N',
            'tedaviTuru' => 'A',
            'tedaviTipi' => '0',
            'takipTipi' => 'N',
            'bransKodu' => '1',
            'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
        ]);

        // The typed DTO leaves takipNo/hastaBasvuruNo unset (null) — the
        // session identifiers must fill them instead of the nulls winning.
        $dto = new HizmetKayitGirisDVO(
            takipNo: null,
            saglikTesisKodu: 1,
            hastaBasvuruNo: null,
            tanilar: [['taniKodu' => 'J06.9', 'taniTipi' => '1']],
            ktsHbysKodu: 'SYNTHETIC_HBYS_001',
        );

        $result = $session->hizmetKayit($dto);

        $this->assertSame('0000', $result->sonucKodu);
        $payload = $transport->recordedCalls()[1]['payload'];
        $this->assertSame('SYNTHETIC_TAKIPNO_000099', $payload['takipNo'] ?? null);
        $this->assertSame('SYNTHETIC_HASTABASVURUNO_000099', $payload['hastaBasvuruNo'] ?? null);
    }

    #[Test]
    public function generic_call_also_accepts_dto_objects(): void
    {
        $dto = new ProvizyonGirisDVO(
            saglikTesisKodu: 1,
            hastaTCKimlikNo: 'SYNTHETIC_TC_000000001',
            sigortaliTuru: SigortaliTuru::from('1'),
            devredilenKurum: DevredilenKurum::from('1'),
            provizyonTarihi: '2026-11-28',
            provizyonTipi: ProvizyonTipi::from('N'),
            tedaviTuru: TedaviTuru::from('A'),
            tedaviTipi: TedaviTipi::from('0'),
            takipTipi: TakipTipi::from('N'),
            bransKodu: '1',
            ktsHbysKodu: 'SYNTHETIC_HBYS_001',
        );

        $transport = new FakeTransport([
            self::ADMISSION_METHOD => ['sonucKodu' => '0000', 'sonucMesaji' => 'ok'],
        ]);
        $session = $this->session($transport);

        $result = $session->call(self::ADMISSION_METHOD, $dto);

        $this->assertSame('0000', $result->sonucKodu);
        $this->assertSame(
            'SYNTHETIC_HBYS_001',
            $transport->recordedCalls()[0]['payload']['ktsHbysKodu'] ?? null,
        );
    }
}
