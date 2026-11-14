<?php declare(strict_types=1);

namespace Tests\Conformance\InMemory;

use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Fixture\InMemoryCarrier;
use UniversalCarrier\Conformance\ShipmentCreatorConformanceTestCase;
use UniversalCarrier\Core\Contract\ShipmentCreatorInterface;
use UniversalCarrier\Core\Dto\ShipmentRequest;

/**
 * Every shipment option supported: the booking branch of each option test runs.
 */
#[CoversNothing]
class InMemoryShipmentCreatorConformanceTest extends ShipmentCreatorConformanceTestCase
{
    protected function createCarrier(): ShipmentCreatorInterface
    {
        return new InMemoryCarrier(pickupPoints: Fixtures::pickupPoints());
    }

    protected function bookableRequest(): ShipmentRequest
    {
        return Fixtures::homeDelivery();
    }

    protected function pickupPointRequest(): ShipmentRequest
    {
        return Fixtures::pickupPointDelivery();
    }

    protected function customsRequest(): ShipmentRequest
    {
        return Fixtures::customsShipment();
    }
}
