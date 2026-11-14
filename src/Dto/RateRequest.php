<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use DateTimeImmutable;
use UniversalCarrier\Core\Contract\ShipmentDataInterface;
use UniversalCarrier\Core\Exception\InvalidArgumentException;
use UniversalCarrier\Core\Support\Assert;
use UniversalCarrier\Core\Support\ShipmentDataTrait;

/**
 * Immutable Data Transfer Object describing a shipment to be quoted.
 *
 * Rating and booking are deliberately given separate request objects. A quote is asked before
 * a service has been chosen - the service code is the answer, not part of the question - and
 * before order references or label languages are known. Reusing {@see ShipmentRequest} here
 * would force callers to invent values for fields that only exist once a rate is accepted.
 */
final readonly class RateRequest implements ShipmentDataInterface
{

    use ShipmentDataTrait;

    /** @var Parcel[] */
    public array $parcels;

    public ?string $pickupPointId;

    /**
     * @param Address $sender The origin address.
     * @param Address $recipient The destination address.
     * @param Parcel[] $parcels The parcels to quote, at least one.
     * @param DateTimeImmutable|null $shippingDate The date the parcels are handed to the
     *   carrier. Rates and transit times depend on it. Null lets the implementation assume
     *   the next possible collection.
     * @param string|null $pickupPointId Quote a delivery to this specific pickup point.
     * @param string[] $serviceCodes Restrict the quote to these carrier service codes. An
     *   empty list asks for every service available for the shipment.
     * @param array<string, mixed> $metadata Carrier-specific rating options.
     * @throws InvalidArgumentException
     */
    public function __construct(
        public Address $sender,
        public Address $recipient,
        array $parcels,
        public ?DateTimeImmutable $shippingDate = null,
        ?string $pickupPointId = null,
        public array $serviceCodes = [],
        public array $metadata = []
    ) {
        Assert::notEmpty($parcels, 'A rate request must contain at least one parcel.');
        Assert::listOf($parcels, Parcel::class, 'parcels');

        $this->parcels = array_values($parcels);
        $this->pickupPointId = Assert::trimToNull($pickupPointId);
    }
}
