<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Exception;

/**
 * Thrown when the carrier rejects a request because of its content.
 *
 * Covers invalid or undeliverable addresses, weight and dimension limits, unknown service
 * codes, unknown pickup points, and any other business rule violation reported by the carrier.
 *
 * Such an error is deterministic: retrying the exact same request MUST be expected to fail again.
 */
interface ShipmentExceptionInterface extends CarrierExceptionInterface {}
