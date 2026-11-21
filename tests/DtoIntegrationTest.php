<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests;

use ClaimBridge\Medula\Client\FakeTransport;
use ClaimBridge\Medula\Client\MedulaClient;
use ClaimBridge\Medula\Dto\HastaKabul\ProvizyonGirisDVO;
use ClaimBridge\Medula\Enum\TakipTipi;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DtoIntegrationTest extends TestCase
{
    #[Test]
    public function generated_dto_package_is_synced_and_autoloadable(): void
    {
        $this->assertTrue(
            class_exists(ProvizyonGirisDVO::class),
            'run scripts/sync-dto.mjs to fetch the generated DTO package',
        );
        $this->assertTrue(enum_exists(TakipTipi::class));
    }

    #[Test]
    public function dto_fromarray_coerces_enum_bound_fields(): void
    {
        $dto = ProvizyonGirisDVO::fromArray([
            'saglikTesisKodu' => 1,
            'takipTipi' => 'N',
            'provizyonTipi' => 'N',
            'tedaviTuru' => 'A',
        ]);

        $this->assertSame(1, $dto->saglikTesisKodu);
        $this->assertSame(TakipTipi::Normal, $dto->takipTipi);
        $this->assertNull($dto->hastaTCKimlikNo);
    }

    #[Test]
    public function dto_unknown_enum_codes_coerce_to_null(): void
    {
        $dto = ProvizyonGirisDVO::fromArray([
            'takipTipi' => 'X-not-in-guide',
        ]);

        $this->assertNull($dto->takipTipi);
    }

    #[Test]
    public function dto_toarray_round_trips_back_to_codes(): void
    {
        $dto = ProvizyonGirisDVO::fromArray([
            'saglikTesisKodu' => 1,
            'takipTipi' => 'N',
        ]);

        $array = $dto->toArray();

        $this->assertSame(1, $array['saglikTesisKodu']);
        $this->assertSame('N', $array['takipTipi']);
    }

    #[Test]
    public function dto_payload_flows_through_the_client(): void
    {
        $transport = new FakeTransport([
            'medula.hastane.hasta-kabul.hasta-kabul' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
                'takipNo' => 'SYNTHETIC_TAKIPNO_000001',
            ],
        ]);
        $client = MedulaClient::create($transport);

        $dto = ProvizyonGirisDVO::fromArray([
            'saglikTesisKodu' => 1,
            'hastaTCKimlikNo' => 'SYNTHETIC_TC_000000001',
            'sigortaliTuru' => '1',
            'devredilenKurum' => '1',
            'provizyonTarihi' => '2026-11-21',
            'provizyonTipi' => 'N',
            'tedaviTuru' => 'A',
            'tedaviTipi' => '0',
            'takipTipi' => 'N',
            'bransKodu' => '1',
            'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
        ]);

        $result = $client->hastaKabul($dto->toArray());

        $this->assertSame('0000', $result->sonucKodu);
        $this->assertSame('SYNTHETIC_TAKIPNO_000001', $result->takipNo);
    }
}
