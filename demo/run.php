<?php declare(strict_types=1);

/**
 * Walks through every operation of the contract against the demo driver.
 *
 *     composer demo
 *
 * The consumer code below only ever talks to the package's interfaces and DTOs; the driver
 * is chosen in one place, as it would be in a service container.
 */

require __DIR__ . '/../vendor/autoload.php';

use Demo\Api\DemoApiClient;
use Demo\DemoCarrier;
use UniversalCarrier\Core\Contract\{CarrierInterface,
    PickupPointProviderInterface,
    RateProviderInterface,
    ShipmentCreatorInterface,
    TrackingProviderInterface};
use UniversalCarrier\Core\Dto\{Address, Parcel, ParcelItem, PickupPointQuery, Price, RateRequest, ShipmentRequest};
use UniversalCarrier\Core\Enum\{ExportReason, LabelFormat, PickupPointType, ShipmentOption};
use UniversalCarrier\Core\Exception\{CarrierExceptionInterface,
    ConnectionExceptionInterface,
    ShipmentExceptionInterface,
    UnsupportedOptionExceptionInterface};

function section(string $title): void
{
    echo "\n\033[1m== {$title} ==\033[0m\n";
}

/**
 * Consumer code sees a CarrierInterface and discovers the rest through the type system.
 */
function describeCapabilities(CarrierInterface $carrier): void
{
    printf("%s (%s)\n", $carrier->getName(), $carrier->getCode());
    printf("  quotes rates:          %s\n", $carrier instanceof RateProviderInterface ? 'yes' : 'no');
    printf("  lists pickup points:   %s\n", $carrier instanceof PickupPointProviderInterface ? 'yes' : 'no');
    printf("  books shipments:       %s\n", $carrier instanceof ShipmentCreatorInterface ? 'yes' : 'no');
    printf("  tracks parcels:        %s\n", $carrier instanceof TrackingProviderInterface ? 'yes' : 'no');
}

function describe(CarrierExceptionInterface $e): string
{
    return sprintf('%s: %s', (new ReflectionClass($e))->getShortName(), $e->getMessage());
}

// --- Configuration: the only place the concrete driver appears -----------------------------

$api = new DemoApiClient();
$carrier = new DemoCarrier($api, accountNumber: '800123', password: 'demo');
$insuredCarrier = new DemoCarrier($api, accountNumber: '800123', password: 'demo', insuranceContract: true, labelFormat: LabelFormat::ZPL);

// --- The shipment ---------------------------------------------------------------------------

$shippingDate = new DateTimeImmutable('2026-09-21');

$shop = new Address(
    address1: '51 avenue des Champs-Élysées',
    postalCode: '75008',
    city: 'Paris',
    countryCode: 'FR',
    company: 'Acme SAS',
    phone: '+33123456789',
);

$customer = new Address(
    address1: '12 rue de Rivoli',
    postalCode: '75004',
    city: 'Paris',
    countryCode: 'fr',
    firstName: '  Jeanne ',
    lastName: 'Martin',
    email: 'jeanne.martin@example.com',
);

$parcel = new Parcel(weightInKilogram: 1.45, length: 30.0, width: 20.0, height: 10.0, reference: 'BOX-1');

// --- 1. What can this carrier do? -----------------------------------------------------------

section('1. Capabilities');

describeCapabilities($carrier);

foreach (ShipmentOption::cases() as $option) {
    printf(
        "  option %-20s standard contract: %-3s  insured contract: %s\n",
        $option->value,
        $carrier->supports($option) ? 'yes' : 'no',
        $insuredCarrier->supports($option) ? 'yes' : 'no'
    );
}

// --- 2. Rates -------------------------------------------------------------------------------

section('2. Rates, Paris -> Paris');

$rateRequest = new RateRequest($shop, $customer, [$parcel], $shippingDate);
$rates = $carrier->getRates($rateRequest);

foreach ($rates as $rate) {
    printf(
        "  %-15s %-24s %6s %s  %d day(s), by %s%s\n",
        $rate->serviceCode,
        $rate->serviceName,
        $rate->price->toDecimalString(),
        $rate->price->currency,
        $rate->transitTimeInDays,
        $rate->getDeliveryDate()?->format('D d M') ?? '?',
        $rate->requiresPickupPoint ? '  [pickup point]' : ''
    );
}

