# Universal Carrier — Core

A strictly typed, dependency-free **specification** for shipping carrier integrations in PHP.

Every carrier API models the same handful of ideas — an address, a parcel, a rate, a pickup
point, a label — and every one of them models it differently. This package defines those ideas
once, so that an application can talk to Colissimo, Mondial Relay, DHL or GLS through a single
contract, and swap one for another without touching anything but a service definition.

It contains **no networking code and no runtime dependency beyond PHP itself**. It is the
common vocabulary; the drivers do the talking.

> 📄 The normative contract lives in [`docs/specification.md`](docs/specification.md). This
> README is the guided tour.

## Installation

```bash
composer require universal-carrier/core
```

Requires **PHP 8.4+**. This is the abstraction core. You also need a driver — for example
`universal-carrier/colissimo` — to actually reach a carrier.

## Design principles

- **Everything is validated and normalised at construction.** A DTO that exists is a DTO that
  is valid, down to the elements of its collections. PHP has no generics, so a `Parcel[]`
  annotation is enforced at runtime rather than merely documented. Strings are trimmed, codes
  are case-normalised, and a blank optional field becomes `null`.
- **Immutable by default.** Every DTO is `final readonly`.
- **One canonical unit per quantity.** Kilograms, centimetres, minor currency units, ISO codes.
  No driver may reinterpret them.
- **One interface per operation.** A driver implements what its carrier offers and nothing
  else — no method that exists only to throw. And a driver handed data it cannot honour
  (insurance, customs) must refuse rather than silently drop it.
- **Carrier-specific data has an explicit door.** One `$metadata` in both directions: what the
  consumer sends on a request, what the driver publishes on a rate, a pickup point, a booking
  or a tracking answer. Oddities never leak into the common shape.

## Quick start

```php
use UniversalCarrier\Core\Dto\{Address, Parcel, Rate, RateRequest, ShipmentRequest};
use UniversalCarrier\Core\Exception\{
    ShipmentExceptionInterface,
    ConnectionExceptionInterface,
    CarrierExceptionInterface
};

/** @var \UniversalCarrier\Core\Contract\RateProviderInterface&\UniversalCarrier\Core\Contract\ShipmentCreatorInterface $carrier */

$sender = new Address(
    address1: '51 avenue des Champs-Élysées',
    postalCode: '75008',
    city: 'Paris',
    countryCode: 'FR',
    company: 'Acme SAS',
    phone: '+33123456789',
);

$recipient = new Address(
    address1: '456 avenue de l\'Opéra',
    postalCode: '75001',
    city: 'Paris',
    countryCode: 'FR',
    firstName: 'John',
    lastName: 'Doe',
    email: 'john@example.com',
);

$parcel = new Parcel(
    weightInKilogram: 1.45,
    length: 30.0, // centimetres, optional
    width: 20.0,
    height: 10.0,
);

try {
    // 1. Ask what is available and how much it costs.
    $rates = $carrier->getRates(new RateRequest($sender, $recipient, [$parcel]));

    // Rates are guaranteed to be sorted cheapest first. Keep home delivery here: a service
    // with requiresPickupPoint = true cannot be booked without a pickup point id.
    $homeDelivery = array_filter($rates, fn (Rate $rate) => !$rate->requiresPickupPoint);
    $chosen = reset($homeDelivery) ?: throw new \RuntimeException('No shipping option available.');

    // 2. Book the option the customer picked.
    $response = $carrier->createShipment(new ShipmentRequest(
        serviceCode: $chosen->serviceCode,
        sender: $sender,
        recipient: $recipient,
        parcels: [$parcel],
        orderReference: 'ORD-1001',
    ));

    // 3. Print and ship.
    $package = $response->getFirstPackage();
    $label = $package->label;

    echo 'Tracking number: ' . $package->trackingNumber;
    file_put_contents($label->getSuggestedFilename($package->trackingNumber), $label->content);

} catch (ShipmentExceptionInterface $e) {
    // The request itself is wrong: bad address, weight over the limit, unknown service.
    // Retrying will fail the same way — tell the customer.
} catch (ConnectionExceptionInterface $e) {
    // The carrier could not be reached. Retry later, or fall back to another carrier.
} catch (CarrierExceptionInterface $e) {
    // Catch-all for anything else this package can throw.
}
```

## The contract

One interface per operation, all sharing the carrier's identity:

