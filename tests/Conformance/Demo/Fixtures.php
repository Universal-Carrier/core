<?php declare(strict_types=1);

namespace Tests\Conformance\Demo;

use Demo\Api\DemoApiClient;
use Demo\DemoCarrier;
use UniversalCarrier\Core\Dto\{Address, Parcel, ParcelItem, Price, ShipmentRequest};

/**
 * Shared data for running the conformance suite against the demo driver - the way a real
 * driver would run it against its carrier's sandbox.
 */
final class Fixtures
{

    public static function carrier(bool $insuranceContract = false): DemoCarrier
    {
        return new DemoCarrier(new DemoApiClient(), '800123', 'demo', $insuranceContract);
    }

    public static function shop(): Address
    {
        return new Address('51 avenue des Champs-Élysées', '75008', 'Paris', 'FR', company: 'Acme SAS');
    }

    public static function customer(): Address
    {
        return new Address('12 rue de Rivoli', '75004', 'Paris', 'FR', firstName: 'Jeanne', lastName: 'Martin');
    }

    public static function homeDelivery(): ShipmentRequest
    {
        return new ShipmentRequest('HOME', self::shop(), self::customer(), [new Parcel(1.45)], 'ORD-1');
    }

    public static function pickupPointDelivery(): ShipmentRequest
    {
        return new ShipmentRequest('RELAY', self::shop(), self::customer(), [new Parcel(1.45)], 'ORD-1', pickupPointId: 'FR-004512');
    }

    public static function customsShipment(): ShipmentRequest
    {
        return new ShipmentRequest(
            'HOME',
            self::shop(),
            new Address('350 5th Avenue', '10118', 'New York', 'US', firstName: 'John', lastName: 'Smith', state: 'NY'),
            [new Parcel(0.8, items: [new ParcelItem('Cotton t-shirt', 3, 0.2, new Price(1990), '610910', 'PT')])],
            'ORD-1'
        );
    }
}
