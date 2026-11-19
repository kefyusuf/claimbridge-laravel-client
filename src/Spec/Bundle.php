<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Spec;

/**
 * Guide-verified spec bundle: the pinned copy of the generated
 * claimbridge-medula-spec output consumed by clients.
 */
final class Bundle
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private readonly array $data)
    {
        foreach (['specVersion', 'services', 'methods', 'dvos', 'enums', 'rules', 'workflows', 'scenarios'] as $key) {
            if (!array_key_exists($key, $this->data)) {
                throw new \RuntimeException("bundle missing collection: {$key}");
            }
        }
    }

    public function specVersion(): string
    {
        return (string) $this->data['specVersion'];
    }

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function services(): array
    {
        return $this->data['services'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function methods(): array
    {
        return $this->data['methods'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dvos(): array
    {
        return $this->data['dvos'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function enums(): array
    {
        return $this->data['enums'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scenarios(): array
    {
        return $this->data['scenarios'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function methodById(string $id): ?array
    {
        foreach ($this->data['methods'] as $method) {
            if ($method['id'] === $id) {
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
        foreach ($this->data['dvos'] as $dvo) {
            if ($dvo['id'] === $id) {
                return $dvo;
            }
        }

        return null;
    }
}
