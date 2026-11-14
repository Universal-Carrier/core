# Universal Carrier — Conformance

PHPUnit test cases checking a [Universal Carrier](../README.md) driver against the
[specification](../docs/specification.md). A driver that passes them honours every rule of
the specification that can be observed from outside.

```bash
composer require --dev universal-carrier/conformance
```

## Usage

There is one abstract test case per capability, mirroring the interfaces. Extend
`CarrierConformanceTestCase` once, then the test case of every capability your driver
implements, and give each the data only you know: a shipment your carrier can book, a
tracking number it knows.

```php
use UniversalCarrier\Conformance\ShipmentCreatorConformanceTestCase;

final class MondialRelayShipmentConformanceTest extends ShipmentCreatorConformanceTestCase
{
    protected function createCarrier(): ShipmentCreatorInterface
    {
        return new MondialRelayCarrier(/* sandbox credentials */);
    }

    protected function bookableRequest(): ShipmentRequest
    {
        return new ShipmentRequest('24R', $sender, $recipient, [new Parcel(1.2)], 'ORD-1');
    }

    protected function pickupPointRequest(): ?ShipmentRequest   // optional
    {
        return new ShipmentRequest('24R', $sender, $recipient, [new Parcel(1.2)], 'ORD-1', pickupPointId: 'FR-066974');
    }
}
```

| Test case | Required | Optional (null skips the test) |
|---|---|---|
| `CarrierConformanceTestCase` | `createCarrier()` | |
| `RateProviderConformanceTestCase` | `createCarrier()`, `quotableRequest()` | `unquotableRequest()` (defaults to a 1,000 kg parcel), `invalidRequest()` |
| `PickupPointProviderConformanceTestCase` | `createCarrier()`, `searchableQuery()` | `unmatchedQuery()` |
| `ShipmentCreatorConformanceTestCase` | `createCarrier()`, `bookableRequest()` | `pickupPointRequest()`, `customsRequest()`, `insuranceValue()` |
| `TrackingProviderConformanceTestCase` | `createCarrier()`, `trackedNumber()` | `unscannedNumber()`, `malformedNumber()` |

**These tests call the carrier.** Point the driver at its sandbox. `ShipmentCreatorConformanceTestCase`
books real shipments — up to ten per run — each with an order reference of its own, so that
sandboxes rejecting duplicates do not get in the way.

To check both sides of an option that depends on the contract, such as insurance, extend the
test case twice: once with a configuration that supports it, once with one that does not.

## What is checked

Every call goes through a guard: a driver letting a foreign exception escape fails whatever
test it happens in (section 6, rule 1).

| Capability | Rules |
|---|---|
| Identity | snake_case code, displayable name, both stable; at least one capability implemented |
| Rates | sorted cheapest first; one currency; nothing to offer is `[]`; an unassessable request throws `ShipmentExceptionInterface` |
| Pickup points | sorted nearest first; limit honoured; unique ids; no match is `[]` |
| Booking | one package per parcel, in request order, each with its label; labels are raw bytes of their declared format, not base64; `supports()` stable and agreeing with `createShipment()` for multi-parcel, insurance and customs; pickup point / service mismatches refused both ways; every quoted service and listed pickup point can be booked |
| Tracking | answers for the number asked; `isFinal()` follows the status; unscanned is `pending`; malformed throws `ShipmentExceptionInterface` |

## What is not

Some rules cannot be observed from outside, and belong in the driver's own unit tests, fed
with recorded carrier responses:

- the mapping of each carrier event onto a `TrackingStatus`, and of each error onto an exception;
- that `getCode()`, `getName()` and `supports()` perform no I/O;
- every `SHOULD` of the specification, such as populating pickup point time zones.
