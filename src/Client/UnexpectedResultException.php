<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

use ClaimBridge\Medula\Exception\ClaimBridgeException;

/**
 * The endpoint returned a response violating the result contract
 * (sonucKodu/sonucMesaji missing or of the wrong type).
 */
final class UnexpectedResultException extends \RuntimeException implements ClaimBridgeException {}
