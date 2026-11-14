<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Support;

use UniversalCarrier\Core\Contract\ShipmentDataInterface;
use UniversalCarrier\Core\Dto\Parcel;

/**
 * The single implementation of the derived accessors of {@see ShipmentDataInterface}.
 *
 * An interface declares what a type offers, never how; without this trait the same five
 * one-line bodies would exist twice, and would drift the day one of them gains a subtlety.
 *
 * @internal This trait is an implementation detail and is not part of the specification.
 *
 * @phpstan-require-implements ShipmentDataInterface
 */
trait ShipmentDataTrait
{

    public function getTotalWeightInKilogram(): float
    {
        return array_reduce(
            $this->parcels,
            static fn (float $total, Parcel $parcel): float => $total + $parcel->weightInKilogram,
            0.0
        );
    }

    public function getParcelCount(): int
    {
        return count($this->parcels);
    }

    public function isMultiParcel(): bool
    {
        return count($this->parcels) > 1;
    }

    public function isPickupPointDelivery(): bool
    {
        return $this->pickupPointId !== null;
    }
}
