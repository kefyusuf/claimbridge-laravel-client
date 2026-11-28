<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests;

use ClaimBridge\Medula\Client\FakeTransport;
use ClaimBridge\Medula\Client\MedulaClient;
use ClaimBridge\Medula\Client\PayloadValidationException;
use ClaimBridge\Medula\Client\PayloadValidator;
use ClaimBridge\Medula\Client\RulesValidator;
use ClaimBridge\Medula\Spec\BundleLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * L6 rules layer: an optional payload validator backed by the generated
 * Laravel rule classes — length (max:N) and membership (in:...) checks
 * that the default PayloadValidator does not perform. Same exception
 * contract: PayloadValidationException.
 */
final class RulesValidatorTest extends TestCase
{
    private const ADMISSION_METHOD = 'medula.hastane.hasta-kabul.hasta-kabul';

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'saglikTesisKodu' => 1,
            'hastaTCKimlikNo' => '10000000001',
            'sigortaliTuru' => '1',
            'devredilenKurum' => '1',
            'provizyonTarihi' => '2026-11-28',
            'provizyonTipi' => 'N',
            'tedaviTuru' => 'A',
            'tedaviTipi' => '0',
            'takipTipi' => 'N',
            'bransKodu' => '1',
            'ktsHbysKodu' => 'SYNTHETIC_HBYS_001',
        ];
    }

    #[Test]
    public function rejects_a_payload_exceeding_the_guide_max_length(): void
    {
        $validator = new RulesValidator(BundleLoader::load(__DIR__.'/../resources/bundle.json'));
        $payload = $this->validPayload();
        $payload['bransKodu'] = '123456'; // guide max is 4 (s.155)

        $this->expectException(PayloadValidationException::class);
        $this->expectExceptionMessage('bransKodu');

        $validator->validate(self::ADMISSION_METHOD, $payload);
    }

    #[Test]
    public function rejects_an_enum_value_outside_the_generated_in_list(): void
    {
        $validator = new RulesValidator(BundleLoader::load(__DIR__.'/../resources/bundle.json'));
        $payload = $this->validPayload();
        $payload['takipTipi'] = 'X-not-in-guide';

        $this->expectException(PayloadValidationException::class);
        $this->expectExceptionMessage('takipTipi');

        $validator->validate(self::ADMISSION_METHOD, $payload);
    }

    #[Test]
    public function passes_a_guide_fully_valid_payload(): void
    {
        $validator = new RulesValidator(BundleLoader::load(__DIR__.'/../resources/bundle.json'));

        $validator->validate(self::ADMISSION_METHOD, $this->validPayload());
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function methods_without_an_input_contract_stay_unvalidated(): void
    {
        $validator = new RulesValidator(BundleLoader::load(__DIR__.'/../resources/bundle.json'));

        // The disabled identity-check method carries no input contract.
        $validator->validate('medula.hastane.hasta-kabul.hasta-kabul-kimlik-dogrulama', []);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function medula_client_can_opt_into_the_rules_layer(): void
    {
        $transport = new FakeTransport([
            self::ADMISSION_METHOD => ['sonucKodu' => '0000', 'sonucMesaji' => 'ok'],
        ]);
        $client = new MedulaClient(
            $transport,
            BundleLoader::load(__DIR__.'/../resources/bundle.json'),
            null,
            new RulesValidator(BundleLoader::load(__DIR__.'/../resources/bundle.json')),
        );

        $payload = $this->validPayload();
        $payload['bransKodu'] = '123456';

        $this->expectException(PayloadValidationException::class);
        $client->call(self::ADMISSION_METHOD, $payload);
    }

    #[Test]
    public function the_default_validator_ignores_max_lengths(): void
    {
        $transport = new FakeTransport([
            self::ADMISSION_METHOD => ['sonucKodu' => '0000', 'sonucMesaji' => 'ok'],
        ]);
        // No third argument: the default PayloadValidator stays in charge.
        $client = new MedulaClient(
            $transport,
            BundleLoader::load(__DIR__.'/../resources/bundle.json'),
        );

        $payload = $this->validPayload();
        $payload['bransKodu'] = '123456';

        $result = $client->call(self::ADMISSION_METHOD, $payload);
        $this->assertSame('0000', $result->sonucKodu);
    }
}
