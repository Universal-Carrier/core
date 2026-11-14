<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Contract;

use UniversalCarrier\Core\Dto\{PickupPoint, PickupPointQuery, ShipmentRequest};
use UniversalCarrier\Core\Exception\{CarrierExceptionInterface,
    ConnectionExceptionInterface,
    ShipmentExceptionInterface};

/**
 * A carrier that delivers to out-of-home locations and can list them.
 *
 * @see CarrierInterface for the rules every driver follows.
 */
interface PickupPointProviderInterface extends CarrierInterface
{

    /**
     * Finds out-of-home delivery locations matching a query.
     *
     * The returned points MUST be sorted by ascending distance from the queried location, and
     * MUST NOT exceed {@see PickupPointQuery::$limit} when one is given. Implementations
     * SHOULD apply every other criterion of the query their carrier supports, and MAY ignore
     * those it does not. When no location matches, implementations MUST return an empty array
     * rather than throw.
     *
     * When the same instance also implements {@see ShipmentCreatorInterface}, every returned
     * {@see PickupPoint::$id} MUST be accepted as {@see ShipmentRequest::$pickupPointId} by
     * {@see ShipmentCreatorInterface::createShipment()}.
     *
     * @param PickupPointQuery $query The search criteria.
     * @return PickupPoint[] The matching locations, nearest first. Possibly empty.
     * @throws ShipmentExceptionInterface If the carrier rejects the query content.
     * @throws ConnectionExceptionInterface If the carrier could not be reached.
     * @throws CarrierExceptionInterface For any other error.
     */
    public function getPickupPoints(PickupPointQuery $query): array;
}
