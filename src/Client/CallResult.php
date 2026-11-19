<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

/**
 * Result contract returned by every client call: sonucKodu/sonucMesaji
 * plus any contract-specific keys accessible as properties.
 */
final class CallResult
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(private readonly array $raw)
    {
    }

    /** @param array<string, mixed> $raw */
    public static function from(array $raw): self
    {
        return new self($raw);
    }

    public function __get(string $name): mixed
    {
        return $this->raw[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->raw[$name]);
    }

    public function isSuccess(): bool
    {
        return ($this->raw['sonucKodu'] ?? null) === '0000';
    }

    /**
     * The complete raw response contract.
     *
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }
}