```php
namespace UniversalCarrier\Core\Contract;

interface CarrierInterface
{
    public function getCode(): string;                    // 'colissimo'
    public function getName(): string;                    // 'Colissimo'
}

interface RateProviderInterface extends CarrierInterface
{
    /** @return Rate[] sorted by ascending price */
    public function getRates(RateRequest $request): array;
}

interface PickupPointProviderInterface extends CarrierInterface
{
    /** @return PickupPoint[] sorted by ascending distance */
    public function getPickupPoints(PickupPointQuery $query): array;
}

interface ShipmentCreatorInterface extends CarrierInterface
{
    public function supports(ShipmentOption $option): bool;
    public function createShipment(ShipmentRequest $request): ShipmentResponse;
}

interface TrackingProviderInterface extends CarrierInterface
{
    public function track(string $trackingNumber): Tracking;
}
```

A driver implements the ones its carrier offers — all four for Colissimo, perhaps only
`RateProviderInterface` for a price comparison API. Code that only quotes asks for a
`RateProviderInterface`, and says so in its signature.

Rating and booking take **different request objects on purpose**: a quote is asked before a
service has been chosen, so `RateRequest` does not ask for a service code, an order reference
or a label language. `ShipmentRequest::fromRate()` bridges the two once the customer decides.

Both describe the same physical movement, though, so both implement `ShipmentDataInterface` —
`sender`, `recipient`, `parcels`, `shippingDate`, `pickupPointId`, `metadata`, and the
accessors derived from them. A driver types its shared mapping helpers on it and writes them
once:

```php
use UniversalCarrier\Core\Contract\ShipmentDataInterface;

private function buildAddressBlock(ShipmentDataInterface $data): array
{
    return [
        'from'   => $this->mapAddress($data->sender),
        'to'     => $this->mapAddress($data->recipient),
        'weight' => $data->getTotalWeightInKilogram(),
        'boxes'  => $data->getParcelCount(),
    ];
}
```

### Capability discovery

A carrier that does not do pickup points has no `getPickupPoints()` method to call, so there is
nothing to catch. The type system answers, and PHPStan narrows after the check:

```php
use UniversalCarrier\Core\Contract\PickupPointProviderInterface;

if ($carrier instanceof PickupPointProviderInterface) {
    $points = $carrier->getPickupPoints($query);
}
```

What a booking may contain is a finer question, answered by `supports()` on the instance — the
answer can depend on the customer's contract with the carrier, not only on the driver:

```php
use UniversalCarrier\Core\Enum\ShipmentOption;

$carrier->supports(ShipmentOption::MULTI_PARCEL);          // several boxes in one booking
$carrier->supports(ShipmentOption::INSURANCE);             // a declared insurance value
$carrier->supports(ShipmentOption::CUSTOMS_DECLARATION);   // itemised content abroad
```

The specification requires `supports()` to agree with `createShipment()`, in both directions:
an unsupported option is refused with `UnsupportedOptionExceptionInterface`, never silently
dropped.

## Multi-parcel shipments

A shipment of three boxes gets three tracking numbers and three labels at every carrier that
supports it, so the response is parcel-oriented rather than a single flat pair:

```php
$response = $carrier->createShipment(new ShipmentRequest(
    serviceCode: 'STANDARD',
    sender: $sender,
    recipient: $recipient,
    parcels: [$box1, $box2, $box3],
    orderReference: 'ORD-1001',
));

$response->shipmentId;            // the carrier's id for the shipment as a whole
$response->getTrackingNumbers();  // one per parcel, in request order
$response->isMultiParcel();       // true

foreach ($response->packages as $package) {
    $label = $package->label;

    file_put_contents(
        $label->getSuggestedFilename($package->trackingNumber), // "SHP-001-01.zpl"
        $label->content                                          // always raw, never base64
    );
}
```

`getSuggestedFilename()` sanitises its argument down to `A-Za-z0-9._-`, so a tracking number
coming back from a carrier API can never turn into a path.

A label is always the file itself. Carriers that answer with a download link are the driver's
problem, not yours: it fetches the label before returning, so the code above is the same
everywhere. In exchange, the file is handed over once — store it if you may need to print it
again, since a carrier link, where one exists at all, is usually deleted after a while.

Single-parcel shipments are just the one-entry case, and `getFirstPackage()` keeps them short.

Label **format**, **size** and **DPI** are separate: they are orthogonal, and a ZPL program
printed at the wrong resolution comes out at the wrong scale.

