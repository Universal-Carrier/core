<?php declare(strict_types=1);

namespace Tests\Conformance\Demo;

use PHPUnit\Framework\Attributes\CoversNothing;
use UniversalCarrier\Conformance\ShipmentCreatorConformanceTestCase;
use UniversalCarrier\Core\Contract\ShipmentCreatorInterface;
use UniversalCarrier\Core\Dto\ShipmentRequest;

/**
 * A standard contract, without insurance: the insurance test checks the refusal.
 */
#[CoversNothing]
class DemoShipmentCreatorConformanceTest extends ShipmentCreatorConformanceTestCase
{
    protected function createCarrier(): ShipmentCreatorInterface
    {
        return Fixtures::carrier();
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
