<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Contract;

use DateTimeImmutable;
use UniversalCarrier\Core\Dto\Address;
use UniversalCarrier\Core\Dto\Parcel;
use UniversalCarrier\Core\Dto\RateRequest;
use UniversalCarrier\Core\Dto\ShipmentRequest;

/**
 * The description of goods moving from one address to another.
 *
 * {@see RateRequest} and {@see ShipmentRequest} describe the same physical movement at two
 * different moments: before a service has been chosen, and after. Everything that describes
 * the movement itself - who sends, who receives, what boxes, when - is identical between the
 * two, and lives here; everything that belongs to one moment only stays on the concrete class.
 *
 * A driver builds the same carrier payload fragment from either, so it can type its mapping
 * helpers on this interface and write them once:
 *
 * ```php
 * private function buildAddressBlock(ShipmentDataInterface $data): array { ... }
 * ```
 *
 * This interface exists to factor that out, not to open an extension point: the specification
 * forbids replacing the Data Transfer Objects of this package, so its only implementations are
 * and will remain the two named above.
 */
interface ShipmentDataInterface
{

    /**
     * The origin address.
     */
    public Address $sender { get; }

    /**
     * The destination address.
     */
    public Address $recipient { get; }

    /**
     * The parcels making up the movement, at least one, indexed from zero.
     *
     * @var Parcel[]
     */
    public array $parcels { get; }

    /**
     * The date the parcels are handed to the carrier, or null to let the implementation assume
     * the next possible collection.
     */
    public ?DateTimeImmutable $shippingDate { get; }

    /**
     * The pickup point to deliver to, or null for a home delivery.
     */
    public ?string $pickupPointId { get; }

    /**
     * Carrier-specific options with no standard equivalent.
     *
     * @var array<string, mixed>
     */
    public array $metadata { get; }

    /**
     * The combined weight of every parcel, in kilograms.
     */
    public function getTotalWeightInKilogram(): float;

    /**
     * The number of parcels making up the movement.
     */
    public function getParcelCount(): int;

    /**
     * Whether the movement covers more than one parcel.
     */
    public function isMultiParcel(): bool;

    /**
     * Whether the movement is bound for a pickup point rather than a home address.
     */
    public function isPickupPointDelivery(): bool;
}
