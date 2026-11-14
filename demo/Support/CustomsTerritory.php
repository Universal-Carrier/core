<?php declare(strict_types=1);

namespace Demo\Support;

use UniversalCarrier\Core\Dto\Address;

/**
 * Demo Carrier's rule for when a shipment needs a customs declaration.
 *
 * This is carrier knowledge, which is why the package does not provide it: comparing two
 * country codes gets exactly the interesting cases wrong. France to Germany changes country
 * without customs; France to Guadeloupe, often recorded as country "FR" with a 971 postal
 * code, goes through customs without changing country - and so does mainland Spain to the
 * Canary Islands.
 *
 * The rule below is simplified for the demo. A real driver takes it from its carrier's
 * documentation, which is the only authority on where that carrier draws the lines.
 */
final class CustomsTerritory
{

    private const array EU_MEMBER_STATES = ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR',
        'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];

    public static function requiresDeclaration(Address $from, Address $to): bool
    {
        return self::of($from->countryCode, $from->postalCode) !== self::of($to->countryCode, $to->postalCode);
    }

    /**
     * The customs territory an address belongs to: "EU" for the single market, otherwise an
     * identifier of its own. Two addresses in the same territory need no declaration.
     */
    public static function of(string $countryCode, string $postalCode): string
    {
        return match (true) {
            // French overseas departments and territories: 97x and 98x, except Monaco (980).
            $countryCode === 'FR' && preg_match('/^9[78](?!0)/', $postalCode) === 1 => 'FR-' . substr($postalCode, 0, 3),
            // Canary Islands (35, 38), Ceuta (51) and Melilla (52).
            $countryCode === 'ES' && preg_match('/^(35|38|51|52)/', $postalCode) === 1 => 'ES-' . substr($postalCode, 0, 2),
            in_array($countryCode, self::EU_MEMBER_STATES, true) => 'EU',
            default => $countryCode,
        };
    }
}
