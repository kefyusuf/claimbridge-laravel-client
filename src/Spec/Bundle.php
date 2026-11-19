<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Spec;

/**
 * Guide-verified spec bundle: the pinned copy of the generated
 * claimbridge-medula-spec output consumed by clients.
 *
 * Collections are validated and typed at the boundary — every entry must
 * be an object (JSON), so downstream code can rely on the shapes below.
 *
 * @property-read string                     $specVersion
 * @property-read list<array<string, mixed>> $services
 * @property-read list<array<string, mixed>> $methods
 * @property-read list<array<string, mixed>> $dvos
 * @property-read list<array<string, mixed>> $enums
 * @property-read list<array<string, mixed>> $rules
 * @property-read list<array<string, mixed>> $workflows
 * @property-read list<array<string, mixed>> $scenarios
 */
final class Bundle
{
    private const COLLECTIONS = [
        'services',
        'methods',
        'dvos',
        'enums',
        'rules',
        'workflows',
        'scenarios',
    ];

    private readonly string $specVersion;

    /** @var list<array<string, mixed>> */
    private array $services;

    /** @var list<array<string, mixed>> */
    private array $methods;

    /** @var list<array<string, mixed>> */
    private array $dvos;

    /** @var list<array<string, mixed>> */
    private array $enums;

    /** @var list<array<string, mixed>> */
    private array $rules;

    /** @var list<array<string, mixed>> */
    private array $workflows;

    /** @var list<array<string, mixed>> */
    private array $scenarios;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $version = $data['specVersion'] ?? null;
        if (! is_string($version)) {
            throw new \RuntimeException('bundle specVersion must be a string');
        }
        $this->specVersion = $version;

        $this->services = $this->collection($data, 'services');
        $this->methods = $this->collection($data, 'methods');
        $this->dvos = $this->collection($data, 'dvos');
        $this->enums = $this->collection($data, 'enums');
        $this->rules = $this->collection($data, 'rules');
        $this->workflows = $this->collection($data, 'workflows');
        $this->scenarios = $this->collection($data, 'scenarios');
    }

    public function specVersion(): string
    {
        return $this->specVersion;
    }

    public function __get(string $name): mixed
    {
        if ($name === 'specVersion') {
            return $this->specVersion;
        }

        if (in_array($name, self::COLLECTIONS, true)) {
            return $this->{$name};
        }

        return null;
    }

    public function __isset(string $name): bool
    {
        return $name === 'specVersion' || in_array($name, self::COLLECTIONS, true);
    }

    /** @return list<array<string, mixed>> */
    public function services(): array
    {
        return $this->services;
    }

    /** @return list<array<string, mixed>> */
    public function methods(): array
    {
        return $this->methods;
    }

    /** @return list<array<string, mixed>> */
    public function dvos(): array
    {
        return $this->dvos;
    }

    /** @return list<array<string, mixed>> */
    public function enums(): array
    {
        return $this->enums;
    }

    /** @return list<array<string, mixed>> */
    public function scenarios(): array
    {
        return $this->scenarios;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function methodById(string $id): ?array
    {
        foreach ($this->methods as $method) {
            if (($method['id'] ?? null) === $id) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function dvoById(string $id): ?array
    {
        foreach ($this->dvos as $dvo) {
            if (($dvo['id'] ?? null) === $id) {
                return $dvo;
            }
        }

        return null;
    }

    /**
     * Validates and normalizes one bundle collection.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function collection(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (! is_array($value)) {
            throw new \RuntimeException("bundle missing collection: {$key}");
        }

        $items = [];
        foreach ($value as $entry) {
            if (! is_array($entry)) {
                throw new \RuntimeException("bundle collection entry must be an object: {$key}");
            }
            /** @var array<string, mixed> $entry — JSON objects decode to string-keyed maps */
            $items[] = $entry;
        }

        return $items;
    }
}
