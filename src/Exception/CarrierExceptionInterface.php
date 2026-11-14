<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Exception;

use Throwable;

/**
 * Root interface for every exception thrown by a Universal Carrier implementation.
 *
 * Consumers MUST be able to catch any error originating from this package - or from any
 * conforming driver - with a single `catch (CarrierExceptionInterface $e)` block.
 *
 * Implementations MUST NOT throw exceptions that do not implement this interface from the
 * methods declared by {@see \UniversalCarrier\Core\Contract\CarrierInterface}.
 */
interface CarrierExceptionInterface extends Throwable {}
