<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Exception;

/**
 * Thrown when a shipment request exercises an option the carrier does not honour.
 *
 * Implementations MUST throw this only for options reported as unsupported by
 * {@see \UniversalCarrier\Core\Contract\ShipmentCreatorInterface::supports()}, and MUST NOT
 * throw it for an option they report as supported.
 */
interface UnsupportedOptionExceptionInterface extends CarrierExceptionInterface {}
