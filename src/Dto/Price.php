<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use UniversalCarrier\Core\Exception\InvalidArgumentException;

/**
 * Immutable Value Object representing a monetary amount and its currency.
 *
 * The amount is always expressed in the MINOR unit of its currency (cents for EUR, yen for
 * JPY, fils for KWD). Floating point numbers are never used to carry money, only to render it.
 *
 * A price is written once and never recomputed. Shipping is not a domain that does arithmetic
 * on money: a carrier quotes a rate, a shipper declares a customs value, and neither is ever
 * added to or discounted afterwards - a rebate granted to the customer is the merchant's
 * business and the carrier never hears about it. This object therefore offers comparison and
 * rendering, and no arithmetic at all.
 */
final readonly class Price
{

    /**
     * Currencies whose minor unit is not a hundredth of the major unit.
     *
     * ISO 4217 defines the exponent per currency; 2 covers the vast majority, so only the
     * exceptions are listed here.
     *
     * @var array<string, int>
     */
    private const array MINOR_UNIT_EXCEPTIONS = [
        'BHD' => 3, 'BIF' => 0, 'CLF' => 4, 'CLP' => 0, 'DJF' => 0, 'GNF' => 0, 'IQD' => 3,
        'ISK' => 0, 'JOD' => 3, 'JPY' => 0, 'KMF' => 0, 'KRW' => 0, 'KWD' => 3, 'LYD' => 3,
        'OMR' => 3, 'PYG' => 0, 'RWF' => 0, 'TND' => 3, 'UGX' => 0, 'UYW' => 4, 'VND' => 0,
        'VUV' => 0, 'XAF' => 0, 'XOF' => 0, 'XPF' => 0,
    ];

    public string $currency;

    /**
     * @param int $amount The amount in the minor unit of $currency (e.g. 1250 for 12.50 EUR).
     * @param string $currency The 3-letter ISO 4217 currency code.
     * @throws InvalidArgumentException
     */
    public function __construct(
        public int $amount,
        string $currency = 'EUR'
    ) {
        if ($this->amount < 0) {
            throw new InvalidArgumentException('Price amount cannot be negative.');
        }

        $normalizedCurrency = strtoupper(trim($currency));

        if (preg_match('/^[A-Z]{3}$/', $normalizedCurrency) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid value for currency: "%s". Expected a 3-letter ISO 4217 code.',
                $currency
            ));
        }

        $this->currency = $normalizedCurrency;
    }

    /**
     * Builds a price from a major-unit amount, rounding to the currency's minor unit.
     *
     * Provided for the common case of importing a price from a source that expresses it in
     * major units. Prefer the constructor whenever the minor-unit amount is already known.
     */
    public static function fromDecimal(float $amount, string $currency = 'EUR'): self
    {
        $exponent = self::getMinorUnitExponentFor($currency);

        return new self((int)round($amount * (10 ** $exponent)), $currency);
    }

    /**
     * Number of decimal places the currency uses, per ISO 4217.
     */
    public function getMinorUnitExponent(): int
    {
        return self::MINOR_UNIT_EXCEPTIONS[$this->currency] ?? 2;
    }

    /**
     * The amount rendered in the major unit, as an exact decimal string (e.g. "12.50").
     *
     * This is the representation to use for display, serialisation and carrier payloads.
     */
    public function toDecimalString(): string
    {
        $exponent = $this->getMinorUnitExponent();

        if ($exponent === 0) {
            return (string)$this->amount;
        }

        $digits = str_pad((string)$this->amount, $exponent + 1, '0', STR_PAD_LEFT);

        return substr($digits, 0, -$exponent) . '.' . substr($digits, -$exponent);
    }

    /**
     * The amount in the major unit as a float.
     *
     * Lossy by nature: use {@see toDecimalString()} for anything other than a quick display,
     * and never for arithmetic.
     */
    public function toFloat(): float
    {
        return (float)$this->toDecimalString();
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    private static function getMinorUnitExponentFor(string $currency): int
    {
        return self::MINOR_UNIT_EXCEPTIONS[strtoupper(trim($currency))] ?? 2;
    }
}
