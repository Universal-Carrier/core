# Demo driver

A complete driver for **Demo Carrier**, a fictional carrier, written the way a real one would
be. It implements all four capabilities and shows every job the specification gives a driver.

```bash
composer demo
```

Only the carrier's servers are simulated. Everything else is real driver code: configuration,
mapping in both directions, error translation, and the rules on each method.

## Layout

| File | Role | In a real driver |
|---|---|---|
| [`DemoCarrier.php`](DemoCarrier.php) | The driver: configuration, `supports()`, one method per capability, error translation | Same |
| [`Mapping/RequestMapper.php`](Mapping/RequestMapper.php) | DTOs → carrier payload | Same — most of the unit tests go here |
| [`Mapping/ResponseMapper.php`](Mapping/ResponseMapper.php) | Carrier payload → DTOs | Same |
| [`Support/Payload.php`](Support/Payload.php) | Typed reader over decoded JSON | Same, or a serializer |
| [`Support/CustomsTerritory.php`](Support/CustomsTerritory.php) | When a shipment needs a customs declaration | Same, from the carrier's documentation |
| [`Exception/`](Exception) | Driver exceptions implementing the package's interfaces | Same |
| [`Api/DemoApiClient.php`](Api/DemoApiClient.php) | **Fake** carrier servers | An HTTP / SOAP client, or the vendor SDK |
| [`run.php`](run.php) | A consumer exercising everything | Your application |

## What the driver has to handle

The fictional API deliberately uses its own conventions, as real carrier APIs do. Each one is
translated in exactly one place:

| Carrier convention | Package convention | Where |
|---|---|---|
| Weights in grams, sizes in mm | kg, cm | `RequestMapper::parcel()` |
| Amounts as `"8.90"` strings | `Price` in minor units | `RequestMapper::money()`, `ResponseMapper::price()` |
| One `name` field | first name, last name, company | `RequestMapper::address()` |
| Pickup point = (country, code) | one opaque `id` | `RequestMapper::relayReference()`, `ResponseMapper::pickupPoint()` |
| Point types `A` / `L` / `P` | `PickupPointType` | `RequestMapper::RELAY_KINDS` |
| Coordinates `"48,856614"` | `float` | `Payload::decimal()` |
| Distances in metres | km | `ResponseMapper::pickupPoint()` |
| Hours packed as `"0830120014001930"` | `BusinessDay` / `TimeSlot` | `ResponseMapper::openingHours()` |
| No time zone | IANA time zone | `ResponseMapper::timezoneOf()` |
| One label code `ZPL_203` | format, size, DPI | `ResponseMapper::labelSpecification()` |
| Labels in base64 | raw bytes | `ResponseMapper::decodeLabel()` |
| Offers in any order | cheapest first | `ResponseMapper::rates()` |
| Points unsorted, no limit applied | nearest first, capped | `DemoCarrier::getPickupPoints()` |
| HTTP errors, timeouts | the package's exception interfaces | `DemoCarrier::translate()` |
| "Too heavy" / "not served" errors on a quote | no rate, `[]` | `DemoCarrier::getRates()` |
| Its own customs borders (overseas, Canaries…) | driver decides | `Support/CustomsTerritory.php` |
| Customs categories `SALE`, `GIFT`… | `ExportReason` | `RequestMapper::customsCategory()` |
| Tracking event codes `PCH`, `AVP`, `RET`… | `TrackingStatus` | `ResponseMapper::trackingStatus()` |
| A code added since the table was written | keeps the previous status, never an incident | `ResponseMapper::tracking()` |
| Fields the contract does not model (`drop_off_before`, `signed_by`) | `$metadata`, under `demo.` keys | `ResponseMapper::shipment()`, `::tracking()` |
| Event times in Paris time, no offset | `DateTimeImmutable` with its zone | `ResponseMapper::tracking()` |
| 404 on a number not scanned yet | `pending`, not an error | `DemoCarrier::track()` |

Some rules the carrier does not enforce, so the driver checks them itself before paying for a
network call:
- a pickup point sent with a home delivery service is silently ignored by the API;
- an option the customer's contract with the carrier does not include (insurance) must be
  refused, never dropped.
