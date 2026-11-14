<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Dto\ParcelItem;
use UniversalCarrier\Core\Dto\Price;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

#[CoversClass(ParcelItem::class)]
class ParcelItemTest extends TestCase
{
    public function testItBuildsAnItemAndNormalizesTheOriginCountry(): void
    {
        $item = new ParcelItem('Cotton t-shirt', 3, 0.2, new Price(1990), '610910', ' pt ');

        $this->assertSame('Cotton t-shirt', $item->description);
        $this->assertSame(3, $item->quantity);
        $this->assertSame('610910', $item->hsTariffCode);
        $this->assertSame('PT', $item->originCountry);
    }

    public function testItLeavesTheOriginCountryNullWhenNotProvided(): void
    {
        $this->assertNull((new ParcelItem('Book', 1, 0.4, new Price(1000)))->originCountry);
    }

    public function testItComputesTheTotalWeightAndPriceOfTheLine(): void
    {
        $item = new ParcelItem('Cotton t-shirt', 3, 0.2, new Price(1990));

        $this->assertEqualsWithDelta(0.6, $item->getTotalWeightInKilogram(), 0.0001);
        $this->assertTrue($item->getTotalPrice()->equals(new Price(5970)));
    }

    public function testItRejectsABlankDescription(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('description cannot be blank.');

        new ParcelItem('   ', 1, 0.2, new Price(100));
    }

    public function testItRejectsANonPositiveQuantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item quantity must be at least 1, got 0.');

        new ParcelItem('Book', 0, 0.4, new Price(1000));
    }

    public function testItRejectsANegativeUnitWeight(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item unit weight cannot be negative');

        new ParcelItem('Book', 1, -0.4, new Price(1000));
    }

    public function testItRejectsAnInvalidOriginCountry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value for originCountry');

        new ParcelItem('Book', 1, 0.4, new Price(1000), null, 'PRT');
    }
}
