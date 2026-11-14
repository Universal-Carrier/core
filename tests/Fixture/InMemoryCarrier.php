<?php declare(strict_types=1);

namespace Tests\Fixture;

use DateTimeImmutable;
use UniversalCarrier\Core\Contract\PickupPointProviderInterface;
use UniversalCarrier\Core\Contract\RateProviderInterface;
use UniversalCarrier\Core\Contract\ShipmentCreatorInterface;
use UniversalCarrier\Core\Contract\TrackingProviderInterface;
use UniversalCarrier\Core\Dto\Label;
use UniversalCarrier\Core\Dto\PickupPoint;
use UniversalCarrier\Core\Dto\PickupPointQuery;
use UniversalCarrier\Core\Dto\Price;
use UniversalCarrier\Core\Dto\Rate;
use UniversalCarrier\Core\Dto\RateRequest;
use UniversalCarrier\Core\Dto\ShipmentPackage;
use UniversalCarrier\Core\Dto\ShipmentRequest;
use UniversalCarrier\Core\Dto\ShipmentResponse;
use UniversalCarrier\Core\Dto\Tracking;
use UniversalCarrier\Core\Dto\TrackingCheckpoint;
use UniversalCarrier\Core\Enum\LabelFormat;
use UniversalCarrier\Core\Enum\LabelSize;
use UniversalCarrier\Core\Enum\ShipmentOption;
use UniversalCarrier\Core\Enum\TrackingStatus;
use UniversalCarrier\Core\Exception\ShipmentException;
use UniversalCarrier\Core\Exception\UnsupportedOptionException;

/**
 * A minimal, network-free driver used to exercise the contract.
 *
 * It is not a mock: it honours every MUST of the specification, and therefore doubles as the
 * reference for what a conforming implementation has to do.
 */
final class InMemoryCarrier implements
    RateProviderInterface,
    PickupPointProviderInterface,
    ShipmentCreatorInterface,
    TrackingProviderInterface
{

    /** @var PickupPoint[] */
    private array $pickupPoints;

    private int $shipmentCounter = 0;

    /** @var array<string, DateTimeImmutable> When each label was issued, by tracking number. */
    private array $issuedAt = [];

    /**
     * @param ShipmentOption[] $supportedOptions
     * @param PickupPoint[] $pickupPoints
     */
    public function __construct(
        private readonly array $supportedOptions = [
            ShipmentOption::MULTI_PARCEL,
            ShipmentOption::CUSTOMS_DECLARATION,
            ShipmentOption::INSURANCE,
        ],
        array $pickupPoints = []
    ) {
        $this->pickupPoints = $pickupPoints;
    }

    public function getCode(): string
    {
        return 'in_memory';
    }

    public function getName(): string
    {
        return 'In Memory Carrier';
    }

    public function supports(ShipmentOption $option): bool
    {
        return in_array($option, $this->supportedOptions, true);
    }

    public function getRates(RateRequest $request): array
    {
        // Heavier than any service accepts: nothing to offer, which is not an error.
        if ($request->getTotalWeightInKilogram() > 30.0) {
            return [];
        }

        $weight = $request->getTotalWeightInKilogram();

        $rates = [
            new Rate('EXPRESS', 'Express 24h', new Price((int)round(1290 + $weight * 100))),
            new Rate('RELAY', 'Pickup point', new Price((int)round(390 + $weight * 50)), requiresPickupPoint: true),
            new Rate('STD', 'Standard', new Price((int)round(590 + $weight * 80)), transitTimeInDays: 3),
        ];

        usort($rates, static fn (Rate $a, Rate $b): int => $a->price->amount <=> $b->price->amount);

        return $rates;
    }

    public function getPickupPoints(PickupPointQuery $query): array
    {
        $points = array_values(array_filter(
            $this->pickupPoints,
            static fn (PickupPoint $point): bool => $query->types === [] || in_array($point->type, $query->types, true)
        ));

        usort($points, static fn (PickupPoint $a, PickupPoint $b): int => ($a->distanceInKilometer ?? PHP_FLOAT_MAX)
            <=> ($b->distanceInKilometer ?? PHP_FLOAT_MAX));

        return $query->limit === null ? $points : array_slice($points, 0, $query->limit);
    }

    public function createShipment(ShipmentRequest $request): ShipmentResponse
    {
        if ($request->isMultiParcel()) {
            $this->guard(ShipmentOption::MULTI_PARCEL);
        }

        foreach ($request->parcels as $parcel) {
            if ($parcel->insuranceValue !== null) {
                $this->guard(ShipmentOption::INSURANCE);
            }

            if ($parcel->items !== [] && $this->requiresCustomsDeclaration($request)) {
                $this->guard(ShipmentOption::CUSTOMS_DECLARATION);
            }
        }

        if ($request->serviceCode === 'RELAY' && !$request->isPickupPointDelivery()) {
            throw new ShipmentException('Service "RELAY" requires a pickup point identifier.');
        }

        if ($request->serviceCode !== 'RELAY' && $request->isPickupPointDelivery()) {
            throw new ShipmentException(sprintf('Service "%s" is a home delivery service.', $request->serviceCode));
        }

        $shipmentId = sprintf('SHP-%03d', ++$this->shipmentCounter);
        $packages = [];

        foreach ($request->parcels as $index => $parcel) {
            $trackingNumber = sprintf('%s-%02d', $shipmentId, $index + 1);
            $this->issuedAt[$trackingNumber] = new DateTimeImmutable();

            $packages[] = new ShipmentPackage(
                trackingNumber: $trackingNumber,
                label: new Label(sprintf('^XA^FD%s^FS^XZ', $trackingNumber), LabelFormat::ZPL, LabelSize::ROLL_100X150, 203),
                trackingUrl: sprintf('https://track.example.com/%s', $trackingNumber),
                parcelReference: $parcel->reference
            );
        }

        return new ShipmentResponse($shipmentId, $packages);
    }

    public function track(string $trackingNumber): Tracking
    {
        if (preg_match('/^SHP-\d{3}-\d{2}$/', $trackingNumber) !== 1) {
            throw new ShipmentException(sprintf('"%s" is not an In Memory Carrier tracking number.', $trackingNumber));
        }

        $issuedAt = $this->issuedAt[$trackingNumber] ?? null;

        // A well-formed number the carrier has never seen is pending, not an error: the label
        // may simply not have been scanned yet.
        return new Tracking(
            $trackingNumber,
            TrackingStatus::PENDING,
            checkpoints: $issuedAt === null ? [] : [new TrackingCheckpoint(TrackingStatus::PENDING, $issuedAt, 'Label created')]
        );
    }

    /**
     * This fixture treats every change of country as a customs border. A real driver applies
     * its carrier's own rules, which rarely come down to that.
     */
    private function requiresCustomsDeclaration(ShipmentRequest $request): bool
    {
        return $request->sender->countryCode !== $request->recipient->countryCode;
    }

    private function guard(ShipmentOption $option): void
    {
        if (!$this->supports($option)) {
            throw UnsupportedOptionException::forOption($option, $this->getName());
        }
    }
}
