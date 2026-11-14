<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use DateTimeImmutable;
use UniversalCarrier\Core\Contract\CarrierInterface;
use UniversalCarrier\Core\Enum\TrackingStatus;
use UniversalCarrier\Core\Exception\InvalidArgumentException;
use UniversalCarrier\Core\Support\Assert;

/**
 * Immutable Data Transfer Object describing where a parcel stands.
 *
 * Only the tracking number and the current status are guaranteed: every carrier can give
 * those, not every carrier gives a history or a delivery estimate. When it does, the history
 * is exposed oldest first, whatever order the carrier sent it in.
 */
final readonly class Tracking
{

    public string $trackingNumber;

    public ?string $description;

    public ?string $location;

    /** @var TrackingCheckpoint[] */
    public array $checkpoints;

    /**
     * @param string $trackingNumber The tracking number this information is about.
     * @param TrackingStatus $status The parcel's current normalised status.
     * @param string|null $description The carrier's wording of the current status, verbatim.
     *   Useful above all when the carrier gives no history.
     * @param string|null $location Where the parcel currently is, as the carrier describes it.
     * @param DateTimeImmutable|null $estimatedDelivery The expected delivery date, when the
     *   carrier gives one. Only its date part is meaningful.
     * @param TrackingCheckpoint[] $checkpoints The parcel's history, in any order: it is
     *   sorted oldest first on construction.
     * @param array<string, mixed> $metadata What the carrier reported that this specification
     *   does not model, chosen and named by the driver - the destination pickup point, a
     *   reason code - under keys namespaced with its {@see CarrierInterface::getCode()}.
     *   Values are `mixed`, so a consumer narrows before reading.
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $trackingNumber,
        public TrackingStatus $status,
        ?string $description = null,
        ?string $location = null,
        public ?DateTimeImmutable $estimatedDelivery = null,
        array $checkpoints = [],
        public array $metadata = []
    ) {
        Assert::listOf($checkpoints, TrackingCheckpoint::class, 'checkpoints');

        $this->trackingNumber = Assert::notBlank($trackingNumber, 'trackingNumber');
        $this->description = Assert::trimToNull($description);
        $this->location = Assert::trimToNull($location);

        $sorted = array_values($checkpoints);
        usort($sorted, static fn (TrackingCheckpoint $a, TrackingCheckpoint $b): int => $a->at <=> $b->at);

        $this->checkpoints = $sorted;
    }

    /**
     * Whether no further status is expected for this parcel.
     */
    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    public function hasCheckpoints(): bool
    {
        return $this->checkpoints !== [];
    }

    /**
     * The most recent event, or null when the carrier gave no history.
     */
    public function getLatestCheckpoint(): ?TrackingCheckpoint
    {
        return $this->checkpoints === [] ? null : $this->checkpoints[array_key_last($this->checkpoints)];
    }
}
