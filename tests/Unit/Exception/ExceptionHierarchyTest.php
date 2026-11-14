<?php declare(strict_types=1);

namespace Tests\Unit\Exception;

use InvalidArgumentException as SplInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use UniversalCarrier\Core\Enum\ShipmentOption;
use UniversalCarrier\Core\Exception\CarrierException;
use UniversalCarrier\Core\Exception\CarrierExceptionInterface;
use UniversalCarrier\Core\Exception\ConnectionException;
use UniversalCarrier\Core\Exception\ConnectionExceptionInterface;
use UniversalCarrier\Core\Exception\InvalidArgumentException;
use UniversalCarrier\Core\Exception\InvalidArgumentExceptionInterface;
use UniversalCarrier\Core\Exception\ShipmentException;
use UniversalCarrier\Core\Exception\ShipmentExceptionInterface;
use UniversalCarrier\Core\Exception\UnsupportedOptionException;
use UniversalCarrier\Core\Exception\UnsupportedOptionExceptionInterface;

#[CoversClass(CarrierException::class)]
#[CoversClass(ConnectionException::class)]
#[CoversClass(InvalidArgumentException::class)]
#[CoversClass(ShipmentException::class)]
#[CoversClass(UnsupportedOptionException::class)]
class ExceptionHierarchyTest extends TestCase
{
    /**
     * @param class-string<Throwable> $class
     */
    #[DataProvider('exceptionProvider')]
    public function testEveryExceptionIsCatchableThroughTheRootInterface(string $class): void
    {
        $exception = new $class('boom');

        $this->assertInstanceOf(CarrierExceptionInterface::class, $exception);
        $this->assertInstanceOf(Throwable::class, $exception);
        $this->assertSame('boom', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{class-string<Throwable>}>
     */
    public static function exceptionProvider(): iterable
    {
        yield 'base' => [CarrierException::class];
        yield 'shipment' => [ShipmentException::class];
        yield 'connection' => [ConnectionException::class];
        yield 'unsupported option' => [UnsupportedOptionException::class];
        yield 'invalid argument' => [InvalidArgumentException::class];
    }

    public function testEachConcreteClassImplementsItsOwnInterface(): void
    {
        $this->assertInstanceOf(ShipmentExceptionInterface::class, new ShipmentException());
        $this->assertInstanceOf(ConnectionExceptionInterface::class, new ConnectionException());
        $this->assertInstanceOf(UnsupportedOptionExceptionInterface::class, new UnsupportedOptionException());
        $this->assertInstanceOf(InvalidArgumentExceptionInterface::class, new InvalidArgumentException());
    }

    /**
     * The specific interfaces partition the error space: a consumer catching one of them must
     * never accidentally catch a different kind of failure.
     *
     * @param class-string<Throwable> $class
     * @param class-string $unexpectedInterface
     */
    #[DataProvider('disjointProvider')]
    public function testTheSpecificInterfacesDoNotOverlap(string $class, string $unexpectedInterface): void
    {
        $this->assertFalse(is_a($class, $unexpectedInterface, true));
    }

    /**
     * @return iterable<string, array{class-string<Throwable>, class-string}>
     */
    public static function disjointProvider(): iterable
    {
        yield 'shipment is not a connection error' => [ShipmentException::class, ConnectionExceptionInterface::class];
        yield 'connection is not a shipment error' => [ConnectionException::class, ShipmentExceptionInterface::class];
        yield 'validation is not a shipment error' => [InvalidArgumentException::class, ShipmentExceptionInterface::class];
        yield 'base is not a shipment error' => [CarrierException::class, ShipmentExceptionInterface::class];
        yield 'base is not a connection error' => [CarrierException::class, ConnectionExceptionInterface::class];
        yield 'base is not an unsupported option error' => [CarrierException::class, UnsupportedOptionExceptionInterface::class];
    }

    public function testValidationErrorsStayCompatibleWithTheSplHierarchy(): void
    {
        $this->assertInstanceOf(SplInvalidArgumentException::class, new InvalidArgumentException());
    }

    public function testItBuildsAnUnsupportedOptionMessage(): void
    {
        $exception = UnsupportedOptionException::forOption(ShipmentOption::INSURANCE, 'DHL Express');

        $this->assertSame('Carrier "DHL Express" does not support the "insurance" shipment option.', $exception->getMessage());
    }

    public function testItPreservesThePreviousException(): void
    {
        $previous = new SplInvalidArgumentException('root cause');
        $exception = new ConnectionException('wrapped', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
