<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Dto\Parcel;
use UniversalCarrier\Core\Dto\ParcelItem;
use UniversalCarrier\Core\Dto\Price;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

#[CoversClass(Parcel::class)]
class ParcelTest extends TestCase
{
    public function testItBuildsAParcelFromItsWeightAlone(): void
    {
        $parcel = new Parcel(1.45);

        $this->assertSame(1.45, $parcel->weightInKilogram);
        $this->assertSame(1450, $parcel->getWeightInGram());
        $this->assertSame([], $parcel->items);
        $this->assertFalse($parcel->hasDimensions());
        $this->assertFalse($parcel->isInsured());
    }

    public function testItRejectsANonPositiveWeight(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parcel weight must be strictly positive');

        new Parcel(0.0);
    }

    public function testItRejectsANonPositiveDimension(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parcel width must be strictly positive when provided');

        new Parcel(1.0, 30.0, 0.0, 10.0);
    }

    public function testItConvertsDimensionsToMillimeters(): void
    {
        $parcel = new Parcel(1.0, 30.0, 20.5, 10.0);

        $this->assertSame(300, $parcel->getLengthInMillimeter());
        $this->assertSame(205, $parcel->getWidthInMillimeter());
        $this->assertSame(100, $parcel->getHeightInMillimeter());
    }

    public function testItReturnsNullForMissingDimensionsRatherThanZero(): void
    {
        $parcel = new Parcel(1.0, 30.0);

        $this->assertSame(300, $parcel->getLengthInMillimeter());
        $this->assertNull($parcel->getWidthInMillimeter());
        $this->assertNull($parcel->getHeightInMillimeter());
        $this->assertFalse($parcel->hasDimensions());
        $this->assertNull($parcel->getVolumeInCubicCentimeter());
        $this->assertNull($parcel->getCombinedDimensionsInCentimeter());
    }

    public function testItComputesVolumeAndCombinedDimensions(): void
    {
        $parcel = new Parcel(1.0, 30.0, 20.0, 10.0);

        $this->assertTrue($parcel->hasDimensions());
        $this->assertSame(6000.0, $parcel->getVolumeInCubicCentimeter());
        $this->assertSame(60.0, $parcel->getCombinedDimensionsInCentimeter());
    }

    public function testItValidatesItemsAtRuntime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All items in items must be instances of');

        /** @phpstan-ignore argument.type */
        new Parcel(1.0, items: ['a t-shirt']);
    }

    public function testItReindexesItems(): void
    {
        $parcel = new Parcel(1.0, items: [7 => new ParcelItem('Book', 1, 0.4, new Price(1000))]);

        $this->assertSame([0], array_keys($parcel->items));
    }

    public function testItAggregatesTheDeclaredContent(): void
    {
        $parcel = new Parcel(1.45, items: [
            new ParcelItem('Cotton t-shirt', 3, 0.2, new Price(1990)),
            new ParcelItem('Book', 1, 0.4, new Price(1500)),
        ]);

        $this->assertEqualsWithDelta(1.0, $parcel->getItemsWeightInKilogram(), 0.0001);
        $this->assertTrue($parcel->getDeclaredValue()?->equals(new Price(7470)));
    }

    public function testItTotalsTheDeclaredValueOnIntegerMinorUnits(): void
    {
        $parcel = new Parcel(1.0, items: [
            new ParcelItem('Trinket', 3, 0.1, new Price(5, 'EUR')),
            new ParcelItem('Bauble', 7, 0.1, new Price(3, 'EUR')),
        ]);

        $declared = $parcel->getDeclaredValue();

        // 3 * 5 + 7 * 3 = 36 cents, exact - no float ever touches the amount.
        $this->assertNotNull($declared);
        $this->assertSame(36, $declared->amount);
        $this->assertSame('0.36', $declared->toDecimalString());
    }

    public function testItHasNoDeclaredValueWithoutItems(): void
    {
        $this->assertNull((new Parcel(1.0))->getDeclaredValue());
        $this->assertSame(0.0, (new Parcel(1.0))->getItemsWeightInKilogram());
    }

    public function testItRefusesToAggregateItemsInDifferentCurrencies(): void
    {
        $parcel = new Parcel(1.0, items: [
            new ParcelItem('Book', 1, 0.4, new Price(1000, 'EUR')),
            new ParcelItem('Mug', 1, 0.3, new Price(1000, 'USD')),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot total a parcel declaring items in different currencies: EUR and USD.');

        $parcel->getDeclaredValue();
    }

    public function testItReportsInsurance(): void
    {
        $this->assertTrue((new Parcel(1.0, insuranceValue: new Price(15000)))->isInsured());
        $this->assertFalse((new Parcel(1.0, insuranceValue: new Price(0)))->isInsured());
    }
}
