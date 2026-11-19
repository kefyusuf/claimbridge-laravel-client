<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests;

use ClaimBridge\Medula\Client\PayloadValidationException;
use ClaimBridge\Medula\Client\PayloadValidator;
use ClaimBridge\Medula\Spec\BundleLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PayloadValidatorTest extends TestCase
{
    private PayloadValidator $validator;

    protected function setUp(): void
    {
        $bundle = BundleLoader::load(__DIR__.'/../resources/bundle.json');
        $this->validator = new PayloadValidator($bundle);
    }

    #[Test]
    public function passes_a_valid_provizyon_payload(): void
    {
        $payload = $this->admissionPayload();

        $this->validator->validate('medula.hastane.hasta-kabul.hasta-kabul', $payload);

        $this->addToAssertionCount(1); // no exception thrown
    }

    #[Test]
    public function fails_fast_on_the_first_missing_required_field(): void
    {
        $payload = $this->admissionPayload();
        unset($payload['hastaTCKimlikNo']);

        try {
            $this->validator->validate('medula.hastane.hasta-kabul.hasta-kabul', $payload);
            $this->fail('expected PayloadValidationException');
        } catch (PayloadValidationException $e) {
            $this->assertSame('hastaTCKimlikNo', $e->missingFieldPath);
            $this->assertStringContainsString('hastaTCKimlikNo', $e->getMessage());
        }
    }

    #[Test]
    public function linked_takip_exception_hasta_tc_not_required_when_takip_no_provided(): void
    {
        $payload = $this->admissionPayload();
        unset($payload['hastaTCKimlikNo']);
        $payload['takipNo'] = 'SYNTHETIC_TAKIP_000001';

        $this->validator->validate('medula.hastane.hasta-kabul.hasta-kabul', $payload);

        $this->addToAssertionCount(1); // no exception thrown
    }

    #[Test]
    public function hasta_tc_still_required_without_takip_no(): void
    {
        $payload = $this->admissionPayload();
        unset($payload['hastaTCKimlikNo']);

        $this->expectException(PayloadValidationException::class);

        $this->validator->validate('medula.hastane.hasta-kabul.hasta-kabul', $payload);
    }

    #[Test]
    public function validates_fatura_required_fields_including_muvafakat_var(): void
    {
        $payload = $this->faturaPayload();
        unset($payload['muvafakatVar']);

        try {
            $this->validator->validate('medula.hastane.fatura-kayit.fatura-kayit', $payload);
            $this->fail('expected PayloadValidationException');
        } catch (PayloadValidationException $e) {
            $this->assertSame('muvafakatVar', $e->missingFieldPath);
        }
    }

    #[Test]
    public function unknown_method_is_a_runtime_error(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unknown method');

        $this->validator->validate('does.not.exist', []);
    }

    /**
     * Required set per ProvizyonGirisDVO (guide-verified bundle).
     *
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
