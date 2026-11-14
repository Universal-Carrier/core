<?php declare(strict_types=1);

namespace UniversalCarrier\Conformance;

use UniversalCarrier\Core\Contract\TrackingProviderInterface;
use UniversalCarrier\Core\Dto\Tracking;
use UniversalCarrier\Core\Enum\TrackingStatus;
use UniversalCarrier\Core\Exception\ShipmentExceptionInterface;

/**
 * Checks {@see TrackingProviderInterface::track()} against section 5.6 of the specification.
 *
 * Extend it when the driver implements {@see TrackingProviderInterface}.
 *
 * The mapping of each carrier event onto a status cannot be checked from the outside: it is
 * the driver's own unit tests, fed with recorded carrier responses, that should cover it.
 */
abstract class TrackingProviderConformanceTestCase extends ConformanceTestCase
{

    /**
     * A fully configured instance of the driver under test.
     */
    abstract protected function createCarrier(): TrackingProviderInterface;

    /**
     * A tracking number the carrier knows, ideally with some history.
     */
    abstract protected function trackedNumber(): string;

    /**
     * A tracking number in this carrier's format that it has never seen. Null, the default,
     * skips the test.
     */
    protected function unscannedNumber(): ?string
    {
        return null;
    }

    /**
     * A string that cannot be a tracking number of this carrier. Null, the default, skips the
     * test.
     */
    protected function malformedNumber(): ?string
    {
        return null;
    }

    final public function testTheAnswerIsAboutTheNumberAskedFor(): void
    {
        $number = $this->trackedNumber();

        $this->assertSame(
            $number,
            $this->track($number)->trackingNumber,
            'Tracking::$trackingNumber MUST be the number asked for (section 5.6).'
        );
    }

    final public function testFinalityFollowsTheStatus(): void
    {
        $tracking = $this->track($this->trackedNumber());

        $this->assertSame($tracking->status->isFinal(), $tracking->isFinal());
    }

    final public function testANumberNotScannedYetIsPendingNotAnError(): void
    {
        $number = $this->unscannedNumber() ?? self::markTestSkipped('No unscannedNumber() provided.');

        $this->assertSame(
            TrackingStatus::PENDING,
            $this->track($number)->status,
            'A well-formed number the carrier does not know yet MUST be pending (section 5.6).'
        );
    }

    final public function testANumberThatCannotBelongToTheCarrierIsRejected(): void
    {
        $number = $this->malformedNumber() ?? self::markTestSkipped('No malformedNumber() provided.');

        $this->expectException(ShipmentExceptionInterface::class);

        $this->track($number);
    }

    private function track(string $trackingNumber): Tracking
    {
        $carrier = $this->createCarrier();

        return $this->guarded(static fn (): Tracking => $carrier->track($trackingNumber));
    }
}
