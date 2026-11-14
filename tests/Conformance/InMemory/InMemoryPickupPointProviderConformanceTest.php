<?php declare(strict_types=1);

namespace Tests\Conformance\InMemory;

use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Fixture\InMemoryCarrier;
use UniversalCarrier\Conformance\PickupPointProviderConformanceTestCase;
use UniversalCarrier\Core\Contract\PickupPointProviderInterface;
use UniversalCarrier\Core\Dto\PickupPointQuery;
use UniversalCarrier\Core\Enum\PickupPointType;

#[CoversNothing]
class InMemoryPickupPointProviderConformanceTest extends PickupPointProviderConformanceTestCase
{
    protected function createCarrier(): PickupPointProviderInterface
    {
        return new InMemoryCarrier(pickupPoints: Fixtures::pickupPoints());
    }

    protected function searchableQuery(): PickupPointQuery
    {
        return PickupPointQuery::forAddress(Fixtures::paris());
    }

    protected function unmatchedQuery(): PickupPointQuery
    {
        return new PickupPointQuery(address: Fixtures::paris(), types: [PickupPointType::CARRIER_DEPOT]);
    }
}
