<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Spec;

final class BundleLoader
{
    public static function load(string $path): Bundle
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException("bundle not readable: {$path}");
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return new Bundle($data);
    }
}
