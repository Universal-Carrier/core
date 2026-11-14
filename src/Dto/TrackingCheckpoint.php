<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use DateTimeImmutable;
use DateTimeInterface;
use UniversalCarrier\Core\Enum\TrackingStatus;
use UniversalCarrier\Core\Support\Assert;

/**
 * Immutable Data Transfer Object describing one event in a parcel's journey.
 *
 * The status is normalised, for code to act on; the description is the carrier's own wording,
 * for people to read. Both are needed: with a handful of statuses, a timeline of statuses
 * alone would read "in transit" five times in a row.
 */
final readonly class TrackingCheckpoint
{

    public DateTimeImmutable $at;

    public ?string $description;

    public ?string $location;

    /**
     * @param TrackingStatus $status The normalised status this event puts the parcel in.
     * @param DateTimeInterface $at When the event happened, in the time zone it happened in.
     *   Implementations receiving a local time with no zone MUST attach the right one.
     * @param string|null $description The carrier's wording of the event, verbatim.
     * @param string|null $location Where the event happened, as the carrier describes it.
     */
    public function __construct(
        public TrackingStatus $status,
        DateTimeInterface $at,
        ?string $description = null,
        ?string $location = null
    ) {
        $this->at = DateTimeImmutable::createFromInterface($at);
        $this->description = Assert::trimToNull($description);
        $this->location = Assert::trimToNull($location);
    }
}
