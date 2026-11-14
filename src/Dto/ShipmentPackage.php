<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use UniversalCarrier\Core\Exception\InvalidArgumentException;
use UniversalCarrier\Core\Support\Assert;

/**
 * Immutable Data Transfer Object binding one booked parcel to its tracking number and label.
 *
 * A shipment of three boxes produces three tracking numbers and three labels at every carrier
 * that supports multi-parcel booking. Modelling a shipment as a flat pair of
 * (tracking number, label) makes those carriers unrepresentable, so the pair lives here, once
 * per parcel, and {@see ShipmentResponse} holds the collection.
 */
final readonly class ShipmentPackage
{

    public string $trackingNumber;

    public ?string $trackingUrl;

    public ?string $parcelReference;

    /**
     * @param string $trackingNumber The tracking number assigned to this parcel.
     * @param Label $label The label to print and stick on this parcel. Always present: a
     *   booking that cannot produce its label throws instead of returning a package without
     *   one (specification, section 5.5).
     * @param string|null $trackingUrl A public URL where the recipient can follow this parcel.
     * @param string|null $parcelReference The {@see Parcel::$reference} of the parcel this
     *   package corresponds to, when the request supplied one.
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $trackingNumber,
        public Label $label,
        ?string $trackingUrl = null,
        ?string $parcelReference = null
    ) {
        $this->trackingNumber = Assert::notBlank($trackingNumber, 'trackingNumber');
        $this->trackingUrl = Assert::trimToNull($trackingUrl);
        $this->parcelReference = Assert::trimToNull($parcelReference);
    }
}