// Asking is always allowed: a carrier that cannot carry the parcel answers with no rate.
$heavy = new RateRequest($shop, $customer, [new Parcel(60.0)], $shippingDate);
$tokyo = new Address('1-1 Marunouchi', '100-0005', 'Tokyo', 'JP', company: 'Tokyo Office');

printf("  60 kg parcel:           %d rate(s)\n", count($carrier->getRates($heavy)));
printf("  destination not served: %d rate(s)\n", count($carrier->getRates(new RateRequest($shop, $tokyo, [$parcel]))));

// --- 3. Pickup points -----------------------------------------------------------------------

section('3. Pickup points near the customer, shops and lockers, 3 max');

$points = $carrier->getPickupPoints(new PickupPointQuery(
    address: $customer,
    weightInKilogram: $parcel->weightInKilogram,
    limit: 3,
    types: [PickupPointType::SHOP, PickupPointType::LOCKER],
));

$tuesdayMorning = new DateTimeImmutable('2026-09-22 08:15', new DateTimeZone('UTC'));

foreach ($points as $point) {
    $open = match ($point->isOpenAt($tuesdayMorning)) {
        true => 'open',
        false => 'closed',
        null => 'hours unknown',
    };

    printf(
        "  %-10s %-24s %-7s %.2f km  %s  (Tue 08:15 UTC: %s)\n",
        $point->id,
        $point->name,
        $point->type->name,
        $point->distanceInKilometer,
        $point->timezone ?? 'no time zone',
        $open
    );

    foreach ($point->openingHours as $day) {
        $slots = array_map(static fn ($slot): string => "{$slot->open}-{$slot->close}", $day->timeSlots);
        printf("      %-9s %s\n", $day->day->name, $slots === [] ? 'closed' : implode(' ', $slots));
    }
}

// --- 4. Book the cheapest option ------------------------------------------------------------

section('4. Book the cheapest rate');

$cheapest = $rates[0] ?? throw new RuntimeException('No rate.');
$pickupPointId = $cheapest->requiresPickupPoint ? ($points[0]->id ?? null) : null;

$response = $carrier->createShipment(ShipmentRequest::fromRate($cheapest, $rateRequest, 'ORD-1001', $pickupPointId));
$package = $response->getFirstPackage();
$bookedTrackingNumber = $package->trackingNumber;

printf("  service     %s, pickup point %s\n", $cheapest->serviceCode, $pickupPointId ?? '-');
printf("  shipment    %s, cost %s %s\n", $response->shipmentId, $response->cost?->toDecimalString(), $response->cost?->currency);
printf("  tracking    %s  %s\n", $package->trackingNumber, $package->trackingUrl);
printf(
    "  label       %s, %d bytes, %s -> %s\n",
    $package->label->format->getMediaType(),
    $package->label->getSizeInBytes(),
    $package->label->size->name,
    $package->label->getSuggestedFilename($package->trackingNumber)
);

// What the carrier said that the contract does not model. The driver chose these keys and
// documents them; their values are `mixed`, so they are narrowed before being read.
foreach ($response->metadata as $key => $value) {
    printf("  %-11s %s\n", $key, is_scalar($value) ? (string)$value : get_debug_type($value));
}

// --- 5. International, multi-parcel, insured, ZPL ------------------------------------------

section('5. Two insured boxes to New York, with customs, on a ZPL printer');

$newYork = new Address(
    address1: '350 5th Avenue',
    postalCode: '10118',
    city: 'New York',
    countryCode: 'US',
    firstName: 'John',
    lastName: 'Smith',
    state: 'NY',
    phone: '+12125550100',
);

$tshirts = new ParcelItem('Cotton t-shirt', 3, 0.2, new Price(1990), hsTariffCode: '610910', originCountry: 'PT');
$book = new ParcelItem('Hardcover book', 1, 0.9, new Price(3500), hsTariffCode: '490199', originCountry: 'FR');

