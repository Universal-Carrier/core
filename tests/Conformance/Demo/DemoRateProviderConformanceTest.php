<?php declare(strict_types=1);

namespace Tests\Conformance\Demo;

use PHPUnit\Framework\Attributes\CoversNothing;
use UniversalCarrier\Conformance\RateProviderConformanceTestCase;
use UniversalCarrier\Core\Contract\RateProviderInterface;
use UniversalCarrier\Core\Dto\{Address, Parcel, RateRequest};

#[CoversNothing]
class DemoRateProviderConformanceTest extends RateProviderConformanceTestCase
{
    protected function createCarrier(): RateProviderInterface
    {
        return Fixtures::carrier();
    }

    protected function quotableRequest(): RateRequest
    {
        return new RateRequest(Fixtures::shop(), Fixtures::customer(), [new Parcel(1.45)]);
    }

    protected function invalidRequest(): RateRequest
    {
        return new RateRequest(
            Fixtures::shop(),
            new Address('1 rue Inconnue', '00000', 'Nowhere', 'FR', company: 'Ghost Ltd'),
            [new Parcel(1.45)]
        );
    }
}
