<?php declare(strict_types=1);

namespace Tests\Conformance\Demo;

use PHPUnit\Framework\Attributes\CoversNothing;
use UniversalCarrier\Conformance\ShipmentCreatorConformanceTestCase;
use UniversalCarrier\Core\Contract\ShipmentCreatorInterface;
use UniversalCarrier\Core\Dto\ShipmentRequest;

/**
 * A contract including insurance: the insurance test checks the booking.
 */
#[CoversNothing]
class DemoInsuredShipmentCreatorConformanceTest extends ShipmentCreatorConformanceTestCase
{
    protected function createCarrier(): ShipmentCreatorInterface
    {
        return Fixtures::carrier(insuranceContract: true);
    }

    protected function bookableRequest(): ShipmentRequest
    {
        return Fixtures::homeDelivery();
    }
}