$international = new ShipmentRequest(
    serviceCode: 'HOME',
    sender: $shop,
    recipient: $newYork,
    parcels: [
        new Parcel(0.8, items: [$tshirts], insuranceValue: new Price(5970), reference: 'BOX-1'),
        new Parcel(1.1, items: [$book], insuranceValue: new Price(3500), reference: 'BOX-2'),
    ],
    orderReference: 'ORD-1002',
    shippingDate: $shippingDate,
    exportReason: ExportReason::SALE_OF_GOODS,
);

$response = $insuredCarrier->createShipment($international);

foreach ($response->packages as $package) {
    printf(
        "  %s  %s  %s  %s\n",
        $package->parcelReference,
        $package->trackingNumber,
        $package->label->getSuggestedFilename($package->trackingNumber),
        $package->label->content
    );
}

// --- 6. Tracking ----------------------------------------------------------------------------

section('6. Tracking');

$tracked = [
    'just booked' => $bookedTrackingNumber,
    'delivered' => '6D0000000001',
    'failed attempt' => '6D0000000002',
    'returned' => '6D0000000003',
    'never scanned' => '6D9999999999',
];

foreach ($tracked as $label => $trackingNumber) {
    $tracking = $carrier->track($trackingNumber);

    printf(
        "  %-15s %s  %-21s %s%s\n",
        $label,
        $tracking->trackingNumber,
        $tracking->status->value,
        $tracking->isFinal() ? 'final' : 'ETA ' . ($tracking->estimatedDelivery?->format('D d M') ?? '?'),
        $tracking->description === null ? '' : sprintf('  "%s"', $tracking->description)
    );
}

echo "\n  History of 6D0000000002, oldest first, in UTC:\n";

foreach ($carrier->track('6D0000000002')->checkpoints as $checkpoint) {
    printf(
        "    %s  %-21s %-20s %s\n",
        $checkpoint->at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i\Z'),
        $checkpoint->status->value,
        $checkpoint->location ?? '',
        $checkpoint->description
    );
}

// --- 7. What goes wrong, and how the consumer tells the cases apart -------------------------

section('7. Errors');

$attempts = [
    'insurance without the contract' => static fn () => $carrier->createShipment($international),
    'relay service, no pickup point' => static fn () => $carrier->createShipment(
        new ShipmentRequest('RELAY', $shop, $customer, [$parcel], 'ORD-1003')
    ),
    'postal code the carrier rejects' => static fn () => $carrier->getRates(new RateRequest(
        $shop,
        new Address('1 rue Inconnue', '00000', 'Nowhere', 'FR', company: 'Ghost Ltd'),
        [$parcel]
    )),
    'FR -> Guadeloupe, no items' => static fn () => $carrier->createShipment(new ShipmentRequest(
        'HOME',
        $shop,
        new Address('Rue Frébault', '97110', 'Pointe-à-Pitre', 'FR', firstName: 'Marie', lastName: 'Laurent'),
        [$parcel],
        'ORD-1005'
    )),
    'tracking a foreign number' => static fn () => $carrier->track('XY618254390FR'),
    'booking a parcel over the limit' => static fn () => $carrier->createShipment(
        new ShipmentRequest('HOME', $shop, $customer, [new Parcel(32.0)], 'ORD-1004')
    ),
    'carrier down for maintenance' => static fn () => (new DemoCarrier(new DemoApiClient(503), '800123', 'demo'))
        ->getRates($rateRequest),
    'network timeout' => static fn () => (new DemoCarrier(new DemoApiClient(0), '800123', 'demo'))
        ->getRates($rateRequest),
    'wrong password' => static fn () => (new DemoCarrier($api, '800123', 'oops'))->getRates($rateRequest),
];

foreach ($attempts as $label => $attempt) {
    try {
        $attempt();
        printf("  %-32s no error?!\n", $label);
    } catch (UnsupportedOptionExceptionInterface $e) {
        printf("  %-32s -> refuse up front   %s\n", $label, describe($e));
    } catch (ShipmentExceptionInterface $e) {
        printf("  %-32s -> tell the customer %s\n", $label, describe($e));
    } catch (ConnectionExceptionInterface $e) {
        printf("  %-32s -> retry or fall back %s\n", $label, describe($e));
    } catch (CarrierExceptionInterface $e) {
        printf("  %-32s -> unexpected        %s\n", $label, describe($e));
    }
}

echo "\n";
