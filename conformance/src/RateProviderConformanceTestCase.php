<?php declare(strict_types=1);

namespace UniversalCarrier\Conformance;

use UniversalCarrier\Core\Contract\RateProviderInterface;
use UniversalCarrier\Core\Dto\{Parcel, Rate, RateRequest};
use UniversalCarrier\Core\Exception\ShipmentExceptionInterface;

/**
 * Checks {@see RateProviderInterface::getRates()} against section 5.3 of the specification.
 *
 * Extend it when the driver implements {@see RateProviderInterface}.
 */
abstract class RateProviderConformanceTestCase extends ConformanceTestCase
{

    /**
     * A fully configured instance of the driver under test.
     */
    abstract protected function createCarrier(): RateProviderInterface;

    /**
     * A shipment the carrier can quote: at least one rate is expected, ideally several.
     */
    abstract protected function quotableRequest(): RateRequest;

    /**
     * A shipment the carrier has nothing to offer for.
     *
     * Defaults to the quotable shipment with a single 1,000 kg parcel. Override it for a
     * carrier that carries freight.
     */
    protected function unquotableRequest(): RateRequest
    {
        $quotable = $this->quotableRequest();

        return new RateRequest(
            $quotable->sender,
            $quotable->recipient,
            [new Parcel(1000.0)],
            $quotable->shippingDate,
            metadata: $quotable->metadata
        );
    }

    /**
     * A shipment the carrier cannot even assess, such as one to a postal code that does not
     * exist. Null, the default, skips the test.
     */
    protected function invalidRequest(): ?RateRequest
    {
        return null;
    }

    final public function testAQuotableShipmentIsQuoted(): void
    {
        $this->assertNotSame(
            [],
            $this->quote($this->quotableRequest()),
            'quotableRequest() returned no rate: provide a shipment the carrier can quote.'
        );
    }

    final public function testRatesAreSortedCheapestFirst(): void
    {
        $amounts = array_map(static fn (Rate $rate): int => $rate->price->amount, $this->quote($this->quotableRequest()));
        $sorted = $amounts;
        sort($sorted);

        $this->assertSame($sorted, $amounts, 'Rates MUST be sorted by ascending price (section 5.3).');
    }

    final public function testRatesShareOneCurrency(): void
    {
        $currencies = array_unique(array_map(
            static fn (Rate $rate): string => $rate->price->currency,
            $this->quote($this->quotableRequest())
        ));

        $this->assertLessThanOrEqual(1, count($currencies), 'Prices MUST share one currency (section 5.3).');
    }

    final public function testNothingToOfferIsAnEmptyArrayNotAnException(): void
    {
        $this->assertSame(
            [],
            $this->quote($this->unquotableRequest()),
            'A shipment the carrier cannot carry MUST be quoted as an empty array (section 5.3).'
        );
    }

    final public function testARequestTheCarrierCannotAssessIsRejected(): void
    {
        $request = $this->invalidRequest() ?? self::markTestSkipped('No invalidRequest() provided.');

        $this->expectException(ShipmentExceptionInterface::class);

        $this->quote($request);
    }

    /**
     * @return Rate[]
     */
    private function quote(RateRequest $request): array
    {
        $carrier = $this->createCarrier();

        return $this->guarded(static fn (): array => $carrier->getRates($request));
    }
}
