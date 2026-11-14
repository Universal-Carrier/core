<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Contract;

use UniversalCarrier\Core\Exception\CarrierExceptionInterface;

/**
 * The identity every shipping carrier driver exposes.
 *
 * On its own, this interface declares no operation. A driver implements one or more of the
 * capability interfaces that extend it - {@see RateProviderInterface},
 * {@see PickupPointProviderInterface}, {@see ShipmentCreatorInterface},
 * {@see TrackingProviderInterface} - and only those: an operation a carrier does not offer is
 * simply absent, never a method that throws. Consumers discover what a driver can do with
 * `instanceof`, and type their own dependencies on the narrowest interface they need.
 *
 * An implementation encapsulates one carrier's API - REST, SOAP, EDI, whatever it happens to
 * be - behind these methods, and translates between that API and the Data Transfer Objects of
 * this package. Consumers depend on these interfaces only, and swapping a carrier for another
 * offering the same capabilities must require no change beyond the construction of the
 * implementation.
 *
 * The keywords MUST, MUST NOT, SHOULD and MAY in this documentation are to be interpreted as
 * described in RFC 2119.
 *
 * Implementations MUST be stateless: nothing learnt from one call may change what the next
 * one answers. That makes the read-only operations repeatable, but says nothing about
 * booking, which has a side effect at the carrier and is not idempotent - calling
 * {@see ShipmentCreatorInterface::createShipment()} twice MAY produce two shipments
 * (specification, section 5.7). Implementations MUST NOT throw exceptions that do not
 * implement {@see CarrierExceptionInterface}.
 */
interface CarrierInterface
{

    /**
     * A stable, machine-readable identifier for this carrier.
     *
     * Used as a key in registries, configuration and persisted records, it MUST be lowercase
     * snake_case (e.g. "colissimo", "mondial_relay", "dhl_express"), MUST be stable across
     * releases of the implementation, and SHOULD identify the carrier rather than the vendor
     * of the driver.
     */
    public function getCode(): string;

    /**
     * The carrier's commercial name, fit to display to an end user (e.g. "DHL Express").
     */
    public function getName(): string;
}
