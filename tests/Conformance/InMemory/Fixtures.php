<?php declare(strict_types=1);

namespace Tests\Conformance\InMemory;

use UniversalCarrier\Core\Dto\{Address, Parcel, ParcelItem, PickupPoint, Price, ShipmentRequest};
use UniversalCarrier\Core\Enum\PickupPointType;

/**
 * Shared data for running the conformance suite against the in-memory reference driver.
 */
final class Fixtures
{

    public static function paris(): Address
    {
        return new Address('1 rue de Rivoli', '75001', 'Paris', 'FR', firstName: 'Jeanne', lastName: 'Martin');
    }

    public static function newYork(): Address
    {
        return new Address('350 5th Avenue', '10118', 'New York', 'US', firstName: 'John', lastName: 'Smith', state: 'NY');
    }

    /**
     * @return list<PickupPoint>
     */
    public static function pickupPoints(): array
    {
        return [
            new PickupPoint('FR-002', 'Tabac du Centre', PickupPointType::SHOP, '3 rue du Louvre', '75001', 'Paris', 'FR', distanceInKilometer: 1.2),
            new PickupPoint('FR-001', 'Consigne Rivoli', PickupPointType::LOCKER, '8 rue de Rivoli', '75001', 'Paris', 'FR', distanceInKilometer: 0.4),
            new PickupPoint('FR-003', 'Librairie', PickupPointType::SHOP, '9 rue de Seine', '75006', 'Paris', 'FR'),
        ];
    }

    public static function homeDelivery(): ShipmentRequest
    {
        return new ShipmentRequest('STD', self::paris(), self::paris(), [new Parcel(1.2)], 'ORD-1');
    }

    public static function pickupPointDelivery(): ShipmentRequest
    {
        return new ShipmentRequest('RELAY', self::paris(), self::paris(), [new Parcel(1.2)], 'ORD-1', pickupPointId: 'FR-001');
    }

    public static function customsShipment(): ShipmentRequest
    {
        return new ShipmentRequest(
            'STD',
            self::paris(),
            self::newYork(),
            [new Parcel(1.2, items: [new ParcelItem('Cotton t-shirt', 2, 0.3, new Price(1990))])],
            'ORD-1'
        );
    }
}
