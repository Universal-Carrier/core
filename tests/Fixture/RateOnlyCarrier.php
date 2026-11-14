<?php declare(strict_types=1);

namespace Tests\Fixture;

use UniversalCarrier\Core\Contract\RateProviderInterface;
use UniversalCarrier\Core\Dto\Price;
use UniversalCarrier\Core\Dto\Rate;
use UniversalCarrier\Core\Dto\RateRequest;

/**
 * A partial driver: it quotes, and nothing else.
 *
 * Price comparison services and rate-only APIs look like this. It implements the one
 * capability it has and no other, which is the whole point of splitting the contract.
 */
final class RateOnlyCarrier implements RateProviderInterface
{

    public function getCode(): string
    {
        return 'rate_only';
    }

    public function getName(): string
    {
        return 'Rate Only Carrier';
    }

    public function getRates(RateRequest $request): array
    {
        return [new Rate('FLAT', 'Flat rate', new Price(990))];
    }
}
