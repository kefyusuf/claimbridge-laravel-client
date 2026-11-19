<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

use ClaimBridge\Medula\Exception\ClaimBridgeException;

/**
 * An enum-bound field carried a value that is not a member of the
 * guide-verified enum from the spec bundle.
 */
final class PayloadEnumException extends \RuntimeException implements ClaimBridgeException
{
    public function __construct(
        public readonly string $fieldPath,
        public readonly string $enumId,
        string $message,
    ) {
        parent::__construct($message);
    }
}
