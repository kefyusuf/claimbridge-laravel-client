<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

/**
 * A payload validation layer for outgoing spec-method calls. Default
 * implementation: PayloadValidator (guide-verified DVO contracts from
 * the spec bundle). Optional implementation: RulesValidator (the
 * generated Laravel rule classes).
 */
interface PayloadValidatorInterface
{
    /**
     * Validates the payload for a spec method; must throw on failure.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws PayloadValidationException
     * @throws PayloadEnumException
     */
    public function validate(string $methodId, array $payload): void;
}