```php
$label->format;  // LabelFormat::ZPL
$label->size;    // LabelSize::ROLL_100X150
$label->dpi;     // 203
```

## Pickup points and opening hours

Opening hours are the part carriers model most inconsistently. This package normalises them
into at most one `BusinessDay` per weekday, each holding sorted, non-overlapping `TimeSlot`s.

```php
use UniversalCarrier\Core\Dto\PickupPointQuery;
use UniversalCarrier\Core\Enum\PickupPointType;

$points = $carrier->getPickupPoints(new PickupPointQuery(
    address: $recipient,
    weightInKilogram: 1.45,
    radiusInKilometer: 5.0,
    limit: 10,
    types: [PickupPointType::SHOP, PickupPointType::LOCKER],
));

// A "near me" button gives coordinates instead. They do not say which country to search in,
// and most carriers search one country at a time, so it is given explicitly.
$nearMe = PickupPointQuery::forCoordinates(48.8566, 2.3522, countryCode: 'FR');

foreach ($points as $point) {
    echo $point->name . ' — ' . $point->distanceInKilometer . ' km';

    foreach ($point->openingHours as $businessDay) {
        echo $businessDay->day->name . ': ';                 // "MONDAY"

        if ($businessDay->isClosed()) {
            echo "closed\n";
            continue;
        }

        foreach ($businessDay->timeSlots as $slot) {
            echo "{$slot->open}-{$slot->close} ";            // "08:30-12:00 14:00-19:00"
        }
    }
}
```

`isOpenAt()` returns `?bool`, and the `null` matters:

```php
match ($point->isOpenAt(new \DateTimeImmutable('2026-09-07 10:00'))) {
    true  => 'Open',
    false => 'Closed',
    null  => 'Opening hours unknown',   // NOT the same as closed
};
```

Showing "closed" for a shop whose hours are simply unpublished costs the customer a delivery
option they could have used, so the two answers are kept distinct.

Opening hours are wall-clock times **local to the shop**. When the driver populates
`$point->timezone`, any instant can be passed and it is converted for you:

```php
$point->timezone;      // 'America/New_York'
$point->hasTimezone(); // true

// 15:00 UTC is 11:00 in New York — open.
$point->isOpenAt(new \DateTimeImmutable('2026-09-07 15:00', new \DateTimeZone('UTC')));  // true
```

Without a time zone the moment is used as it stands, and it is on you to express it in the
shop's local time — a silent source of wrong answers for foreign locations, which is why
drivers *should* populate it.

## Tracking

Every carrier has its own event codes. They all land on nine statuses, with the carrier's own
wording kept beside them — the status is for your code, the description for your customer:

| Status | Meaning |
|---|---|
| `pending` | Label created, not yet handed to the carrier |
| `picked_up` | Taken by the carrier |
| `in_transit` | Moving through the network |
| `out_for_delivery` | On its way to the recipient |
| `delivery_attempted` | Delivery failed, recipient absent |
| `available_for_pickup` | Waiting at a pickup point |
| `delivered` | Delivered — final |
| `exception` | An incident needs attention |
| `returned` | Going back to the sender — final |

```php
use UniversalCarrier\Core\Contract\TrackingProviderInterface;
use UniversalCarrier\Core\Enum\TrackingStatus;

if ($carrier instanceof TrackingProviderInterface) {
    $tracking = $carrier->track('6D0000000002');

    $tracking->status;              // TrackingStatus::AVAILABLE_FOR_PICKUP
    $tracking->description;         // "Votre colis est disponible dans votre point relais"
    $tracking->estimatedDelivery;   // ?DateTimeImmutable
    $tracking->isFinal();           // false: keep polling

    foreach ($tracking->checkpoints as $checkpoint) {   // oldest first, always
        echo $checkpoint->at->format(DATE_ATOM) . ' ' . $checkpoint->status->value . ' ' . $checkpoint->description;
    }
}
```

Only the number and the status are guaranteed; the description, location, estimated delivery
and history are there whenever the carrier provides them. A well-formed number the carrier has
not scanned yet is `pending`, not an error. The specification (section 5.6) writes down how the
ambiguous cases are mapped — a customs hold, a return, a failed delivery attempt — so that two
drivers describe the same situation the same way. An event a driver cannot read keeps the
status of the one before it: carriers add wordings faster than drivers learn them, and an
unknown wording is almost never an incident.

## Carrier-specific data

