<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use DateTimeImmutable;
use UniversalCarrier\Core\Enum\PickupPointType;
use UniversalCarrier\Core\Exception\InvalidArgumentException;
use UniversalCarrier\Core\Support\Assert;

/**
 * Immutable Data Transfer Object describing a search for out-of-home delivery locations.
 *
 * A search is anchored either on a postal address or on GPS coordinates: a checkout form
 * gives the former, a "near me" button the latter, and carriers accept both. Either way it
 * names the country to search in, since most carriers search one country at a time and a
 * pair of coordinates does not say which. Every other criterion is a filter the
 * implementation applies if its carrier supports it, and silently ignores otherwise - with
 * the exception of the limit, which implementations MUST honour.
 */
final readonly class PickupPointQuery
{

    /**
     * The ISO 3166-1 alpha-2 code of the country to search in, uppercase. Always set: taken
     * from the address, or given alongside the coordinates.
     */
    public string $countryCode;

    /** @var PickupPointType[] */
    public array $types;

    public ?string $serviceCode;

    /**
     * @param Address|null $address The address to search around. Required unless coordinates
     *   are given.
     * @param float|null $latitude The WGS 84 latitude to search around.
     * @param float|null $longitude The WGS 84 longitude to search around.
     * @param string|null $countryCode The 2-letter ISO 3166-1 alpha-2 code of the country to
     *   search in, case insensitive. Required with coordinates; defaults to the country of the
     *   address otherwise, and must match it when both are given.
     * @param float|null $weightInKilogram The parcel weight, in kilograms. Lockers and small
     *   shops refuse heavy parcels, so passing it filters out unusable locations.
     * @param DateTimeImmutable|null $shippingDate The date the parcel enters the carrier
     *   network, that is the order date plus the preparation time. Locations closed for
     *   holidays around that date can then be excluded.
     * @param float|null $radiusInKilometer The maximum distance from the anchor point.
     * @param int|null $limit The maximum number of results to return, at least 1.
     * @param PickupPointType[] $types Restrict results to these kinds of location. An empty
     *   list accepts every kind.
     * @param string|null $serviceCode Restrict results to locations served by this carrier service.
     * @param array<string, mixed> $metadata Carrier-specific search options.
     * @throws InvalidArgumentException
     */
    public function __construct(
        public ?Address $address = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        ?string $countryCode = null,
        public ?float $weightInKilogram = null,
        public ?DateTimeImmutable $shippingDate = null,
        public ?float $radiusInKilometer = null,
        public ?int $limit = null,
        array $types = [],
        ?string $serviceCode = null,
        public array $metadata = []
    ) {
        $this->serviceCode = Assert::trimToNull($serviceCode);

        $hasCoordinates = $this->latitude !== null && $this->longitude !== null;

        if ($this->address === null && !$hasCoordinates) {
            throw new InvalidArgumentException(
                'A pickup point query must be anchored on an address or on a latitude/longitude pair.'
            );
        }

        $this->countryCode = $this->resolveCountryCode($countryCode);

        if ($this->latitude !== null && ($this->latitude < -90.0 || $this->latitude > 90.0)) {
            throw new InvalidArgumentException(sprintf('Latitude must be between -90 and 90, got %s.', $this->latitude));
        }

        if ($this->longitude !== null && ($this->longitude < -180.0 || $this->longitude > 180.0)) {
            throw new InvalidArgumentException(sprintf('Longitude must be between -180 and 180, got %s.', $this->longitude));
        }

        if ($this->weightInKilogram !== null && $this->weightInKilogram < 0.0) {
            throw new InvalidArgumentException(sprintf(
                'Weight cannot be negative, got %s kg.',
                $this->weightInKilogram
            ));
        }

        if ($this->radiusInKilometer !== null && $this->radiusInKilometer <= 0.0) {
            throw new InvalidArgumentException(sprintf(
                'Search radius must be strictly positive, got %s km.',
                $this->radiusInKilometer
            ));
        }

        if ($this->limit !== null && $this->limit < 1) {
            throw new InvalidArgumentException(sprintf('Result limit must be at least 1, got %d.', $this->limit));
        }

        Assert::listOf($types, PickupPointType::class, 'types');

        $this->types = array_values($types);
    }

    /**
     * Builds a query anchored on a postal address.
     *
     * @throws InvalidArgumentException
     */
    public static function forAddress(Address $address, ?float $weightInKilogram = null): self
    {
        return new self(address: $address, weightInKilogram: $weightInKilogram);
    }

    /**
     * Builds a query anchored on GPS coordinates.
     *
     * @throws InvalidArgumentException
     */
    public static function forCoordinates(
        float $latitude,
        float $longitude,
        string $countryCode,
        ?float $weightInKilogram = null
    ): self {
        return new self(
            latitude: $latitude,
            longitude: $longitude,
            countryCode: $countryCode,
            weightInKilogram: $weightInKilogram
        );
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function resolveCountryCode(?string $countryCode): string
    {
        if ($countryCode === null) {
            return $this->address->countryCode ?? throw new InvalidArgumentException(
                'A pickup point query anchored on coordinates must give the country to search in.'
            );
        }

        $normalized = Assert::countryCode($countryCode);

        if ($this->address !== null && $this->address->countryCode !== $normalized) {
            throw new InvalidArgumentException(sprintf(
                'The country to search in (%s) must match the country of the address (%s).',
                $normalized,
                $this->address->countryCode
            ));
        }

        return $normalized;
    }
}
