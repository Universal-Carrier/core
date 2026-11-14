<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Dto\Address;
use UniversalCarrier\Core\Dto\PickupPointQuery;
use UniversalCarrier\Core\Enum\PickupPointType;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

#[CoversClass(PickupPointQuery::class)]
class PickupPointQueryTest extends TestCase
{
    private function address(): Address
    {
        return new Address('1 Main St', '75001', 'Paris', 'FR', firstName: 'Jane');
    }

    public function testItBuildsAQueryAnchoredOnAnAddress(): void
    {
        $query = PickupPointQuery::forAddress($this->address(), 1.45);

        $this->assertNotNull($query->address);
        $this->assertSame(1.45, $query->weightInKilogram);
        $this->assertFalse($query->hasCoordinates());
    }

    public function testItTakesTheCountryFromTheAddress(): void
    {
        $this->assertSame('FR', PickupPointQuery::forAddress($this->address())->countryCode);
    }

    public function testItAcceptsTheCountryOfTheAddressWhenGivenExplicitly(): void
    {
        $this->assertSame('FR', (new PickupPointQuery(address: $this->address(), countryCode: 'fr'))->countryCode);
    }

    public function testItRejectsACountryThatContradictsTheAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The country to search in (BE) must match the country of the address (FR).');

        new PickupPointQuery(address: $this->address(), countryCode: 'BE');
    }

    public function testItBuildsAQueryAnchoredOnCoordinates(): void
    {
        $query = PickupPointQuery::forCoordinates(48.8566, 2.3522, ' fr ');

        $this->assertTrue($query->hasCoordinates());
        $this->assertNull($query->address);
        $this->assertSame('FR', $query->countryCode);
    }

    public function testItRequiresACountryWithCoordinates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('anchored on coordinates must give the country to search in');

        new PickupPointQuery(latitude: 48.8566, longitude: 2.3522);
    }

    public function testItRejectsAMalformedCountry(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PickupPointQuery(latitude: 48.8566, longitude: 2.3522, countryCode: 'FRA');
    }

    public function testItAcceptsEveryFilter(): void
    {
        $query = new PickupPointQuery(
            address: $this->address(),
            weightInKilogram: 2.0,
            radiusInKilometer: 5.0,
            limit: 10,
            types: [PickupPointType::LOCKER, PickupPointType::SHOP],
            serviceCode: 'RELAY'
        );

        $this->assertSame(10, $query->limit);
        $this->assertCount(2, $query->types);
        $this->assertSame('RELAY', $query->serviceCode);
    }

    public function testItRequiresAnAnchor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be anchored on an address or on a latitude/longitude pair');

        new PickupPointQuery();
    }

    public function testItRejectsAHalfCoordinatePairAsAnAnchor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PickupPointQuery(latitude: 48.8566);
    }

    public function testItRejectsOutOfRangeCoordinates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Longitude must be between -180 and 180');

        new PickupPointQuery(latitude: 48.0, longitude: 181.0, countryCode: 'FR');
    }

    public function testItRejectsANegativeWeight(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Weight cannot be negative');

        new PickupPointQuery(address: $this->address(), weightInKilogram: -1.0);
    }

    public function testItRejectsANonPositiveRadius(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Search radius must be strictly positive');

        new PickupPointQuery(address: $this->address(), radiusInKilometer: 0.0);
    }

    public function testItRejectsANonPositiveLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Result limit must be at least 1, got 0.');

        new PickupPointQuery(address: $this->address(), limit: 0);
    }

    public function testItValidatesTypesAtRuntime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All items in types must be instances of');

        /** @phpstan-ignore argument.type */
        new PickupPointQuery(address: $this->address(), types: ['locker']);
    }
}
