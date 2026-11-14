<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use UniversalCarrier\Core\Enum\DayOfWeek;
use UniversalCarrier\Core\Exception\InvalidArgumentException;
use UniversalCarrier\Core\Support\Assert;

/**
 * Immutable Data Transfer Object describing when a location is open on a given weekday.
 *
 * Slots are normalised on construction: they are sorted by opening time and must not overlap.
 * Carriers commonly return them out of order, or return a single "08:00-19:00" slot where a
 * competitor returns two around a lunch break; normalising here is what makes the two
 * comparable downstream.
 */
final readonly class BusinessDay
{

    /** @var TimeSlot[] */
    public array $timeSlots;

    /**
     * @param DayOfWeek $day The day of the week these slots apply to.
     * @param TimeSlot[] $timeSlots The opening periods. An empty list means closed all day.
     * @throws InvalidArgumentException
     */
    public function __construct(
        public DayOfWeek $day,
        array $timeSlots = []
    ) {
        Assert::listOf($timeSlots, TimeSlot::class, 'timeSlots');

        $sorted = array_values($timeSlots);
        usort($sorted, static fn (TimeSlot $a, TimeSlot $b): int => strcmp($a->open, $b->open));

        foreach ($sorted as $index => $slot) {
            $next = $sorted[$index + 1] ?? null;

            if ($next !== null && $slot->overlaps($next)) {
                throw new InvalidArgumentException(sprintf(
                    'Overlapping time slots for %s: "%s-%s" and "%s-%s".',
                    $day->name,
                    $slot->open,
                    $slot->close,
                    $next->open,
                    $next->close
                ));
            }
        }

        $this->timeSlots = $sorted;
    }

    /**
     * Convenience constructor for a day with a single continuous opening period.
     *
     * @throws InvalidArgumentException
     */
    public static function open(DayOfWeek $day, string $open, string $close): self
    {
        return new self($day, [new TimeSlot($open, $close)]);
    }

    /**
     * Convenience constructor for a day the location is closed.
     */
    public static function closed(DayOfWeek $day): self
    {
        return new self($day);
    }

    public function isClosed(): bool
    {
        return $this->timeSlots === [];
    }

    /**
     * Whether the location is open at the given "HH:MM" wall-clock time.
     *
     * @throws InvalidArgumentException If $time is not a valid "HH:MM" string.
     */
    public function isOpenAt(string $time): bool
    {
        foreach ($this->timeSlots as $slot) {
            if ($slot->contains($time)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Total number of minutes the location is open on this day.
     */
    public function getTotalOpenMinutes(): int
    {
        return array_sum(array_map(
            static fn (TimeSlot $slot): int => $slot->getDurationInMinutes(),
            $this->timeSlots
        ));
    }
}
