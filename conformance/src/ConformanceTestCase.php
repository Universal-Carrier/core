<?php declare(strict_types=1);

namespace UniversalCarrier\Conformance;

use PHPUnit\Framework\TestCase;
use Throwable;
use UniversalCarrier\Core\Exception\CarrierExceptionInterface;

/**
 * What every conformance test case shares.
 *
 * Every call to the driver goes through {@see guarded()}, so that the rule no single test can
 * check on its own - no foreign exception may escape a contract method - is checked by all of
 * them at once.
 */
abstract class ConformanceTestCase extends TestCase
{

    /**
     * Runs a call to the driver, failing the test if it lets a foreign exception escape.
     *
     * Exceptions implementing {@see CarrierExceptionInterface} are rethrown untouched, so that
     * tests expecting one of them still see it.
     *
     * @template T
     * @param callable(): T $call
     * @return T
     */
    final protected function guarded(callable $call): mixed
    {
        try {
            return $call();
        } catch (CarrierExceptionInterface $e) {
            throw $e;
        } catch (Throwable $e) {
            self::fail(sprintf(
                'A contract method let %s escape ("%s"). Every exception thrown by a driver MUST'
                . ' implement %s; wrap foreign ones, passing the original as $previous'
                . ' (specification, section 6, rule 1).',
                $e::class,
                $e->getMessage(),
                CarrierExceptionInterface::class
            ));
        }
    }

    /**
     * An order reference no previous run has used, so that sandboxes rejecting duplicates do
     * not fail a booking for a reason unrelated to the test.
     */
    final protected static function uniqueReference(): string
    {
        return 'CONFORMANCE-' . strtoupper(bin2hex(random_bytes(4)));
    }
}
