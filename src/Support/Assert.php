<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Support;

use DateTimeZone;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

/**
 * Runtime guards shared by the Data Transfer Objects of this package.
 *
 * PHP has no generics, so a `Parcel[]` annotation is a promise made to the static analyser
 * only. These guards turn that promise into a runtime invariant, which is what allows a
 * consumer to trust the content of a collection it did not build itself.
 *
 * @internal This helper is an implementation detail and is not part of the specification.
 */
final class Assert
{

    /**
     * Asserts that every value of the given array is an instance of $expectedType.
     *
     * @param mixed[] $values
     * @param class-string $expectedType
     * @throws InvalidArgumentException
     */
    public static function listOf(array $values, string $expectedType, string $property): void
    {
        foreach ($values as $value) {
            if (!$value instanceof $expectedType) {
                throw new InvalidArgumentException(sprintf(
                    'All items in %s must be instances of %s, %s found.',
                    $property,
                    $expectedType,
                    get_debug_type($value)
                ));
            }
        }
    }

    /**
     * Asserts that the given array is not empty.
     *
     * @param mixed[] $values
     * @throws InvalidArgumentException
     */
    public static function notEmpty(array $values, string $message): void
    {
        if ($values === []) {
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * Normalises and validates an ISO 3166-1 alpha-2 country code.
     *
     * @throws InvalidArgumentException
     */
    public static function countryCode(string $countryCode, string $property = 'countryCode'): string
    {
        $normalized = strtoupper(trim($countryCode));

        if (preg_match('/^[A-Z]{2}$/', $normalized) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid value for %s: "%s". Expected a 2-letter ISO 3166-1 alpha-2 code.',
                $property,
                $countryCode
            ));
        }

        return $normalized;
    }

    /**
     * Normalises and validates an ISO 639-1 alpha-2 language code.
     *
     * @throws InvalidArgumentException
     */
    public static function languageCode(string $languageCode, string $property = 'language'): string
    {
        $normalized = strtolower(trim($languageCode));

        if (preg_match('/^[a-z]{2}$/', $normalized) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid value for %s: "%s". Expected a 2-letter ISO 639-1 code.',
                $property,
                $languageCode
            ));
        }

        return $normalized;
    }

    /**
     * Asserts that a string is not blank once trimmed, and returns the trimmed value.
     *
     * The caller is expected to store what this returns rather than its own input: leading
     * and trailing whitespace in a postal code or a service code is silently rejected by most
     * carrier APIs, and normalising it here is what keeps `===` comparisons meaningful.
     *
     * @throws InvalidArgumentException
     */
    public static function notBlank(string $value, string $property): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException(sprintf('%s cannot be blank.', $property));
        }

        return $trimmed;
    }

    /**
     * Trims an optional string, turning a blank one into null.
     *
     * An optional field filled with spaces carries no more information than an absent one,
     * and collapsing the two removes a whole class of "is it empty?" checks downstream.
     */
    public static function trimToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Validates an IANA time zone identifier (e.g. "Europe/Paris").
     *
     * @throws InvalidArgumentException
     */
    public static function timezone(string $timezone, string $property = 'timezone'): string
    {
        $trimmed = trim($timezone);

        if (!in_array($trimmed, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid value for %s: "%s". Expected an IANA time zone identifier such as "Europe/Paris".',
                $property,
                $timezone
            ));
        }

        return $trimmed;
    }
}
