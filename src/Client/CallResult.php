<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

/**
 * Result contract returned by every client call: sonucKodu/sonucMesaji
 * plus any contract-specific keys accessible as properties. The result
 * contract is enforced at construction — responses without the two
 * required strings are rejected with UnexpectedResultException.
 *
 * @property-read string|null                    $hastaBasvuruNo
 * @property-read string|null                    $takipNo
 * @property-read string|null                    $islemSiraNo
 * @property-read string|null                    $islemSiraNumaralari
 * @property-read string|null                    $hizmetKayitReferansNo
 * @property-read string|null                    $faturaTeslimNo
 * @property-read string|null                    $faturaTutari
 * @property-read string|null                    $bildirimNo
 * @property-read list<array<string, mixed>>|null $takipler
 */
final class CallResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $sonucKodu,
        public readonly string $sonucMesaji,
        private readonly array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     *
     * @throws UnexpectedResultException
     */
    public static function from(array $raw): self
    {
        if (! isset($raw['sonucKodu']) || ! is_string($raw['sonucKodu'])) {
            throw new UnexpectedResultException(
                'response contract violated: sonucKodu missing',
            );
        }

        if (! isset($raw['sonucMesaji']) || ! is_string($raw['sonucMesaji'])) {
            throw new UnexpectedResultException(
                'response contract violated: sonucMesaji missing',
            );
        }

        return new self($raw['sonucKodu'], $raw['sonucMesaji'], $raw);
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
        return $this->sonucKodu === '0000';
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
