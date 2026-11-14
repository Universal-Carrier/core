<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use UniversalCarrier\Core\Exception\InvalidArgumentException;

/**
 * Immutable Value Object representing a continuous opening period within a single day.
 *
 * Times are wall-clock times local to the pickup point, in 24-hour "HH:MM" format. They are
 * deliberately not `DateTimeImmutable` instances: a slot is a recurring daily rule, not a
 * point in time, and attaching a date or a timezone to it would be misleading.
 */
final readonly class TimeSlot
{

    private const string TIME_PATTERN = '/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/';

    /**
     * @param string $open The opening time in 24-hour format (e.g. "08:30").
     * @param string $close The closing time in 24-hour format (e.g. "18:00"), strictly after $open.
     * @throws InvalidArgumentException
     */
    public function __construct(
        public string $open,
        public string $close
    ) {
        if (preg_match(self::TIME_PATTERN, $this->open) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid open time format "%s". Expected "HH:MM".', $this->open));
        }

        if (preg_match(self::TIME_PATTERN, $this->close) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid close time format "%s". Expected "HH:MM".', $this->close));
        }

        if ($this->close <= $this->open) {
            throw new InvalidArgumentException(sprintf(
                'A time slot must close after it opens, got "%s" to "%s".',
                $this->open,
                $this->close
            ));
        }
    }

    /**
     * Whether the given "HH:MM" wall-clock time falls within this slot.
     *
     * The opening time is inclusive and the closing time exclusive, so that back-to-back
     * slots such as 08:00-12:00 and 12:00-18:00 never both match.
     *
     * @throws InvalidArgumentException If $time is not a valid "HH:MM" string.
     */
    public function contains(string $time): bool
    {
        if (preg_match(self::TIME_PATTERN, $time) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid time format "%s". Expected "HH:MM".', $time));
        }

        return $time >= $this->open && $time < $this->close;
    }

    /**
     * Whether this slot shares at least one minute with the given one.
     */
    public function overlaps(self $other): bool
    {
        return $this->open < $other->close && $other->open < $this->close;
    }

    public function getDurationInMinutes(): int
    {
        return $this->toMinutes($this->close) - $this->toMinutes($this->open);
    }

    private function toMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);

        return (int)$hours * 60 + (int)$minutes;
    }
}
