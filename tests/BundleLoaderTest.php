<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Tests;

use ClaimBridge\Medula\Spec\BundleLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BundleLoaderTest extends TestCase
{
    #[Test]
    public function loads_the_pinned_spec_bundle_with_all_collections(): void
    {
        $bundle = BundleLoader::load(__DIR__.'/../resources/bundle.json');

        $this->assertSame('0.4.0', $bundle->specVersion);
        $this->assertCount(3, $bundle->services);
        $this->assertCount(25, $bundle->methods);
        $this->assertCount(70, $bundle->dvos);
        $this->assertCount(23, $bundle->enums);
        $this->assertCount(6, $bundle->scenarios);
    }

    #[Test]
    public function resolves_the_hasta_kabul_method_contract(): void
    {
        $bundle = BundleLoader::load(__DIR__.'/../resources/bundle.json');

        $method = $bundle->methodById('medula.hastane.hasta-kabul.hasta-kabul');

        $this->assertNotNull($method);
        $contract = $method['contract'] ?? null;
        $this->assertIsArray($contract);
        $this->assertSame('ProvizyonGirisDVO', $contract['inputDvo'] ?? null);
        $this->assertSame('ProvizyonCevapDVO', $contract['outputDvo'] ?? null);
    }

    #[Test]
    public function resolves_dvo_and_returns_null_for_unknown_ids(): void
    {
        $bundle = BundleLoader::load(__DIR__.'/../resources/bundle.json');

        $dvo = $bundle->dvoById('ProvizyonGirisDVO');
        $this->assertNotNull($dvo);
        $fields = $dvo['fields'] ?? null;
        $this->assertIsArray($fields);
        $first = $fields[0] ?? null;
        $this->assertIsArray($first);
        $this->assertSame('saglikTesisKodu', $first['name'] ?? null);

        $this->assertNull($bundle->methodById('does.not.exist'));
        $this->assertNull($bundle->dvoById('NoSuchDVO'));
    }

    #[Test]
    public function rejects_a_bundle_missing_a_collection(): void
    {
        $path = sys_get_temp_dir().'/claimbridge-bundle-missing.json';
        file_put_contents($path, json_encode(['specVersion' => '0.4.0']));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('bundle missing collection');

        BundleLoader::load($path);
    }
}
