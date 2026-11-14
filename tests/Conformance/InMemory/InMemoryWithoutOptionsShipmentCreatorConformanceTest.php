<?php declare(strict_types=1);

namespace Tests\Conformance\InMemory;

use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Fixture\InMemoryCarrier;
use UniversalCarrier\Conformance\ShipmentCreatorConformanceTestCase;
use UniversalCarrier\Core\Contract\ShipmentCreatorInterface;
use UniversalCarrier\Core\Dto\ShipmentRequest;

/**
 * No shipment option supported: the refusal branch of each option test runs.
 */
#[CoversNothing]
class InMemoryWithoutOptionsShipmentCreatorConformanceTest extends ShipmentCreatorConformanceTestCase
{
    protected function createCarrier(): ShipmentCreatorInterface
    {
        return new InMemoryCarrier([], Fixtures::pickupPoints());
    }

    protected function bookableRequest(): ShipmentRequest
    {
        return Fixtures::homeDelivery();
    }

    protected function customsRequest(): ShipmentRequest
    {
        return Fixtures::customsShipment();
    }
}
