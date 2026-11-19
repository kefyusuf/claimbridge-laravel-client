<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

use ClaimBridge\Medula\Exception\ClaimBridgeException;

/**
 * Transport failure (network, HTTP status, timeout). The underlying
 * cause is carried as the previous throwable.
 */
final class TransportException extends \RuntimeException implements ClaimBridgeException
{
    public function __construct(
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
