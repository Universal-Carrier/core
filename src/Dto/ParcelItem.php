<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use UniversalCarrier\Core\Exception\InvalidArgumentException;
use UniversalCarrier\Core\Support\Assert;

/**
 * Immutable Data Transfer Object describing one line of a parcel's content.
 *
 * Items exist primarily to satisfy customs: a shipment that requires a declaration needs a
 * description, a value, a weight, an HS code and a country of origin per line. Which shipments
 * require one is for the driver to decide - it depends on the carrier and on the territories
 * involved, not merely on two country codes. Most shipments need none of this, which is why
 * {@see Parcel} carries its own weight and treats items as optional.
 */
final readonly class ParcelItem
{

    public string $description;

    public ?string $hsTariffCode;

    public ?string $originCountry;

    /**
     * @param string $description A plain-language description of the goods, in the language
     *   expected by the destination customs authority. Codes and internal SKUs are rejected.
     * @param int $quantity The number of identical units, at least 1.
     * @param float $unitWeightInKilogram The weight of a single unit, in kilograms.
     * @param Price $unitPrice The customs value of a single unit.
     * @param string|null $hsTariffCode The Harmonized System code of the goods (6 to 10 digits).
     * @param string|null $originCountry The 2-letter ISO 3166-1 alpha-2 code of the country
     *   where the goods were manufactured, which is not necessarily the sender's country.
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $description,
        public int $quantity,
        public float $unitWeightInKilogram,
        public Price $unitPrice,
        ?string $hsTariffCode = null,
        ?string $originCountry = null
    ) {
        $this->description = Assert::notBlank($description, 'description');
        $this->hsTariffCode = Assert::trimToNull($hsTariffCode);

        if ($this->quantity < 1) {
            throw new InvalidArgumentException(sprintf('Item quantity must be at least 1, got %d.', $this->quantity));
        }

        if ($this->unitWeightInKilogram < 0.0) {
            throw new InvalidArgumentException(sprintf(
                'Item unit weight cannot be negative, got %s.',
                $this->unitWeightInKilogram
            ));
        }

        $this->originCountry = $originCountry === null ? null : Assert::countryCode($originCountry, 'originCountry');
    }

    /**
     * The combined weight of every unit of this line, in kilograms.
     */
    public function getTotalWeightInKilogram(): float
    {
        return $this->unitWeightInKilogram * $this->quantity;
    }

    /**
     * The combined customs value of every unit of this line.
     *
     * @throws InvalidArgumentException
     */
    public function getTotalPrice(): Price
    {
        return new Price($this->unitPrice->amount * $this->quantity, $this->unitPrice->currency);
    }
}
