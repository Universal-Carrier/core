<?php declare(strict_types=1);

namespace UniversalCarrier\Conformance;

use UniversalCarrier\Core\Contract\{CarrierInterface,
    PickupPointProviderInterface,
    RateProviderInterface,
    ShipmentCreatorInterface,
    TrackingProviderInterface};

/**
 * Checks what every driver owes, whatever its capabilities: its identity.
 *
 * Extend it once per driver.
 */
abstract class CarrierConformanceTestCase extends ConformanceTestCase
{

    /**
     * A fully configured instance of the driver under test.
     */
    abstract protected function createCarrier(): CarrierInterface;

    final public function testTheCodeIsLowercaseSnakeCase(): void
    {
        $this->assertMatchesRegularExpression(
            '/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/',
            $this->createCarrier()->getCode(),
            'getCode() MUST return a lowercase snake_case identifier (specification, section 5.1).'
        );
    }

    final public function testTheNameIsFitToDisplay(): void
    {
        $name = $this->createCarrier()->getName();

        $this->assertNotSame('', trim($name), 'getName() MUST return a name fit to display (section 5.1).');
        $this->assertSame(trim($name), $name, 'getName() should not carry surrounding whitespace.');
    }

    final public function testTheIdentityIsStable(): void
    {
        $first = $this->createCarrier();
        $second = $this->createCarrier();

        $this->assertSame($first->getCode(), $first->getCode());
        $this->assertSame($first->getCode(), $second->getCode(), 'getCode() MUST be stable (section 5.1).');
        $this->assertSame($first->getName(), $second->getName());
    }

    final public function testTheDriverImplementsAtLeastOneCapability(): void
    {
        $carrier = $this->createCarrier();

        $this->assertTrue(
            $carrier instanceof RateProviderInterface
            || $carrier instanceof PickupPointProviderInterface
            || $carrier instanceof ShipmentCreatorInterface
            || $carrier instanceof TrackingProviderInterface,
            'A conforming driver implements at least one capability interface (section 8).'
        );
    }
}
