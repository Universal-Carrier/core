<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Exception;

/**
 * Thrown when a value handed to a Data Transfer Object violates its own invariants.
 *
 * This covers structural validation only (malformed country code, negative amount, invalid
 * time format, ...). Business rules enforced by a carrier belong to
 * {@see ShipmentExceptionInterface} instead.
 */
interface InvalidArgumentExceptionInterface extends CarrierExceptionInterface {}
