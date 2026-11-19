<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Laravel;

use ClaimBridge\Medula\Client\ClaimSession;
use ClaimBridge\Medula\Client\FakeTransport;
use ClaimBridge\Medula\Client\MedulaClient;
use ClaimBridge\Medula\Spec\Bundle;
use ClaimBridge\Medula\Spec\BundleLoader;
use ClaimBridge\Medula\Transport\SandboxHttpTransport;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Laravel adapter: binds the guide-verified client stack into the
 * container, driven by config/claimbridge.php.
 */
final class ClaimBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/claimbridge.php', 'claimbridge');

        $this->app->singleton('claimbridge.bundle', static function (Container $app): Bundle {
            $path = $app->make(Config::class)->get('claimbridge.bundle_path');

            return BundleLoader::load(
                is_string($path) && $path !== ''
                    ? $path
                    : __DIR__.'/../../resources/bundle.json',
            );
        });

        $this->app->singleton('claimbridge.client', static function (Container $app): MedulaClient {
            $config = $app->make(Config::class);

            $sandboxUrl = $config->get('claimbridge.transport.sandbox_url');
            $fakeResponses = $config->get('claimbridge.fake.responses', []);
            /** @var array<string, array<string, mixed>|list<array<string, mixed>>> $fakeResponses */
            $fakeResponses = is_array($fakeResponses) ? $fakeResponses : [];

            $transport = $config->get('claimbridge.transport.driver') === 'sandbox-http'
                ? SandboxHttpTransport::discover(
                    is_string($sandboxUrl) ? $sandboxUrl : 'http://127.0.0.1:8123',
                )
                : new FakeTransport($fakeResponses);

            $bundle = $app->make('claimbridge.bundle');
            assert($bundle instanceof Bundle);

            return new MedulaClient(
                $transport,
                $bundle,
                $app->make(LoggerInterface::class),
            );
        });

        $this->app->bind('claimbridge.session', static function (Container $app): ClaimSession {
            $client = $app->make('claimbridge.client');
            assert($client instanceof MedulaClient);

            $bundle = $app->make('claimbridge.bundle');
            assert($bundle instanceof Bundle);

            return new ClaimSession($client, $bundle);
        });
    }

    public function boot(): void
    {
        $this->publishes(
            [
                __DIR__.'/../../config/claimbridge.php' => config_path('claimbridge.php'),
            ],
            'claimbridge-config',
        );
    }
}
