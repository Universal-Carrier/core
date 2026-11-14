<?php declare(strict_types=1);

namespace Demo\Mapping;

use DateTimeImmutable;
use DateTimeZone;
use Demo\Exception\DemoProtocolException;
use Demo\Support\Payload;
use UniversalCarrier\Core\Dto\{BusinessDay,
    Label,
    PickupPoint,
    Price,
    Rate,
    ShipmentPackage,
    ShipmentRequest,
    ShipmentResponse,
    TimeSlot,
    Tracking,
    TrackingCheckpoint};
use UniversalCarrier\Core\Enum\{DayOfWeek, LabelFormat, LabelSize, PickupPointType, TrackingStatus};

/**
 * Translates the carrier's responses into the package's DTOs.
 *
 * Like its counterpart, it is pure. Its other job is to be suspicious: every value is read
 * through {@see Payload}, so a response of the wrong shape fails here with a precise message
 * instead of producing a DTO the consumer would trust.
 */
final readonly class ResponseMapper
{

    private const array WEEKDAYS = [
        'mon' => DayOfWeek::MONDAY,
        'tue' => DayOfWeek::TUESDAY,
        'wed' => DayOfWeek::WEDNESDAY,
        'thu' => DayOfWeek::THURSDAY,
        'fri' => DayOfWeek::FRIDAY,
        'sat' => DayOfWeek::SATURDAY,
        'sun' => DayOfWeek::SUNDAY,
    ];

    /**
     * @return list<Rate> Sorted cheapest first - the carrier does not guarantee any order.
     */
    public function rates(Payload $response): array
    {
        $rates = array_map(
            fn (Payload $offer): Rate => new Rate(
                serviceCode: $offer->string('product'),
                serviceName: $offer->string('label'),
                price: $this->price($offer->object('price')),
                requiresPickupPoint: $offer->bool('relay'),
                transitTimeInDays: $offer->nullableInt('transit_days'),
                earliestDeliveryDate: $this->date($offer->object('delivery')->nullableString('from')),
                latestDeliveryDate: $this->date($offer->object('delivery')->nullableString('to')),
                metadata: ['demo.saturday_delivery' => $offer->bool('saturday')]
            ),
            $response->objects('offers')
        );

        usort($rates, static fn (Rate $a, Rate $b): int => $a->price->amount <=> $b->price->amount);

        return $rates;
    }

    /**
     * @return list<PickupPoint>
     */
    public function pickupPoints(Payload $response): array
    {
        return array_map($this->pickupPoint(...), $response->objects('relays'));
    }

    public function shipment(Payload $response, ShipmentRequest $request): ShipmentResponse
    {
        $shipment = $response->object('shipment');
        [$format, $size, $dpi] = $this->labelSpecification($shipment->string('label_format'));
        $packages = [];

        foreach ($shipment->objects('parcels') as $index => $parcel) {
            $number = $parcel->string('number');

            $packages[] = new ShipmentPackage(
                trackingNumber: $number,
                label: new Label($this->decodeLabel($parcel->string('label')), $format, $size, $dpi),
                trackingUrl: 'https://tracking.demo-carrier.example/' . rawurlencode($number),
                // The carrier does not echo our references back; it answers in request order,
                // which is enough to pair each package with its parcel.
                parcelReference: ($request->parcels[$index] ?? null)?->reference
            );
        }

        // One package per parcel is a MUST of the contract. Better to fail loudly than to hand
        // the consumer three labels for four boxes.
        if (count($packages) !== $request->getParcelCount()) {
            throw new DemoProtocolException(sprintf(
                'Demo Carrier returned %d parcel(s) for a shipment of %d.',
                count($packages),
                $request->getParcelCount()
            ));
        }

        return new ShipmentResponse(
            shipmentId: $shipment->string('id'),
            packages: $packages,
            cost: $response->has('invoice') ? $this->price($response->object('invoice')) : null,
            // Only what the contract does not model, named after the driver: the whole
            // envelope would burden every consumer with a shape it must not rely on.
            metadata: array_filter(
                ['demo.drop_off_before' => $shipment->nullableString('drop_off_before')],
                static fn (?string $value): bool => $value !== null
            )
        );
    }

    /**
     * The carrier lists events newest first, timed in Paris wall-clock time with no offset.
     * The DTO sorts them; attaching the time zone is the driver's job, since only the driver
     * knows how its carrier writes dates.
     */
    public function tracking(Payload $response, string $trackingNumber): Tracking
    {
        $checkpoints = [];
        $previous = null;

        // Oldest first, so that an event the driver cannot read can keep the status of the
        // one before it.
        foreach (array_reverse($response->objects('events')) as $event) {
            $checkpoints[] = new TrackingCheckpoint(
                status: $previous = $this->trackingStatus($event->string('code')) ?? $previous ?? TrackingStatus::PENDING,
                at: new DateTimeImmutable($event->string('date'), new DateTimeZone('Europe/Paris')),
                description: $event->string('label'),
                location: $event->nullableString('site')
            );
        }

        $current = $checkpoints === [] ? null : $checkpoints[array_key_last($checkpoints)];

        return new Tracking(
            trackingNumber: $trackingNumber,
            status: $current->status ?? TrackingStatus::PENDING,
            description: $current?->description,
            location: $current?->location,
            estimatedDelivery: $this->date($response->nullableString('eta')),
            checkpoints: $checkpoints,
            metadata: array_filter(
                ['demo.signed_by' => $response->nullableString('signed_by')],
                static fn (?string $value): bool => $value !== null
            )
        );
    }

    /**
     * Where each of the carrier's event codes lands among the nine statuses.
     *
     * The ambiguous cases follow the specification (section 5.6): customs clearance is transit
     * until it blocks, and a return counts as returned from the moment it is decided.
     *
     * @return TrackingStatus|null Null for a code this driver does not know, which keeps the
     *   status of the event before it rather than being reported as an incident.
     */
    private function trackingStatus(string $code): ?TrackingStatus
    {
        return match ($code) {
            'CRE' => TrackingStatus::PENDING,
            'PCH' => TrackingStatus::PICKED_UP,
            'HUB', 'DOU' => TrackingStatus::IN_TRANSIT,
            'TLV' => TrackingStatus::OUT_FOR_DELIVERY,
            'AVP' => TrackingStatus::DELIVERY_ATTEMPTED,
            'RLY' => TrackingStatus::AVAILABLE_FOR_PICKUP,
            'LIV' => TrackingStatus::DELIVERED,
            'ADR', 'DMG', 'BLQ' => TrackingStatus::EXCEPTION,
            'RET' => TrackingStatus::RETURNED,
            // A code added by the carrier since this table was written. Almost every new code
            // is a routine one, so calling it an incident would alarm the customer for
            // nothing; the carrier's own wording still travels in the description.
            default => null,
        };
    }

    private function pickupPoint(Payload $relay): PickupPoint
    {
        $country = $relay->string('country');
        $timezone = $this->timezoneOf($country);

        return new PickupPoint(
            id: sprintf('%s-%s', $country, $relay->string('code')),
            name: $relay->string('name'),
            type: RequestMapper::RELAY_KINDS[$relay->string('kind')] ?? PickupPointType::OTHER,
            address1: $relay->string('street'),
            postalCode: $relay->string('zip'),
            city: $relay->string('town'),
            countryCode: $country,
            latitude: $relay->decimal('lat'),
            longitude: $relay->decimal('lng'),
            distanceInKilometer: $relay->int('distance_m') / 1000,
            openingHours: $this->openingHours($relay),
            holidays: array_map(
                static fn (string $date): DateTimeImmutable => new DateTimeImmutable($date, new DateTimeZone($timezone ?? 'UTC')),
                $relay->strings('closures')
            ),
            timezone: $timezone,
            metadata: [
                'demo.max_weight_kg' => $relay->int('max_weight_g') / 1000,
                'demo.parking' => $relay->bool('parking'),
            ]
        );
    }

    /**
     * Unpacks "0830120014001930" into 08:30-12:00 and 14:00-19:30.
     *
     * A missing "hours" object means the carrier does not know, which the package represents
     * as no entry at all - not as closed. "0000000000000000" means closed that day.
     *
     * @return list<BusinessDay>
     */
    private function openingHours(Payload $relay): array
    {
        if (!$relay->has('hours')) {
            return [];
        }

        $hours = $relay->object('hours');
        $days = [];

        foreach (self::WEEKDAYS as $key => $day) {
            if (!$hours->has($key)) {
                continue;
            }

            $packed = $hours->string($key);

            if (preg_match('/^\d{16}$/', $packed) !== 1) {
                throw new DemoProtocolException(sprintf('Unreadable opening hours "%s" for %s.', $packed, $key));
            }

            $slots = [];

            foreach (str_split($packed, 8) as $pair) {
                if ($pair !== '00000000') {
                    $slots[] = new TimeSlot($this->time(substr($pair, 0, 4)), $this->time(substr($pair, 4, 4)));
                }
            }

            $days[] = new BusinessDay($day, $slots);
        }

        return $days;
    }

    /**
     * The carrier does not say which time zone a point is in. When the country has a single
     * one, it can be derived without guessing; otherwise it is left unknown, as the
     * specification asks (section 5.4).
     */
    private function timezoneOf(string $countryCode): ?string
    {
        $zones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $countryCode);

        return count($zones) === 1 ? $zones[0] : null;
    }

    /**
     * Maps the carrier's single label code onto the package's three independent axes.
     *
     * @return array{LabelFormat, LabelSize, int|null}
     */
    private function labelSpecification(string $code): array
    {
        return match ($code) {
            'PDF_10x15' => [LabelFormat::PDF, LabelSize::ROLL_100X150, null],
            'ZPL_203' => [LabelFormat::ZPL, LabelSize::ROLL_100X150, 203],
            default => throw new DemoProtocolException(sprintf('Unknown label format "%s".', $code)),
        };
    }

    /**
     * Labels travel in base64; the package wants the raw bytes (specification, section 5.5).
     */
    private function decodeLabel(string $base64): string
    {
        $content = base64_decode($base64, true);

        return $content === false || $content === ''
            ? throw new DemoProtocolException('Demo Carrier returned a label that is not valid base64.')
            : $content;
    }

    private function price(Payload $money): Price
    {
        return Price::fromDecimal($money->decimal('amount'), $money->string('currency'));
    }

    private function date(?string $date): ?DateTimeImmutable
    {
        return $date === null ? null : new DateTimeImmutable($date);
    }

    private function time(string $hhmm): string
    {
        return substr($hhmm, 0, 2) . ':' . substr($hhmm, 2, 2);
    }
}
