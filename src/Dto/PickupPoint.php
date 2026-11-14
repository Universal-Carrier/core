<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use UniversalCarrier\Core\Enum\DayOfWeek;
use UniversalCarrier\Core\Enum\PickupPointType;
use UniversalCarrier\Core\Exception\InvalidArgumentException;
use UniversalCarrier\Core\Support\Assert;

/**
 * Immutable Data Transfer Object representing an out-of-home delivery location.
 *
 * Opening hours are the part carriers model most inconsistently - as strings, as nested maps,
 * as comma-separated ranges - so they are normalised here into at most one {@see BusinessDay}
 * per weekday, each holding sorted, non-overlapping {@see TimeSlot} instances.
 */
final readonly class PickupPoint
{

    public string $id;

    public string $name;

    public string $address1;

    public string $postalCode;

    public string $city;

    public string $countryCode;

    public ?string $state;

    public ?string $address2;

    public ?string $address3;

    public ?string $phone;

    public ?string $email;

    public ?string $timezone;

    /**
     * Indexed by the ISO-8601 day number, so a weekday can be looked up in constant time.
     *
     * @var array<int, BusinessDay>
     */
    private array $openingHoursByDay;

    /** @var BusinessDay[] */
    public array $openingHours;

    /** @var DateTimeImmutable[] */
    public array $holidays;

    /**
     * @param string $id The carrier-specific identifier, to be passed back verbatim as
     *   {@see ShipmentRequest::$pickupPointId} when booking a shipment to this location.
     * @param string $name The commercial or shop name (e.g. "Grocery Store Central").
     * @param PickupPointType $type The nature of the location.
     * @param string $address1 The street address.
     * @param string $postalCode The postal code.
     * @param string $city The city or locality name.
     * @param string $countryCode The 2-letter ISO 3166-1 alpha-2 country code.
     * @param string|null $state The state, province or region, when the country uses them.
     * @param string|null $address2 Second address line.
     * @param string|null $address3 Third address line.
     * @param string|null $phone The public phone number of the location.
     * @param string|null $email The public email address of the location.
     * @param float|null $latitude The WGS 84 latitude, between -90 and 90.
     * @param float|null $longitude The WGS 84 longitude, between -180 and 180.
     * @param float|null $distanceInKilometer The distance from the queried location, in kilometres.
     * @param BusinessDay[] $openingHours At most one entry per weekday. A weekday with no
     *   entry means the opening hours are unknown, not that the location is closed; a
     *   weekday present with no time slot means closed.
     * @param DateTimeInterface[] $holidays Specific dates the location is exceptionally closed.
     * @param string|null $timezone The IANA identifier of the location's own time zone (e.g.
     *   "Europe/Paris"). Opening hours are wall-clock times local to the location, so without
     *   it {@see isOpenAt()} cannot convert an arbitrary moment into that local time.
     * @param array<string, mixed> $metadata Carrier-specific attributes with no standard
     *   equivalent (wheelchair access, parking, maximum accepted parcel size, ...).
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $id,
        string $name,
        public PickupPointType $type,
        string $address1,
        string $postalCode,
        string $city,
        string $countryCode,
        ?string $state = null,
        ?string $address2 = null,
        ?string $address3 = null,
        ?string $phone = null,
        ?string $email = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?float $distanceInKilometer = null,
        array $openingHours = [],
        array $holidays = [],
        ?string $timezone = null,
        public array $metadata = []
    ) {
        $this->id = Assert::notBlank($id, 'id');
        $this->name = Assert::notBlank($name, 'name');
        $this->address1 = Assert::notBlank($address1, 'address1');
        $this->postalCode = Assert::notBlank($postalCode, 'postalCode');
        $this->city = Assert::notBlank($city, 'city');
        $this->countryCode = Assert::countryCode($countryCode);

        $this->state = Assert::trimToNull($state);
        $this->address2 = Assert::trimToNull($address2);
        $this->address3 = Assert::trimToNull($address3);
        $this->phone = Assert::trimToNull($phone);
        $this->email = Assert::trimToNull($email);

        $trimmedTimezone = Assert::trimToNull($timezone);
        $this->timezone = $trimmedTimezone === null ? null : Assert::timezone($trimmedTimezone);

        if ($this->latitude !== null && ($this->latitude < -90.0 || $this->latitude > 90.0)) {
            throw new InvalidArgumentException(sprintf('Latitude must be between -90 and 90, got %s.', $this->latitude));
        }

        if ($this->longitude !== null && ($this->longitude < -180.0 || $this->longitude > 180.0)) {
            throw new InvalidArgumentException(sprintf('Longitude must be between -180 and 180, got %s.', $this->longitude));
        }

        if ($this->distanceInKilometer !== null && $this->distanceInKilometer < 0.0) {
            throw new InvalidArgumentException(sprintf(
                'Distance cannot be negative, got %s km.',
                $this->distanceInKilometer
            ));
        }

        Assert::listOf($openingHours, BusinessDay::class, 'openingHours');
        Assert::listOf($holidays, DateTimeInterface::class, 'holidays');

        $indexed = [];

        foreach ($openingHours as $businessDay) {
            if (isset($indexed[$businessDay->day->value])) {
                throw new InvalidArgumentException(sprintf(
                    'Duplicate opening hours declared for %s.',
                    $businessDay->day->name
                ));
            }

            $indexed[$businessDay->day->value] = $businessDay;
        }

        ksort($indexed);

        $this->openingHoursByDay = $indexed;
        $this->openingHours = array_values($indexed);
        $this->holidays = array_values(array_map(
            static fn (DateTimeInterface $date): DateTimeImmutable => DateTimeImmutable::createFromInterface($date),
            $holidays
        ));
    }

    /**
     * The opening hours declared for a weekday, or null when the carrier did not declare any.
     */
    public function getBusinessDay(DayOfWeek $day): ?BusinessDay
    {
        return $this->openingHoursByDay[$day->value] ?? null;
    }

    /**
     * Whether the given moment falls on one of the declared holidays.
     *
     * The moment is first converted to the location's own time zone when one is known, then
     * only the calendar date is compared; the time of day is ignored.
     */
    public function isHoliday(DateTimeInterface $date): bool
    {
        $needle = $this->toLocalTime($date)->format('Y-m-d');

        foreach ($this->holidays as $holiday) {
            if ($holiday->format('Y-m-d') === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the location is open at the given date and time.
     *
     * Returns null - not false - when the answer is unknown because the carrier declared no
     * opening hours for that weekday. Callers MUST distinguish the two: showing "closed" for
     * a location whose hours are simply unpublished loses the customer a delivery option.
     *
     * Opening hours are wall-clock times local to the location. When {@see $timezone} is
     * known, the given moment is converted to it first, so any instant may be passed. When it
     * is not, the moment is used as it stands, and the CALLER is responsible for expressing it
     * in the location's local time - a subtle source of wrong answers for foreign locations.
     */
    public function isOpenAt(DateTimeInterface $moment): ?bool
    {
        $local = $this->toLocalTime($moment);

        if ($this->isHoliday($local)) {
            return false;
        }

        $businessDay = $this->getBusinessDay(DayOfWeek::fromDate($local));

        if ($businessDay === null) {
            return $this->openingHours === [] ? null : false;
        }

        return $businessDay->isOpenAt($local->format('H:i'));
    }

    /**
     * Converts a moment to the location's own time zone, or leaves it untouched when unknown.
     */
    private function toLocalTime(DateTimeInterface $moment): DateTimeImmutable
    {
        $immutable = DateTimeImmutable::createFromInterface($moment);

        return $this->timezone === null ? $immutable : $immutable->setTimezone(new DateTimeZone($this->timezone));
    }

    /**
     * Whether the carrier published any opening hours for this location.
     */
    public function hasOpeningHours(): bool
    {
        return $this->openingHours !== [];
    }

    /**
     * Whether GPS coordinates are available, as required to place the point on a map.
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Whether the location's own time zone is known, and therefore whether
     * {@see isOpenAt()} can be trusted with a moment expressed in any time zone.
     */
    public function hasTimezone(): bool
    {
        return $this->timezone !== null;
    }
}
