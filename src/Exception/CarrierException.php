<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Exception;

use Exception;

/**
 * Default base class for carrier errors.
 *
 * Drivers are free to ignore the concrete classes shipped by this package and to implement
 * the corresponding interfaces on their own exception hierarchy instead. Only the interfaces
 * are part of the contract.
 */
class CarrierException extends Exception implements CarrierExceptionInterface {}
