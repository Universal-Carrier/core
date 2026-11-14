<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use UniversalCarrier\Core\Exception\InvalidArgumentException;
use UniversalCarrier\Core\Support\Assert;

/**
 * Immutable Data Transfer Object representing a single physical package.
 *
 * Canonical units are the KILOGRAM for weight and the CENTIMETRE for dimensions. Every
 * conforming implementation reads these units and converts to whatever its carrier expects;
 * consumers never have to guess. Helpers are provided for the two other units carriers
 * commonly ask for.
 *
 * A parcel carries its own weight rather than deriving it from its contents: the weight is
 * what the carrier prices and what the sorting centre verifies, and it is known for every
 * shipment, whereas an itemised content is only needed for customs.
 */
final readonly class Parcel
{

    /** @var ParcelItem[] */
    public array $items;

    public ?string $reference;

    /**
     * @param float $weightInKilogram The gross weight of the parcel, in kilograms. Must be
     *   strictly positive: carriers reject zero-weight parcels.
     * @param float|null $length The longest side, in centimetres.
     * @param float|null $width The second longest side, in centimetres.
     * @param float|null $height The shortest side, in centimetres.
     * @param ParcelItem[] $items The declared content, required when the shipment goes through
     *   customs and optional otherwise. Whether it does is for the driver to determine.
     * @param Price|null $insuranceValue The declared value to insure. Null means no insurance.
     *   Carriers exposing fixed insurance tiers map this amount to their nearest tier.
     * @param string|null $reference A per-parcel reference printed on the label, useful to
     *   identify one box within a multi-parcel shipment.
     * @throws InvalidArgumentException
     */
    public function __construct(
        public float $weightInKilogram,
        public ?float $length = null,
        public ?float $width = null,
        public ?float $height = null,
        array $items = [],
        public ?Price $insuranceValue = null,
        ?string $reference = null
    ) {
        $this->reference = Assert::trimToNull($reference);

        if ($this->weightInKilogram <= 0.0) {
            throw new InvalidArgumentException(sprintf(
                'Parcel weight must be strictly positive, got %s kg.',
                $this->weightInKilogram
            ));
        }

        foreach (['length' => $this->length, 'width' => $this->width, 'height' => $this->height] as $name => $value) {
            if ($value !== null && $value <= 0.0) {
                throw new InvalidArgumentException(sprintf(
                    'Parcel %s must be strictly positive when provided, got %s cm.',
                    $name,
                    $value
                ));
            }
        }

        Assert::listOf($items, ParcelItem::class, 'items');

        $this->items = array_values($items);
    }

    /**
     * The parcel weight in grams, rounded to the nearest gram.
     */
    public function getWeightInGram(): int
    {
        return (int)round($this->weightInKilogram * 1000);
    }

    /**
     * The combined weight of the declared items, in kilograms.
     *
     * This is the net weight of the content and is normally lower than
     * {@see $weightInKilogram}, which includes packaging. Returns 0.0 when no item is declared.
     */
    public function getItemsWeightInKilogram(): float
    {
        return array_reduce(
            $this->items,
            static fn (float $total, ParcelItem $item): float => $total + $item->getTotalWeightInKilogram(),
            0.0
        );
    }

    /**
     * The combined declared value of the items, or null when no item is declared.
     *
     * This is the only place in the package where amounts are summed, which is why the sum is
     * done here on plain integers rather than through arithmetic on {@see Price}: money in
     * this domain is written once, and a value object that could add would only invite it to
     * be recomputed elsewhere.
     *
     * @throws InvalidArgumentException If the items do not share a single currency.
     */
    public function getDeclaredValue(): ?Price
    {
        if ($this->items === []) {
            return null;
        }

        $currency = $this->items[0]->unitPrice->currency;
        $total = 0;

        foreach ($this->items as $item) {
            if ($item->unitPrice->currency !== $currency) {
                throw new InvalidArgumentException(sprintf(
                    'Cannot total a parcel declaring items in different currencies: %s and %s.',
                    $currency,
                    $item->unitPrice->currency
                ));
            }

            $total += $item->unitPrice->amount * $item->quantity;
        }

        return new Price($total, $currency);
    }

    public function getLengthInMillimeter(): ?int
    {
        return $this->length === null ? null : (int)round($this->length * 10);
    }

    public function getWidthInMillimeter(): ?int
    {
        return $this->width === null ? null : (int)round($this->width * 10);
    }

    public function getHeightInMillimeter(): ?int
    {
        return $this->height === null ? null : (int)round($this->height * 10);
    }

    /**
     * Whether all three dimensions are known.
     *
     * Volumetric pricing and size-based service eligibility both require a complete set.
     */
    public function hasDimensions(): bool
    {
        return $this->length !== null && $this->width !== null && $this->height !== null;
    }

    /**
     * The volume of the parcel in cubic centimetres, or null when a dimension is missing.
     */
    public function getVolumeInCubicCentimeter(): ?float
    {
        if (!$this->hasDimensions()) {
            return null;
        }

        return (float)$this->length * (float)$this->width * (float)$this->height;
    }

    /**
     * The sum of the three dimensions in centimetres, or null when one is missing.
     *
     * Carriers use girth-based limits to decide whether a parcel is oversized. Exposing the
     * measurement rather than an `isOversized` flag keeps the decision where it belongs: with
     * the carrier implementation, which alone knows its own thresholds.
     */
    public function getCombinedDimensionsInCentimeter(): ?float
    {
        if (!$this->hasDimensions()) {
            return null;
        }

        return (float)$this->length + (float)$this->width + (float)$this->height;
    }

    public function isInsured(): bool
    {
        return $this->insuranceValue !== null && !$this->insuranceValue->isZero();
    }
}
