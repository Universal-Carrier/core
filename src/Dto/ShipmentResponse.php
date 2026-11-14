<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use UniversalCarrier\Core\Contract\CarrierInterface;
use UniversalCarrier\Core\Exception\InvalidArgumentException;
use UniversalCarrier\Core\Support\Assert;

/**
 * Immutable Data Transfer Object returned once a shipment has been booked.
 *
 * The response is parcel-oriented: one {@see ShipmentPackage} per parcel of the request, in
 * the same order, each with its own tracking number and label. Single-parcel shipments are
 * simply the case where that collection holds one entry, and {@see getFirstPackage()} keeps
 * them convenient to handle.
 */
final readonly class ShipmentResponse
{

    public string $shipmentId;

    /** @var ShipmentPackage[] */
    public array $packages;

    /**
     * @param string $shipmentId The carrier's identifier for the shipment as a whole. When a
     *   carrier has no such concept, implementations use the first tracking number.
     * @param ShipmentPackage[] $packages One entry per parcel, in the order of the request.
     * @param Price|null $cost The price actually charged for the shipment, when the carrier
     *   discloses it at booking time.
     * @param array<string, mixed> $metadata What the carrier answered that this specification
     *   does not model, chosen and named by the driver - a customs document number, an
     *   internal batch id - under keys namespaced with its {@see CarrierInterface::getCode()}.
     *   Values are `mixed`, so a consumer narrows before reading, and anything read here is
     *   carrier-specific by definition.
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $shipmentId,
        array $packages,
        public ?Price $cost = null,
        public array $metadata = []
    ) {
        $this->shipmentId = Assert::notBlank($shipmentId, 'shipmentId');
        Assert::notEmpty($packages, 'A shipment response must contain at least one package.');
        Assert::listOf($packages, ShipmentPackage::class, 'packages');

        $this->packages = array_values($packages);
    }

    /**
     * Builds a response for a single-parcel shipment.
     *
     * @param array<string, mixed> $metadata
     * @throws InvalidArgumentException
     */
    public static function forSingleParcel(
        string $trackingNumber,
        Label $label,
        ?string $trackingUrl = null,
        array $metadata = []
    ): self {
        return new self(
            shipmentId: $trackingNumber,
            packages: [new ShipmentPackage($trackingNumber, $label, $trackingUrl)],
            metadata: $metadata
        );
    }

    /**
     * The first booked package.
     *
     * Guaranteed to exist by the constructor invariants, which makes it safe to use directly
     * in the single-parcel case without a null check.
     */
    public function getFirstPackage(): ShipmentPackage
    {
        return $this->packages[0];
    }

    /**
     * Every tracking number of the shipment, in parcel order.
     *
     * @return string[]
     */
    public function getTrackingNumbers(): array
    {
        return array_map(
            static fn (ShipmentPackage $package): string => $package->trackingNumber,
            $this->packages
        );
    }

    /**
     * Every label of the shipment, one per parcel, in parcel order.
     *
     * @return Label[]
     */
    public function getLabels(): array
    {
        return array_map(
            static fn (ShipmentPackage $package): Label => $package->label,
            $this->packages
        );
    }

    public function getPackageCount(): int
    {
        return count($this->packages);
    }

    public function isMultiParcel(): bool
    {
        return count($this->packages) > 1;
    }
}
