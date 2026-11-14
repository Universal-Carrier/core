<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Contract;

use UniversalCarrier\Core\Dto\{Label, ShipmentPackage, ShipmentRequest, ShipmentResponse};
use UniversalCarrier\Core\Enum\ShipmentOption;
use UniversalCarrier\Core\Exception\{CarrierExceptionInterface,
    ConnectionExceptionInterface,
    ShipmentExceptionInterface,
    UnsupportedOptionExceptionInterface};

/**
 * A carrier that books shipments and returns their labels.
 *
 * @see CarrierInterface for the rules every driver follows.
 */
interface ShipmentCreatorInterface extends CarrierInterface
{

    /**
     * Whether this instance honours the given shipment option.
     *
     * The answer MAY depend on how the instance was configured - an option is sometimes part
     * of the customer's contract with the carrier rather than of the carrier's API - but it
     * MUST NOT change during the lifetime of the instance, and MUST be answerable without any
     * network call.
     *
     * It MUST agree with the runtime behavior of {@see createShipment()}: a request exercising
     * an option reported as unsupported MUST cause it to throw
     * {@see UnsupportedOptionExceptionInterface}, and an option reported as supported MUST NOT.
     */
    public function supports(ShipmentOption $option): bool;

    /**
     * Books a shipment with the carrier and returns its tracking numbers and labels.
     *
     * This is the only method of the contract with a side effect: on success, a shipment
     * exists in the carrier's system and will be invoiced. Implementations MUST perform the
     * call synchronously and MUST NOT return a partially booked shipment - if any parcel
     * fails, the whole call throws.
     *
     * The response MUST contain exactly one {@see ShipmentPackage} per parcel of the request,
     * in the same order.
     *
     * {@see ShipmentRequest::$pickupPointId} MUST be set for a service that delivers to a
     * pickup point, and MUST NOT be set for a home delivery service; implementations MUST
     * throw {@see ShipmentExceptionInterface} on either mismatch rather than let the parcel
     * go to the wrong place.
     *
     * A {@see Label} always holds the label itself: an implementation whose carrier answers
     * with base64 MUST decode it, and one whose carrier answers with a link MUST download the
     * label before returning, retrying a temporary failure. If the shipment was booked but its
     * label cannot be obtained, the implementation MUST throw rather than return a package
     * without one - with an exception implementing {@see ConnectionExceptionInterface},
     * carrying the carrier's shipment identifier so the label can be fetched later instead of
     * the shipment being booked twice.
     *
     * An implementation MUST throw {@see UnsupportedOptionExceptionInterface}, rather than
     * silently drop the data or split the booking, when:
     *  - the request holds several parcels and {@see ShipmentOption::MULTI_PARCEL} is not
     *    supported;
     *  - a parcel declares an insurance value and {@see ShipmentOption::INSURANCE} is not
     *    supported;
     *  - the shipment requires a customs declaration, a parcel declares items, and
     *    {@see ShipmentOption::CUSTOMS_DECLARATION} is not supported.
     *
     * Whether a shipment requires a customs declaration is for the implementation to
     * determine, by its carrier's rules: two different country codes are neither necessary
     * nor sufficient.
     *
     * Dropping insurance or customs data is worse than refusing: the consumer believes it
     * obtained cover, or a customs declaration, and only finds out when the parcel is lost or
     * held at the border.
     *
     * @param ShipmentRequest $request The shipment to book.
     * @return ShipmentResponse The booked shipment, one package per requested parcel.
     * @throws ShipmentExceptionInterface If the carrier rejects the request content.
     * @throws ConnectionExceptionInterface If the carrier could not be reached.
     * @throws UnsupportedOptionExceptionInterface If the request uses an unsupported option.
     * @throws CarrierExceptionInterface For any other error.
     */
    public function createShipment(ShipmentRequest $request): ShipmentResponse;
}
