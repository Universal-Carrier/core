<?php declare(strict_types=1);

namespace Demo\Exception;

use RuntimeException;
use Throwable;
use UniversalCarrier\Core\Exception\ConnectionExceptionInterface;

/**
 * The carrier could not be reached, or refused our credentials: this may succeed later.
 */
final class DemoConnectionException extends RuntimeException implements ConnectionExceptionInterface
{

    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
