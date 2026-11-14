<?php declare(strict_types=1);

namespace Tests\Conformance\Demo;

use PHPUnit\Framework\Attributes\CoversNothing;
use UniversalCarrier\Conformance\PickupPointProviderConformanceTestCase;
use UniversalCarrier\Core\Contract\PickupPointProviderInterface;
use UniversalCarrier\Core\Dto\{Address, PickupPointQuery};

#[CoversNothing]
class DemoPickupPointProviderConformanceTest extends PickupPointProviderConformanceTestCase
{
    protected function createCarrier(): PickupPointProviderInterface
    {
        return Fixtures::carrier();
    }

    protected function searchableQuery(): PickupPointQuery
    {
        return PickupPointQuery::forAddress(Fixtures::customer());
    }

    protected function unmatchedQuery(): PickupPointQuery
    {
        return PickupPointQuery::forAddress(new Address('Unter den Linden 1', '10117', 'Berlin', 'DE', company: 'Acme GmbH'));
    }
}
