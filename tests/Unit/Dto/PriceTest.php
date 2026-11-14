<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Dto\Price;
use UniversalCarrier\Core\Exception\CarrierExceptionInterface;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

#[CoversClass(Price::class)]
class PriceTest extends TestCase
{
    public function testItBuildsAPriceAndNormalizesCurrency(): void
    {
        $price = new Price(1490, ' eur ');

        $this->assertSame(1490, $price->amount);
        $this->assertSame('EUR', $price->currency);
    }

    public function testItUsesEurAsDefaultCurrency(): void
    {
        $this->assertSame('EUR', (new Price(250))->currency);
    }

    public function testItRejectsNegativeAmounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Price amount cannot be negative.');

        new Price(-1);
    }

    #[DataProvider('invalidCurrencyProvider')]
    public function testItRejectsInvalidCurrencies(string $currency): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected a 3-letter ISO 4217 code.');

        new Price(100, $currency);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCurrencyProvider(): iterable
    {
        yield 'too long' => ['EURO'];
        yield 'too short' => ['EU'];
        yield 'digits' => ['1A3'];
        yield 'empty' => [''];
    }

    public function testItIsCatchableThroughTheRootInterface(): void
    {
        $this->expectException(CarrierExceptionInterface::class);

        new Price(-1);
    }

    #[DataProvider('decimalStringProvider')]
    public function testItRendersTheAmountInItsMajorUnit(int $amount, string $currency, string $expected): void
    {
        $this->assertSame($expected, (new Price($amount, $currency))->toDecimalString());
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function decimalStringProvider(): iterable
    {
        yield 'two decimals' => [1490, 'EUR', '14.90'];
        yield 'below one unit' => [5, 'EUR', '0.05'];
        yield 'zero' => [0, 'EUR', '0.00'];
        yield 'no decimals (JPY)' => [1490, 'JPY', '1490'];
        yield 'three decimals (KWD)' => [1490, 'KWD', '1.490'];
        yield 'three decimals below one unit' => [5, 'BHD', '0.005'];
    }

    public function testItExposesTheIso4217MinorUnitExponent(): void
    {
        $this->assertSame(2, (new Price(1, 'EUR'))->getMinorUnitExponent());
        $this->assertSame(0, (new Price(1, 'JPY'))->getMinorUnitExponent());
        $this->assertSame(3, (new Price(1, 'TND'))->getMinorUnitExponent());
    }

    public function testItBuildsFromAMajorUnitAmount(): void
    {
        $this->assertSame(1490, Price::fromDecimal(14.90)->amount);
        $this->assertSame(1490, Price::fromDecimal(1490.0, 'JPY')->amount);
        $this->assertSame(1490, Price::fromDecimal(1.49, 'KWD')->amount);
    }

    public function testItConvertsToFloatThroughItsExactDecimalRepresentation(): void
    {
        $this->assertSame(14.9, (new Price(1490))->toFloat());
        $this->assertSame(1490.0, (new Price(1490, 'JPY'))->toFloat());
    }

    public function testItComparesByAmountAndCurrency(): void
    {
        $this->assertTrue((new Price(100))->equals(new Price(100, 'eur')));
        $this->assertFalse((new Price(100))->equals(new Price(101)));
        $this->assertFalse((new Price(100))->equals(new Price(100, 'USD')));
        $this->assertTrue((new Price(0))->isZero());
    }

}
