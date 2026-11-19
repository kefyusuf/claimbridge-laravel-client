<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

use ClaimBridge\Medula\Spec\Bundle;

/**
 * Validates outgoing payloads against the guide-verified DVO required
 * fields from the spec bundle, before anything reaches the transport.
 */
final class PayloadValidator
{
    public function __construct(private readonly Bundle $bundle) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws PayloadValidationException
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
    }
}
