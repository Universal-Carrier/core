<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Enum;

use DateTimeInterface;

/**
 * Days of the week, using the ISO-8601 numbering (1 for Monday, 7 for Sunday).
 *
 * The ISO numbering is chosen so that the case value matches PHP's own `N` date format,
 * which makes the mapping from a date to a business day unambiguous.
 */
enum DayOfWeek: int
{

    case MONDAY = 1;
    case TUESDAY = 2;
    case WEDNESDAY = 3;
    case THURSDAY = 4;
    case FRIDAY = 5;
    case SATURDAY = 6;
    case SUNDAY = 7;

    /**
     * The day of the week the given date falls on.
     */
    public static function fromDate(DateTimeInterface $date): self
    {
        return self::from((int)$date->format('N'));
    }

    /**
     * Whether the day is part of the Monday to Friday working week.
     *
     * This is a calendar convention, not a carrier one: carriers routinely operate on
     * Saturdays, and public holidays are not weekends.
     */
    public function isWeekday(): bool
    {
        return $this->value <= self::FRIDAY->value;
    }
}
