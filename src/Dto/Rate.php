<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use DateTimeImmutable;
use UniversalCarrier\Core\Exception\InvalidArgumentException;
use UniversalCarrier\Core\Support\Assert;

/**
 * Immutable Data Transfer Object representing one shipping option returned by a carrier.
 *
 * The `serviceCode` of a rate is guaranteed to be accepted as
 * {@see ShipmentRequest::$serviceCode} by the same carrier implementation. That round trip is
 * the whole point of rating: quote, let the customer choose, book what was chosen.
 */
final readonly class Rate
{

    public string $serviceCode;

    public string $serviceName;

    public ?string $description;

    /**
     * @param string $serviceCode The carrier-specific service identifier, opaque to consumers.
     * @param string $serviceName A human-readable service name fit to display at checkout.
     * @param Price $price The total price of the option for the quoted shipment.
     * @param bool $requiresPickupPoint Whether booking this service requires a
     *   {@see ShipmentRequest::$pickupPointId}. Out-of-home services are unbookable without it.
     * @param int|null $transitTimeInDays The number of business days in transit, when known.
     * @param DateTimeImmutable|null $earliestDeliveryDate The optimistic end of the delivery
     *   estimate. When the carrier gives a single date, both bounds hold that date.
     * @param DateTimeImmutable|null $latestDeliveryDate The pessimistic end of the estimate.
     * @param string|null $description A longer commercial description of the service.
     * @param array<string, mixed> $metadata Carrier-specific attributes with no standard
     *   equivalent (guarantee, carbon offset, signature requirement, ...).
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $serviceCode,
        string $serviceName,
        public Price $price,
        public bool $requiresPickupPoint = false,
        public ?int $transitTimeInDays = null,
        public ?DateTimeImmutable $earliestDeliveryDate = null,
        public ?DateTimeImmutable $latestDeliveryDate = null,
        ?string $description = null,
        public array $metadata = []
    ) {
        $this->serviceCode = Assert::notBlank($serviceCode, 'serviceCode');
        $this->serviceName = Assert::notBlank($serviceName, 'serviceName');
        $this->description = Assert::trimToNull($description);

        if ($this->transitTimeInDays !== null && $this->transitTimeInDays < 0) {
            throw new InvalidArgumentException(sprintf(
                'Transit time cannot be negative, got %d days.',
                $this->transitTimeInDays
            ));
        }

        if (
            $this->earliestDeliveryDate !== null
            && $this->latestDeliveryDate !== null
            && $this->latestDeliveryDate < $this->earliestDeliveryDate
        ) {
            throw new InvalidArgumentException(sprintf(
                'The latest delivery date (%s) cannot precede the earliest one (%s).',
                $this->latestDeliveryDate->format('Y-m-d'),
                $this->earliestDeliveryDate->format('Y-m-d')
            ));
        }
    }

    /**
     * Whether the carrier gave any delivery date estimate.
     */
    public function hasDeliveryEstimate(): bool
    {
        return $this->earliestDeliveryDate !== null || $this->latestDeliveryDate !== null;
    }

    /**
     * The single date to show when the estimate has to be reduced to one value.
     *
     * The latest bound is used on purpose: under-promising a delivery date is the safer error
     * to make in a checkout funnel.
     */
    public function getDeliveryDate(): ?DateTimeImmutable
    {
        return $this->latestDeliveryDate ?? $this->earliestDeliveryDate;
    }
}
