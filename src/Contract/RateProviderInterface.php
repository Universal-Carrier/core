<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Contract;

use UniversalCarrier\Core\Dto\{Rate, RateRequest, ShipmentRequest};
use UniversalCarrier\Core\Exception\{CarrierExceptionInterface,
    ConnectionExceptionInterface,
    ShipmentExceptionInterface};

/**
 * A carrier that quotes shipping options.
 *
 * @see CarrierInterface for the rules every driver follows.
 */
interface RateProviderInterface extends CarrierInterface
{

    /**
     * Quotes the shipping options available for a shipment.
     *
     * The returned rates MUST be sorted by ascending price.
     *
     * Asking is always allowed. When the carrier has nothing to offer for the shipment - a
     * parcel heavier or larger than any of its services accepts, a destination it does not
     * serve - implementations MUST return an empty array rather than throw: having no rate is
     * an answer, not an error. Only a request the carrier cannot even assess - a postal code
     * that does not exist, one that does not match the country's format - MUST throw
     * {@see ShipmentExceptionInterface}.
     *
     * When the same instance also implements {@see ShipmentCreatorInterface}, every returned
     * {@see Rate::$serviceCode} MUST be accepted as {@see ShipmentRequest::$serviceCode} by
     * {@see ShipmentCreatorInterface::createShipment()}.
     *
     * @param RateRequest $request The shipment to quote.
     * @return Rate[] The available options, cheapest first. Possibly empty.
     * @throws ShipmentExceptionInterface If the carrier rejects the request content.
     * @throws ConnectionExceptionInterface If the carrier could not be reached.
     * @throws CarrierExceptionInterface For any other error.
     */
    public function getRates(RateRequest $request): array;
}
