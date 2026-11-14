<?php declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use UniversalCarrier\Core\Contract\CarrierInterface;
use UniversalCarrier\Core\Contract\PickupPointProviderInterface;
use UniversalCarrier\Core\Contract\RateProviderInterface;
use UniversalCarrier\Core\Contract\ShipmentCreatorInterface;
use UniversalCarrier\Core\Contract\TrackingProviderInterface;

/**
 * Locks the shape of the contract: one interface per operation, each carrying the identity.
 *
 * Moving a method from one interface to another, or dropping an `extends CarrierInterface`,
 * breaks every consumer typed on the narrower interface. These tests make that a failure
 * rather than a surprise.
 */
#[CoversNothing]
class CapabilityInterfacesTest extends TestCase
{
    /**
     * @param class-string $interface
     * @param list<string> $ownMethods
     */
    #[DataProvider('capabilityProvider')]
    public function testEachCapabilityDeclaresOnlyItsOwnOperation(string $interface, array $ownMethods): void
    {
        $reflection = new ReflectionClass($interface);

        $declared = array_values(array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            array_filter(
                $reflection->getMethods(),
                static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $interface
            )
        ));

        $this->assertTrue($reflection->isInterface());
        $this->assertSame($ownMethods, $declared);
    }

    /**
     * @param class-string $interface
     */
    #[DataProvider('capabilityProvider')]
    public function testEachCapabilityCarriesTheCarrierIdentity(string $interface): void
    {
        $this->assertTrue(is_subclass_of($interface, CarrierInterface::class));
    }

    /**
     * @return iterable<string, array{class-string, list<string>}>
     */
    public static function capabilityProvider(): iterable
    {
        yield 'rates' => [RateProviderInterface::class, ['getRates']];
        yield 'pickup points' => [PickupPointProviderInterface::class, ['getPickupPoints']];
        yield 'shipment creation' => [ShipmentCreatorInterface::class, ['supports', 'createShipment']];
        yield 'tracking' => [TrackingProviderInterface::class, ['track']];
    }

    public function testTheIdentityDeclaresNoOperation(): void
    {
        $methods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(CarrierInterface::class))->getMethods()
        );

        $this->assertSame(['getCode', 'getName'], $methods);
    }
}
