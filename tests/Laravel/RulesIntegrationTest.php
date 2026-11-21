<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests\Laravel;

use ClaimBridge\Medula\Rules\HastaKabul\ProvizyonGirisDVORules;
use Illuminate\Support\Facades\Validator;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class RulesIntegrationTest extends TestCase
{
    #[Test]
    public function generated_rule_classes_are_synced_and_autoloadable(): void
    {
        $this->assertTrue(
            class_exists(ProvizyonGirisDVORules::class),
            'run scripts/sync-dto.mjs to fetch the generated rules package',
        );
    }

    #[Test]
    public function valid_payload_passes_the_generated_rules(): void
    {
        $validator = Validator::make(
            [
                'saglikTesisKodu' => 1,
                'hastaTCKimlikNo' => '10000000001',
                'sigortaliTuru' => '1',
                'bransKodu' => '1',
                'devredilenKurum' => '1',
                'provizyonTarihi' => '2026-11-21',
                'provizyonTipi' => 'N',
                'tedaviTuru' => 'A',
                'tedaviTipi' => '0',
                'takipTipi' => 'N',
                'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
            ],
            ProvizyonGirisDVORules::rules(),
        );

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function missing_required_field_fails_the_generated_rules(): void
    {
        $validator = Validator::make(
            ['saglikTesisKodu' => 1],
            ProvizyonGirisDVORules::rules(),
        );

        $this->assertTrue($validator->fails());
        $failed = array_keys($validator->failed());
        $this->assertContains('hastaTCKimlikNo', $failed);
        $this->assertContains('provizyonTipi', $failed);
    }

    #[Test]
    public function enum_bound_field_rejects_codes_outside_the_guide_list(): void
    {
        $payload = [
            'saglikTesisKodu' => 1,
            'hastaTCKimlikNo' => '10000000001',
            'sigortaliTuru' => '1',
            'bransKodu' => '1',
            'devredilenKurum' => '1',
            'provizyonTarihi' => '2026-11-21',
            'provizyonTipi' => 'N',
            'tedaviTuru' => 'A',
            'tedaviTipi' => '0',
            'takipTipi' => 'X-not-in-guide',
            'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
        ];

        $validator = Validator::make($payload, ProvizyonGirisDVORules::rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('takipTipi', $validator->errors()->toArray());
    }

    #[Test]
    public function optional_fields_stay_nullable_under_the_generated_rules(): void
    {
        $payload = [
            'saglikTesisKodu' => 1,
            'hastaTCKimlikNo' => '10000000001',
            'sigortaliTuru' => '1',
            'bransKodu' => '1',
            'devredilenKurum' => '1',
            'provizyonTarihi' => '2026-11-21',
            'provizyonTipi' => 'N',
            'tedaviTuru' => 'A',
            'tedaviTipi' => '0',
            'takipTipi' => 'N',
            'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
        ];

        // takipNo is nullable per the guide; absent must not fail.
        $validator = Validator::make($payload, ProvizyonGirisDVORules::rules());

        $this->assertFalse($validator->errors()->has('takipNo'));
    }
}
