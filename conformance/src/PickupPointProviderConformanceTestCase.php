<?php declare(strict_types=1);

namespace UniversalCarrier\Conformance;

use UniversalCarrier\Core\Contract\PickupPointProviderInterface;
use UniversalCarrier\Core\Dto\{PickupPoint, PickupPointQuery};

/**
 * Checks {@see PickupPointProviderInterface::getPickupPoints()} against section 5.4 of the
 * specification.
 *
 * Extend it when the driver implements {@see PickupPointProviderInterface}.
 */
abstract class PickupPointProviderConformanceTestCase extends ConformanceTestCase
{

    /**
     * A fully configured instance of the driver under test.
     */
    abstract protected function createCarrier(): PickupPointProviderInterface;

    /**
     * A search that finds points - at least two, for the sorting and limit checks to mean
     * something.
     */
    abstract protected function searchableQuery(): PickupPointQuery;

    /**
     * A search that finds nothing, such as one in a country the carrier does not serve. Null,
     * the default, skips the test.
     */
    protected function unmatchedQuery(): ?PickupPointQuery
    {
        return null;
    }

    final public function testASearchFindsPoints(): void
    {
        $this->assertNotSame(
            [],
            $this->search($this->searchableQuery()),
            'searchableQuery() found no point: provide a search the carrier can answer.'
        );
    }

    final public function testPointsAreSortedNearestFirst(): void
    {
        $distances = array_map(
            static fn (PickupPoint $point): float => $point->distanceInKilometer ?? PHP_FLOAT_MAX,
            $this->search($this->searchableQuery())
        );
        $sorted = $distances;
        sort($sorted);

        $this->assertSame(
            $sorted,
            $distances,
            'Points MUST be sorted by ascending distance, points without one last (section 5.4).'
        );
    }

    final public function testTheLimitIsHonoured(): void
    {
        $query = $this->searchableQuery();

        $this->assertLessThanOrEqual(
            1,
            count($this->search($this->withLimit($query, 1))),
            'The result MUST NOT exceed PickupPointQuery::$limit (section 5.4).'
        );
    }

    final public function testPointIdsAreUnique(): void
    {
        $ids = array_map(static fn (PickupPoint $point): string => $point->id, $this->search($this->searchableQuery()));

        $this->assertSame(
            array_values(array_unique($ids)),
            $ids,
            'Two points of one answer share an id, so a booking cannot tell them apart.'
        );
    }

    final public function testNoMatchIsAnEmptyArrayNotAnException(): void
    {
        $query = $this->unmatchedQuery() ?? self::markTestSkipped('No unmatchedQuery() provided.');

        $this->assertSame([], $this->search($query), 'No point found MUST be an empty array (section 5.4).');
    }

    /**
     * @return PickupPoint[]
     */
    private function search(PickupPointQuery $query): array
    {
        $carrier = $this->createCarrier();

        return $this->guarded(static fn (): array => $carrier->getPickupPoints($query));
    }

    private function withLimit(PickupPointQuery $query, int $limit): PickupPointQuery
    {
        return new PickupPointQuery(
            address: $query->address,
            latitude: $query->latitude,
            longitude: $query->longitude,
            countryCode: $query->countryCode,
            weightInKilogram: $query->weightInKilogram,
            shippingDate: $query->shippingDate,
            radiusInKilometer: $query->radiusInKilometer,
            limit: $limit,
            types: $query->types,
            serviceCode: $query->serviceCode,
            metadata: $query->metadata
        );
    }
}
