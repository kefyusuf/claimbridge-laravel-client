<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Exception;

/**
 * Marker interface for every claimbridge exception. SANDBOX_* result
 * codes are data, not exceptions — exceptions here mean client-side or
 * transport failures only.
 */
interface ClaimBridgeException extends \Throwable {}
