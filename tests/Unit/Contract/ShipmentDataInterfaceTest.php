<?php declare(strict_types=1);

namespace Tests\Unit\Contract;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use UniversalCarrier\Core\Contract\ShipmentDataInterface;
use UniversalCarrier\Core\Dto\Address;
use UniversalCarrier\Core\Dto\Parcel;
use UniversalCarrier\Core\Dto\RateRequest;
use UniversalCarrier\Core\Dto\ShipmentRequest;

/**
 * The point of the interface is that a driver can write one mapping helper for both request
 * objects, so these tests exercise it through a function typed on the interface alone.
 */
#[CoversNothing]
class ShipmentDataInterfaceTest extends TestCase
{
    private static function address(string $countryCode = 'FR'): Address
    {
        return new Address('1 Main St', '75001', 'Paris', $countryCode, firstName: 'Jane', lastName: 'Doe');
    }

    /**
     * Stands in for the mapping helper a driver would share between getRates() and
     * createShipment().
     *
     * @return array<string, mixed>
     */
    private function buildPayload(ShipmentDataInterface $data): array
    {
        return [
            'from' => $data->sender->countryCode,
            'to' => $data->recipient->countryCode,
            'weight' => $data->getTotalWeightInKilogram(),
            'boxes' => $data->getParcelCount(),
            'multi' => $data->isMultiParcel(),
            'relay' => $data->isPickupPointDelivery(),
            'date' => $data->shippingDate?->format('Y-m-d'),
            'parcels' => count($data->parcels),
            'meta' => $data->metadata,
        ];
    }

    #[DataProvider('shipmentDataProvider')]
    public function testBothRequestObjectsAreUsableThroughTheInterface(ShipmentDataInterface $data): void
    {
        $this->assertSame([
            'from' => 'FR',
            'to' => 'DE',
            'weight' => 3.0,
            'boxes' => 2,
            'multi' => true,
            'relay' => true,
            'date' => '2026-09-07',
            'parcels' => 2,
            'meta' => ['k' => 'v'],
        ], $this->buildPayload($data));
    }

    /**
     * @return iterable<string, array{ShipmentDataInterface}>
     */
    public static function shipmentDataProvider(): iterable
    {
        $parcels = [new Parcel(1.0), new Parcel(2.0)];
        $date = new DateTimeImmutable('2026-09-07');

        yield 'rate request' => [new RateRequest(
            sender: self::address('FR'),
            recipient: self::address('DE'),
            parcels: $parcels,
            shippingDate: $date,
            pickupPointId: 'DE-999',
            metadata: ['k' => 'v'],
        )];

        yield 'shipment request' => [new ShipmentRequest(
            serviceCode: 'STD',
            sender: self::address('FR'),
            recipient: self::address('DE'),
            parcels: $parcels,
            orderReference: 'ORD-1',
            pickupPointId: 'DE-999',
            shippingDate: $date,
            metadata: ['k' => 'v'],
        )];
    }

    public function testARateRequestNowAnswersTheAccessorsItPreviouslyLacked(): void
    {
        $request = new RateRequest(self::address(), self::address(), [new Parcel(1.0)]);

        $this->assertFalse($request->isMultiParcel());
        $this->assertFalse($request->isPickupPointDelivery());
    }

    /**
     * The interface declares properties, not getters, so that a DTO never exposes the same
     * value twice. That is what the PHP 8.4 requirement buys, so it is worth pinning down.
     */
    public function testTheInterfaceDeclaresPropertiesRatherThanGetters(): void
    {
        $reflection = new ReflectionClass(ShipmentDataInterface::class);

        $this->assertSame(
            ['sender', 'recipient', 'parcels', 'shippingDate', 'pickupPointId', 'metadata'],
            array_map(static fn (ReflectionProperty $p): string => $p->getName(), $reflection->getProperties())
        );

        $this->assertSame(
            ['getTotalWeightInKilogram', 'getParcelCount', 'isMultiParcel', 'isPickupPointDelivery'],
            array_map(static fn (ReflectionMethod $m): string => $m->getName(), $reflection->getMethods()),
            'only derived accessors are methods; every stored value is a property'
        );
    }
}
