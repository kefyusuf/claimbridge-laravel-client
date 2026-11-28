<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

use ClaimBridge\Medula\Spec\Bundle;

/**
 * Validates outgoing payloads against the guide-verified DVO contracts
 * from the spec bundle, before anything reaches the transport:
 * required fields first, then enum-bound field membership.
 */
final class PayloadValidator implements PayloadValidatorInterface
{
    public function __construct(private readonly Bundle $bundle) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws PayloadValidationException
     * @throws PayloadEnumException
     */
    public function validate(string $methodId, array $payload): void
    {
        $method = $this->bundle->methodById($methodId);
        if ($method === null) {
            throw new \RuntimeException("unknown method: {$methodId}");
        }

        $contract = $method['contract'] ?? null;
        $inputDvoId = is_array($contract) ? ($contract['inputDvo'] ?? null) : null;
        if (! is_string($inputDvoId)) {
            return; // no input contract to validate against
        }

        $dvo = $this->bundle->dvoById($inputDvoId);
        if ($dvo === null) {
            return;
        }

        $fields = $dvo['fields'] ?? null;
        if (! is_array($fields)) {
            return;
        }

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            if (($field['required'] ?? false) !== true) {
                continue;
            }

            $name = $field['name'] ?? null;
            if (! is_string($name)) {
                continue;
            }

            if (array_key_exists($name, $payload) && $payload[$name] !== null) {
                continue;
            }

            // Linked-takip exception: hastaTCKimlikNo is not required
            // when takipNo is provided (per the guide's linked-takip model).
            if ($name === 'hastaTCKimlikNo' && array_key_exists('takipNo', $payload)) {
                continue;
            }

            throw new PayloadValidationException(
                $name,
                "Zorunlu alan eksik - {$name}",
            );
        }

        $this->validateEnums($fields, $payload);
    }

    /**
     * Enum-bound fields must carry a code from the guide-verified enum
     * (non-null values only; dangling enumRefs are skipped defensively).
     *
     * @param  array<array-key, mixed>  $fields
     * @param  array<string, mixed>  $payload
     *
     * @throws PayloadEnumException
     */
    private function validateEnums(array $fields, array $payload): void
    {
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $name = $field['name'] ?? null;
            $enumRef = $field['enumRef'] ?? null;
            if (! is_string($name) || ! is_string($enumRef)) {
                continue;
            }

            $value = $payload[$name] ?? null;
            if ($value === null) {
                continue;
            }

            $enum = $this->bundle->enumById($enumRef);
            if ($enum === null) {
                continue;
            }

            $codes = $this->enumCodes($enum);
            if ($codes === []) {
                continue;
            }

            if (is_string($value) || is_int($value)) {
                if (! in_array((string) $value, $codes, true)) {
                    throw new PayloadEnumException(
                        $name,
                        $enumRef,
                        "Geçersiz enum değeri - {$name}={$value} ({$enumRef})",
                    );
                }
            }
        }
    }

    /**
     * Extracts the valid code list from an enum collection entry.
     *
     * @param  array<string, mixed>  $enum
     * @return list<string>
     */
    private function enumCodes(array $enum): array
    {
        $values = $enum['values'] ?? null;
        if (! is_array($values)) {
            return [];
        }

        $codes = [];
        foreach ($values as $entry) {
            if (is_array($entry) && is_string($entry['code'] ?? null)) {
                $codes[] = $entry['code'];
            }
        }

        return $codes;
    }
}
