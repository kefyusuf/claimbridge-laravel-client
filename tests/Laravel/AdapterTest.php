<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests\Laravel;

use ClaimBridge\Medula\Client\ClaimSession;
use ClaimBridge\Medula\Client\FakeTransport;
use ClaimBridge\Medula\Client\MedulaClient;
use ClaimBridge\Medula\Laravel\ClaimBridge;
use ClaimBridge\Medula\Laravel\ClaimBridgeServiceProvider;
use ClaimBridge\Medula\Transport\SandboxHttpTransport;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class AdapterTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ClaimBridgeServiceProvider::class];
    }

    /**
     * @param  Application  $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['ClaimBridge' => ClaimBridge::class];
    }

    #[Test]
    public function provider_registers_a_singleton_client(): void
    {
        $client = $this->app->make('claimbridge.client');

        $this->assertInstanceOf(MedulaClient::class, $client);
        $this->assertSame(
            $client,
            $this->app->make('claimbridge.client'),
            'client binding must be a singleton',
        );
    }

    #[Test]
    public function fake_driver_is_the_default_transport(): void
    {
        $client = $this->app->make('claimbridge.client');
        assert($client instanceof MedulaClient);

        $reflection = new \ReflectionProperty(MedulaClient::class, 'transport');
        $reflection->setAccessible(true);
        $transport = $reflection->getValue($client);

        $this->assertInstanceOf(FakeTransport::class, $transport);
    }

    #[Test]
    public function facade_proxies_to_the_client(): void
    {
        $config = $this->app->make('config');
        assert($config instanceof Repository);
        $config->set('claimbridge.fake.responses', [
            'medula.hastane.hasta-kabul.hasta-kabul' => [
                'sonucKodu' => '0000',
                'sonucMesaji' => 'Islem basari ile tamamlandi.',
                'takipNo' => 'FACADE_TAKIP',
            ],
        ]);

        $result = ClaimBridge::hastaKabul([
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

        $this->assertSame('0000', $result->sonucKodu);
        $this->assertSame('FACADE_TAKIP', $result->takipNo);
    }

    #[Test]
    public function sandbox_http_driver_binds_the_discovered_transport(): void
    {
        $config = $this->app->make('config');
        assert($config instanceof Repository);
        $config->set('claimbridge.transport.driver', 'sandbox-http');
        $config->set('claimbridge.transport.sandbox_url', 'http://127.0.0.1:8123');

        $client = $this->app->make('claimbridge.client');
        assert($client instanceof MedulaClient);

        $reflection = new \ReflectionProperty(MedulaClient::class, 'transport');
        $reflection->setAccessible(true);
        $transport = $reflection->getValue($client);

        $this->assertInstanceOf(SandboxHttpTransport::class, $transport);
    }

    #[Test]
    public function session_factory_returns_a_fresh_session_per_resolution(): void
    {
        $first = $this->app->make('claimbridge.session');
        $second = $this->app->make('claimbridge.session');

        $this->assertInstanceOf(ClaimSession::class, $first);
        $this->assertNotSame($first, $second);
    }
}
