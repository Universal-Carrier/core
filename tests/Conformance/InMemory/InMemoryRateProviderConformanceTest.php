<?php declare(strict_types=1);

namespace Tests\Conformance\InMemory;

use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Fixture\InMemoryCarrier;
use UniversalCarrier\Conformance\RateProviderConformanceTestCase;
use UniversalCarrier\Core\Contract\RateProviderInterface;
use UniversalCarrier\Core\Dto\{Parcel, RateRequest};

#[CoversNothing]
class InMemoryRateProviderConformanceTest extends RateProviderConformanceTestCase
{
    protected function createCarrier(): RateProviderInterface
    {
        return new InMemoryCarrier();
    }

    protected function quotableRequest(): RateRequest
    {
        return new RateRequest(Fixtures::paris(), Fixtures::paris(), [new Parcel(1.2)]);
    }
}
