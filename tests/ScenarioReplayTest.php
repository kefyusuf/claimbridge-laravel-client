<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests;

use ClaimBridge\Medula\Client\ClaimSession;
use ClaimBridge\Medula\Client\FakeTransport;
use ClaimBridge\Medula\Client\MedulaClient;
use ClaimBridge\Medula\Spec\BundleLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Scenario replay over the client stack (sandbox v0.7.4+): the
 * scenarioId reaches the transport, the scenario contract skips payload
 * validation, and the replay-echoed identifiers (guide s.143) are
 * adopted by the session so the episode can continue.
 */
final class ScenarioReplayTest extends TestCase
{
    private const ADMISSION_METHOD = 'medula.hastane.hasta-kabul.hasta-kabul';

    #[Test]
    public function call_scenario_forwards_the_scenario_id_to_the_transport(): void
    {
        $transport = new FakeTransport([
            self::ADMISSION_METHOD => [
                'sonucKodu' => '9000',
                'sonucMesaji' => 'Uyari mesaji...',
            ],
        ]);
        $bundle = BundleLoader::load(__DIR__.'/../resources/bundle.json');
        $client = new MedulaClient($transport, $bundle);

        $result = $client->callScenario(self::ADMISSION_METHOD, 'provizyon-under18-warning');

        $this->assertSame('9000', $result->sonucKodu);
        $this->assertSame('provizyon-under18-warning', $transport->lastScenarioId());
    }

    #[Test]
    public function call_scenario_skips_payload_validation(): void
    {
        $transport = new FakeTransport([
            self::ADMISSION_METHOD => [
                'sonucKodu' => '9000',
                'sonucMesaji' => 'Uyari mesaji...',
            ],
        ]);
        $bundle = BundleLoader::load(__DIR__.'/../resources/bundle.json');
        $client = new MedulaClient($transport, $bundle);

        // An empty payload would fail payload validation on a normal call
        // (saglikTesisKodu, hastaTCKimlikNo, ...); a scenario replay
        // carries the guide-verbatim contract instead.
        $result = $client->callScenario(self::ADMISSION_METHOD, 'provizyon-under18-warning', []);

        $this->assertSame('9000', $result->sonucKodu);
    }

    #[Test]
    public function session_scenario_replay_adopts_the_echoed_identifiers(): void
    {
        $transport = new FakeTransport([
            self::ADMISSION_METHOD => [
                'sonucKodu' => '9000',
                'sonucMesaji' => 'Uyari mesaji...',
                'takipNo' => 'SYNTHETIC_TAKIPNO_000042',
                'hastaBasvuruNo' => 'SYNTHETIC_HASTABASVURUNO_000041',
            ],
        ]);
        $bundle = BundleLoader::load(__DIR__.'/../resources/bundle.json');
        $session = new ClaimSession(new MedulaClient($transport, $bundle), $bundle);

        $result = $session->callScenario(self::ADMISSION_METHOD, 'provizyon-under18-warning');

        $this->assertSame('9000', $result->sonucKodu);
        // Guide s.143: "yine takip no dönecektir" — the echoed values
        // must land on the session for follow-up calls.
        $this->assertSame(
            'SYNTHETIC_TAKIPNO_000042',
            $session->identifiers()['takipNo'] ?? null,
        );
    }
}
