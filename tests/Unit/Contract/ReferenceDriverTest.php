<?php declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Conformance\Demo\Fixtures as DemoFixtures;
use Tests\Fixture\InMemoryCarrier;
use Tests\Fixture\RateOnlyCarrier;
use UniversalCarrier\Core\Contract\CarrierInterface;
use UniversalCarrier\Core\Contract\PickupPointProviderInterface;
use UniversalCarrier\Core\Contract\RateProviderInterface;
use UniversalCarrier\Core\Contract\ShipmentCreatorInterface;
use UniversalCarrier\Core\Contract\TrackingProviderInterface;
use UniversalCarrier\Core\Dto\Address;
use UniversalCarrier\Core\Dto\Parcel;
use UniversalCarrier\Core\Dto\ParcelItem;
use UniversalCarrier\Core\Dto\PickupPoint;
use UniversalCarrier\Core\Dto\PickupPointQuery;
use UniversalCarrier\Core\Dto\Price;
use UniversalCarrier\Core\Dto\ShipmentRequest;
use UniversalCarrier\Core\Dto\TrackingCheckpoint;
use UniversalCarrier\Core\Enum\PickupPointType;
use UniversalCarrier\Core\Enum\TrackingStatus;

/**
 * What the conformance kit cannot check generically, exercised on the reference drivers.
 *
 * The rules every driver owes are checked by the kit in conformance/, run against these same
 * drivers from tests/Conformance. What remains here depends on knowing the driver's data - a
 * filter only means something when you know which points should survive it - or spans several
 * calls on one instance.
 */
#[CoversNothing]
class ReferenceDriverTest extends TestCase
{
    private function address(string $countryCode = 'FR'): Address
    {
        return new Address('1 Main St', '75001', 'Paris', $countryCode, firstName: 'Jane', lastName: 'Doe');
    }

    private function pickupPoint(string $id, PickupPointType $type, float $distance): PickupPoint
    {
        return new PickupPoint(
            id: $id,
            name: 'Point ' . $id,
            type: $type,
            address1: '12 rue de Rivoli',
            postalCode: '75004',
            city: 'Paris',
            countryCode: 'FR',
            distanceInKilometer: $distance
        );
    }

    /**
     * @return list<CarrierInterface>
     */
    private function registry(): array
    {
        return [new InMemoryCarrier(), new RateOnlyCarrier()];
    }

    /**
     * @param class-string<CarrierInterface> $capability
     * @return list<string>
     */
    private function codesOf(string $capability): array
    {
        return array_values(array_map(
            static fn (CarrierInterface $carrier): string => $carrier->getCode(),
            array_filter($this->registry(), static fn (CarrierInterface $carrier): bool => $carrier instanceof $capability)
        ));
    }

    public function testCapabilitiesAreDiscoveredThroughTheTypeSystem(): void
    {
        $this->assertSame(['in_memory', 'rate_only'], $this->codesOf(RateProviderInterface::class));
        $this->assertSame(['in_memory'], $this->codesOf(PickupPointProviderInterface::class));
        $this->assertSame(['in_memory'], $this->codesOf(ShipmentCreatorInterface::class));
        $this->assertSame(['in_memory'], $this->codesOf(TrackingProviderInterface::class));
    }

    public function testPickupPointFiltersAreApplied(): void
    {
        $carrier = new InMemoryCarrier(pickupPoints: [
            $this->pickupPoint('A', PickupPointType::LOCKER, 0.3),
            $this->pickupPoint('B', PickupPointType::SHOP, 1.1),
        ]);

        $points = $carrier->getPickupPoints(
            new PickupPointQuery(address: $this->address(), types: [PickupPointType::LOCKER])
        );

        $this->assertCount(1, $points);
        $this->assertSame('A', $points[0]->id);
    }

    public function testItemsOnAShipmentRequiringNoDeclarationDoNotRequireCustomsSupport(): void
    {
        $carrier = new InMemoryCarrier([]);

        $response = $carrier->createShipment(new ShipmentRequest(
            'STD',
            $this->address('FR'),
            $this->address('FR'),
            [new Parcel(1.0, items: [new ParcelItem('Cotton t-shirt', 1, 0.2, new Price(1990))])],
            'ORD-1'
        ));

        $this->assertSame(1, $response->getPackageCount());
    }

    public function testTrackingABookedParcelAnswersForThatNumber(): void
    {
        $carrier = new InMemoryCarrier();
        $trackingNumber = $carrier->createShipment(
            new ShipmentRequest('STD', $this->address(), $this->address(), [new Parcel(1.0)], 'ORD-1')
        )->getFirstPackage()->trackingNumber;

        $tracking = $carrier->track($trackingNumber);

        $this->assertSame($trackingNumber, $tracking->trackingNumber);
        $this->assertSame(TrackingStatus::PENDING, $tracking->status);
        $this->assertTrue($tracking->hasCheckpoints());
    }

    /**
     * The demo carrier sends a code its driver's table does not know - "MEL", an email sent to
     * the recipient - the way a real carrier adds codes after a driver is written. It must
     * read as the parcel standing still, not as an incident (specification, section 5.6).
     */
    public function testAnEventTheDriverCannotReadKeepsThePreviousStatus(): void
    {
        $tracking = DemoFixtures::carrier()->track('6D0000000001');

        $unknown = $tracking->checkpoints[2];

        $this->assertSame('Un email vient de vous être envoyé', $unknown->description);
        $this->assertSame(TrackingStatus::PICKED_UP, $unknown->status);
        $this->assertSame(TrackingStatus::PICKED_UP, $tracking->checkpoints[1]->status, 'The event it inherits from.');
        $this->assertNotContains(
            TrackingStatus::EXCEPTION,
            array_map(static fn (TrackingCheckpoint $c): TrackingStatus => $c->status, $tracking->checkpoints),
            'A delivery that went well reports no incident.'
        );
        $this->assertSame(TrackingStatus::DELIVERED, $tracking->status);
    }
}