Anything one carrier does and the others do not travels in `$metadata`, the single escape
hatch, which works in both directions:

```php
// You to the driver: options only that carrier understands.
$carrier->getRates(new RateRequest($sender, $recipient, [$parcel], metadata: [
    'dhl.account' => '123456789',
]));

// The driver to you: what it answered that the contract does not model.
$response->metadata['mondial_relay.label_url'];   // string
$tracking->metadata['mondial_relay.service'];     // "Livraison au point relais"
```

A driver ignores keys it does not know rather than failing, and namespaces the keys it reads
and publishes with its own `getCode()`. Values are `mixed`, so a static analyser makes you
narrow before reading — a reminder that anything you take out of here is tied to one carrier.

There is no field holding the carrier's whole untouched response: a driver publishes the three
or four values worth exposing, and keeps the rest for its logs.

## Money

Amounts are integers in the **minor unit** of their currency, and the conversion respects the
ISO 4217 exponent — JPY has no decimals, KWD has three. A hard-coded division by 100 is wrong
for about thirty currencies.

```php
use UniversalCarrier\Core\Dto\Price;

$price = new Price(1490, 'EUR');
$price->toDecimalString();   // "14.90"

(new Price(1490, 'JPY'))->toDecimalString();   // "1490"
(new Price(1490, 'KWD'))->toDecimalString();   // "1.490"

$price->equals(new Price(1490, 'eur'));        // true
$price->isZero();                              // false
```

A `Price` is written once and never recomputed: the object offers comparison and rendering,
and **no arithmetic**. Shipping does not compute on money — a carrier quotes a rate, a shipper
declares a customs value, and a discount granted to the customer never reaches the carrier.
For the same reason a `Parcel` carries no shipping price: what a shipment costs is a carrier
output, and several carriers reject a declared cost of zero.

## Error handling

Every error is catchable through one root interface, and drivers implement the **interfaces**
rather than extend the shipped classes — so a driver keeps its own exception hierarchy.

```
CarrierExceptionInterface  (extends Throwable)
├── InvalidArgumentExceptionInterface     — a DTO invariant was violated
├── ShipmentExceptionInterface            — the carrier rejected the request content
├── ConnectionExceptionInterface          — the carrier could not be reached
└── UnsupportedOptionExceptionInterface   — the driver does not honour this shipment option
```

The split between `ShipmentExceptionInterface` and `ConnectionExceptionInterface` is about
**retryability**: the first will fail again with the same input, the second may succeed later.
That is what lets a checkout decide between "tell the customer" and "retry or fall back".

Validation errors are part of the hierarchy too — `InvalidArgumentException` extends the SPL
class *and* implements the root interface, so a malformed `Address` never escapes a
`catch (CarrierExceptionInterface $e)` block.

## Writing a driver

1. Implement the capability interfaces your carrier offers — `RateProviderInterface`,
   `PickupPointProviderInterface`, `ShipmentCreatorInterface`, `TrackingProviderInterface` —
   and none it does not. Do not
   implement `ShipmentDataInterface`, and do not extend the DTOs: both are supplied by this
   package.
2. Read [`docs/specification.md`](docs/specification.md), in particular section 5 (the `MUST`
   rules) and section 8 (conformance).
3. Never let a foreign exception escape a contract method — wrap it, passing the original as
   `$previous`.
4. Read [`demo/`](demo) for a complete driver written the way a real one would be — request
   and response mapping, error translation, configuration-dependent options — against a
   simulated carrier API. Run it with `composer demo`.
5. Run the conformance suite, [`universal-carrier/conformance`](conformance): one abstract
   PHPUnit test case per capability, which you extend with your sandbox configuration and a
   few shipments your carrier can book.
6. Use [`tests/Fixture/InMemoryCarrier.php`](tests/Fixture/InMemoryCarrier.php) as a reference:
   it is a complete, network-free driver that passes the conformance suite, as does the demo
   driver — see [`tests/Conformance`](tests/Conformance).

## Not covered yet

Deliberately out of scope for this revision, and specified before being added:

- Shipment cancellation.
- Return labels, manifests / end-of-day closeout, pickup scheduling.
- Customs documents, incoterms, EORI and VAT identifiers.
- Batch tracking, for carriers accepting several numbers per call.

Each new operation will arrive as a new interface, so adding it breaks no existing driver. See
section 10 of the specification.

## Development

```bash
composer install
composer check      # PHPStan (level max) + PHPUnit
```

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
