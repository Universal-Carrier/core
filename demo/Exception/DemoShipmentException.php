<?php declare(strict_types=1);

namespace Demo\Exception;

use RuntimeException;
use Throwable;
use UniversalCarrier\Core\Exception\ShipmentExceptionInterface;

/**
 * The carrier rejected the request content: retrying with the same input will fail again.
 *
 * The driver implements the interface on its own class rather than extending the one shipped
 * with the package (specification, section 6, rule 2), which lets it carry the carrier's own
 * error code - useful in logs and support tickets, meaningless to a portable consumer.
 */
final class DemoShipmentException extends RuntimeException implements ShipmentExceptionInterface
{

    public function __construct(
        string $message,
        public readonly ?string $carrierErrorCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
