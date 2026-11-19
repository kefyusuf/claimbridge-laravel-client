<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

/**
 * Client-side fail-fast validation error. Mirrors the sandbox's
 * SANDBOX_VALIDATION_ERROR semantics (missingFieldPath) without
 * anything reaching the transport.
 */
final class PayloadValidationException extends \RuntimeException
{
    public function __construct(
        public readonly string $missingFieldPath,
        string $message,
    ) {
        parent::__construct($message);
    }
}
