<?php declare(strict_types=1);

namespace Demo\Api;

use RuntimeException;

/**
 * What the carrier's HTTP client throws.
 *
 * It deliberately does NOT implement any interface of this package: it stands for a foreign
 * exception - Guzzle's, a SOAP fault, a vendor SDK's - that the driver has to translate before
 * it reaches the consumer (specification, section 6, rule 1).
 */
final class DemoApiException extends RuntimeException
{

    /**
     * @param int $status The HTTP status, or 0 when no response was received at all.
     * @param string|null $errorCode The carrier's own error code, when it sent one.
     */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly ?string $errorCode = null
    ) {
        parent::__construct($message, $status);
    }
}
