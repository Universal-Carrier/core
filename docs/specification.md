# Universal Carrier — Specification

> **Status:** unreleased, targeting `1.0.0`.
>
> This document and `universal-carrier/core` share one version number. The specification that
> applies to a release is the copy tagged with it, never the one on the default branch.

## 1. Scope

This document describes a common contract for interacting with shipping carriers from PHP
applications. It covers four operations — quoting shipping options, locating out-of-home
delivery points, booking a shipment and tracking it — and the data structures those
operations exchange.

It does **not** cover transport (HTTP clients, SOAP, EDI), authentication, configuration or
persistence. Those are the responsibility of the *implementation* — hereafter the
*driver* — or of a future revision of this document.

The key words `MUST`, `MUST NOT`, `REQUIRED`, `SHALL`, `SHALL NOT`, `SHOULD`, `SHOULD NOT`,
`RECOMMENDED`, `MAY` and `OPTIONAL` are to be interpreted as described in
[RFC 2119](https://www.ietf.org/rfc/rfc2119.txt).

The intended usage is direct and synchronous: instantiate a driver, ask it for rates or for
a label, use the result. Everything in this document is written for that shape.

### 1.1 Goals

- A consumer written against this contract MUST be able to switch carriers without any change
  other than which driver it instantiates.
- Data that means the same thing at two carriers MUST be represented the same way.
- Data that is genuinely carrier-specific MUST have an explicit, typed escape hatch rather
  than leak into the common shape.

### 1.2 Non-goals

- Hiding every difference between carriers. Service codes, pickup point identifiers and label
  layouts are carrier-specific by nature and remain opaque strings.
- Being a client library. This package contains no networking code and has no runtime
  dependency beyond PHP itself.
- **Serialization.** The DTOs are in-memory value objects passed to a driver and returned by
  it, in one process. This document defines no wire format for them, and deliberately so:
  freezing one would be a permanent commitment under section 9 for a use case the contract
  does not target. See section 4.3.

---

## 2. Definitions

| Term | Meaning |
|---|---|
| **Carrier** | The company physically moving the parcel (Colissimo, DHL, GLS…). |
| **Driver** | A PHP class implementing one or more capability interfaces (section 5) for one carrier. |
| **Capability** | One operation of the contract, declared by its own interface. |
| **Shipment option** | An optional part of a booking request — several parcels, insurance, customs data — that a driver may or may not honour. |
| **Consumer** | The application calling a driver through this contract. |
| **Parcel** | One physical box. A shipment MAY contain several. |
| **Shipment** | One booking, covering one or more parcels between two addresses. |
| **Pickup point** | An out-of-home delivery location: shop, locker, post office, depot. |
| **Service** | A commercial offer of a carrier, identified by an opaque `serviceCode`. |
| **Checkpoint** | One event in a parcel's journey, as reported by the carrier. |

---

## 3. Units and formats

To remove any ambiguity, the following canonical representations are `REQUIRED` throughout the
contract. Drivers MUST convert to and from whatever their carrier uses.

| Quantity | Canonical representation |
|---|---|
| Weight | `float`, **kilograms** |
| Dimensions | `float`, **centimetres** |
| Money | `int` in the **minor unit** of the currency, inside a `Price` |
| Currency | `string`, ISO 4217 alpha-3, uppercase |
| Country | `string`, ISO 3166-1 alpha-2, uppercase |
| Language | `string`, ISO 639-1 alpha-2, lowercase |
| Date & time | `DateTimeImmutable` |
| Opening times | `string`, 24-hour `"HH:MM"`, local to the location |
| Time zone | `string`, IANA identifier (e.g. `"Europe/Paris"`) |
| Weekday | `DayOfWeek`, ISO-8601 numbering (1 = Monday) |
| Coordinates | `float`, WGS 84 decimal degrees |
| Label payload | `string`, **raw bytes**, never transport-encoded |

Values are normalised on construction, so a consumer MAY compare them with `===`:

- `Address::$countryCode`, `PickupPointQuery::$countryCode` and `Price::$currency` are
  uppercased.
- `ShipmentRequest::$senderLanguage` and `$recipientLanguage` are lowercased.
- **Every string field is trimmed.** An optional one left blank becomes `null`, because `" "`
  carries no more information than an absent value — and a postal code padded with spaces is
  rejected by most carrier APIs.

---

## 4. Data Transfer Objects

### 4.1 General rules

1. Every DTO of this package is `final readonly`. Drivers MUST NOT extend them.
2. A DTO MUST validate its own invariants in its constructor and MUST throw
   `InvalidArgumentExceptionInterface` when they are violated. A DTO **built through its
   constructor** is therefore always in a valid state. See section 4.3 for the limit of that
   guarantee.
3. Collection properties are validated element-by-element at runtime. A `Parcel[]` annotation
   is enforced, not merely documented.
4. Collections are re-indexed with `array_values()` on construction. Consumers MAY rely on
   keys `0..n-1`.
5. Strings are normalised as described in section 3. A DTO therefore never stores the raw
   input when that input differs from its canonical form.
6. DTOs are meant to be built with **named arguments**. Parameter order is not part of the
   stability guarantee for optional parameters; parameter *names* are.
7. `RateRequest` and `ShipmentRequest` both implement `ShipmentDataInterface`, which declares
   everything describing the movement itself: `sender`, `recipient`, `parcels`,
   `shippingDate`, `pickupPointId`, `metadata`, and the accessors derived from them. A driver
   SHOULD type its shared mapping helpers on that interface rather than on either concrete
   class. Consumers MUST NOT implement it: like the DTOs, its implementations are fixed.

### 4.2 The escape hatch

There is one escape hatch, `array<string, mixed> $metadata`, and it works in both directions.
`RateRequest`, `PickupPointQuery` and `ShipmentRequest` carry what the consumer sends;
`Rate`, `PickupPoint`, `ShipmentResponse` and `Tracking` carry what the carrier answered.

**Consumer to driver.**

- A consumer MAY use `$metadata` on a request to pass carrier-specific options.
- A driver MUST ignore metadata keys it does not recognise, and MUST NOT fail because of them.
- A driver SHOULD namespace the keys it reads with its own `getCode()` (e.g. `dhl.account`).

**Driver to consumer.**

- A driver decides what is worth publishing and under which key, and SHOULD namespace those
  keys with its own `getCode()` too (e.g. `mondial_relay.label_url`).
- A driver SHOULD publish only what this specification does not already model, and MUST NOT
  contradict the modelled fields with it.
- Values are `mixed`: a scalar, a list, or a fragment of the carrier's own answer — a
  `SimpleXMLElement` for a SOAP carrier — when that is the faithful shape. A driver MUST NOT
  flatten such a value into an array to satisfy a type.
- A driver publishing nothing leaves it empty.

Metadata is the supported way to go beyond the standard, and anything read from it is, by
definition, no longer portable: it is named after one carrier and documented by one driver. The
`mixed` value type is what enforces that a consumer knows it: a static analyser rejects
`$response->metadata['x']['y']` outright, and accepts the same read once guarded by
`is_array()`.

This specification deliberately defines no field for the carrier's untouched response. A
driver that wants to expose one MAY publish it under a key of its own, but the whole payload
is a debugging concern — logs, support — rather than something a consumer should be invited
to read through the contract.

### 4.3 Serialization and the limit of the validity guarantee

This specification defines no serialized representation of its DTOs. A consumer that needs to
persist, queue or transmit one chooses its own representation, and that choice is outside the
scope of conformance.

Two consequences follow, and consumers MUST be aware of both.

**The validity guarantee only covers construction.** `readonly` prevents reassignment, not the
first write to an object created without its constructor. Any hydration mechanism that
bypasses the constructor — `unserialize()`, and the reflection-based hydrators used by most
serializers, mappers and ORMs — can therefore produce a DTO in a state its constructor would
have rejected, silently:

```php
$address = (new ReflectionClass(Address::class))->newInstanceWithoutConstructor();
// writing directly into the properties yields countryCode = "france-entiere"
// and postalCode = "   ", with no error raised.
```

A consumer that reconstructs a DTO from stored data SHOULD do so by calling the constructor
with the stored values, precisely so that validation is replayed.

**Binary payloads are not text.** `Label::$content` holds raw bytes, and a PDF or PNG label is
not valid UTF-8: `json_encode()` on a `ShipmentResponse` carrying one fails outright. A
consumer serializing a label to a text format MUST encode the content itself — base64 through
`Label::toBase64()` — and decode it before printing. PHP's native `serialize()` handles the
bytes as they are.

---

## 5. The contract

The contract is split into one interface per operation. They share a common parent carrying the
identity of the carrier:

```php
interface CarrierInterface
{
    public function getCode(): string;
    public function getName(): string;
}

interface RateProviderInterface extends CarrierInterface
{
    /** @return Rate[] */
    public function getRates(RateRequest $request): array;
}

interface PickupPointProviderInterface extends CarrierInterface
{
    /** @return PickupPoint[] */
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

A driver implements every capability interface its carrier offers, and no other. A driver
whose carrier offers all four implements all four:

```php
final class ColissimoCarrier implements
    RateProviderInterface,
    PickupPointProviderInterface,
    ShipmentCreatorInterface,
    TrackingProviderInterface
{ /* ... */ }
```

### 5.1 Identity

- `getCode()` MUST return a lowercase snake_case identifier, stable across releases.
- `getCode()` SHOULD identify the *carrier*, not the driver vendor.
- `getName()` MUST return a name fit to display to an end user.
- Neither method may perform I/O.

### 5.2 Capability discovery

There are two levels, and they are answered differently.

**Operations are types.** Whether a driver can quote, list pickup points or book is answered by
`instanceof`, and by nothing else:

```php
if ($carrier instanceof PickupPointProviderInterface) {
    $points = $carrier->getPickupPoints($query);
}
```

- A driver MUST NOT implement a capability interface its carrier does not offer. A method
  that exists only to throw is non-conforming: it turns a question the type system answers
  for free into a failure discovered at runtime.
- A consumer that needs one operation SHOULD type its dependency on that interface alone, not
  on the driver class nor on an intersection it does not use.

**Shipment options are values.** Whether a booking may hold several parcels, an insurance
value or customs data is answered by `ShipmentCreatorInterface::supports()`:

- `supports()` MUST be answerable without a network call.
- Its answer MAY depend on how the instance was configured — an option is sometimes part of
  the customer's contract with the carrier rather than of the carrier's API — but MUST NOT
  change during the lifetime of the instance.
- If `supports($option)` returns `false`, `createShipment()` MUST throw
  `UnsupportedOptionExceptionInterface` for a request exercising that option.
- If `supports($option)` returns `true`, it MUST NOT throw it on account of that option.

This equivalence is what makes the options usable for building a carrier selector or a
checkout UI. A driver that lies here is non-conforming.

### 5.3 `getRates()`

- Rates MUST be sorted by ascending `price->amount`.
- Asking for a quote is always allowed. When the carrier has nothing to offer for the
  shipment — a parcel heavier or larger than any of its services accepts, a destination it
  does not serve — the result MUST be an **empty array**, not an exception.
- Only a request the carrier cannot even assess — a postal code that does not exist, or that
  does not match the country's format — MUST throw `ShipmentExceptionInterface`.
- When the instance also implements `ShipmentCreatorInterface`, every returned
  `Rate::$serviceCode` MUST be accepted by its `createShipment()`, for the same shipment, for
  as long as the quote is commercially valid.
- A rate whose service delivers to a pickup point MUST set `requiresPickupPoint` to `true`.
- Prices MUST all share one currency within a single response.

### 5.4 `getPickupPoints()`

- Points MUST be sorted by ascending `distanceInKilometer`; points without a distance go last.
- The result MUST NOT exceed `PickupPointQuery::$limit` when one is given.
- `PickupPointQuery::$countryCode` is always set — taken from the address, or given with the
  coordinates — so a driver whose carrier searches one country at a time never has to guess
  it.
- "No point found" MUST be an **empty array**, not an exception.
- A driver SHOULD apply every query criterion its carrier supports and MAY ignore the others.
  It MUST NOT fail because of a criterion it cannot apply — except `$limit`, which is
  `REQUIRED`.
- When the instance also implements `ShipmentCreatorInterface`, every returned
  `PickupPoint::$id` MUST be accepted as `ShipmentRequest::$pickupPointId` by its
  `createShipment()`.
- A weekday absent from `openingHours` means *unknown*; a weekday present with no time slot
  means *closed*. Drivers MUST NOT emit an empty `BusinessDay` to mean "unknown".
- Opening hours are wall-clock times **local to the location**. Drivers SHOULD populate
  `PickupPoint::$timezone` whenever the carrier discloses it, or whenever it can be derived
  unambiguously from the country. Without it, `isOpenAt()` cannot convert an arbitrary moment
  into local time, and the caller carries that burden instead.

### 5.5 `createShipment()`

- This is the only method with a side effect. On success, a shipment exists and will be
  invoiced.
- The call MUST be synchronous.
- It MUST NOT return a partially booked shipment. If any parcel fails, the whole call throws.
- The response MUST hold exactly one `ShipmentPackage` per requested parcel, **in request
  order**.
- `ShipmentRequest::$pickupPointId` MUST be set for a service that requires one, and MUST NOT
  be set for a home delivery service. A driver MUST throw `ShipmentExceptionInterface` on
  either mismatch.
- Every `ShipmentPackage` carries its own `Label`. There is no package without one, and no
  combined document standing in for them: a consumer prints a shipment the same way at every
  carrier.
- Label content MUST be the raw payload. A driver receiving base64 from its carrier MUST
  decode it; one receiving a link MUST download the label before returning, and SHOULD
  retry a temporary failure.
- If the shipment was booked but its label cannot be obtained, the driver MUST throw rather
  than return a package without it. The shipment exists and will be invoiced, so the
  exception MUST implement `ConnectionExceptionInterface` — which consumers do not blindly
  retry (section 5.7) — and SHOULD carry the carrier's shipment identifier, so that the label
  can be fetched later instead of the shipment being booked twice.
- A driver MUST throw `UnsupportedOptionExceptionInterface`, rather than silently drop the
  data or split the booking, when the request exercises a shipment option it does not report:
  - the request holds several parcels and `ShipmentOption::MULTI_PARCEL` is unsupported;
  - a parcel declares an `insuranceValue` and `ShipmentOption::INSURANCE` is unsupported;
  - the shipment requires a customs declaration, a parcel declares `items`, and
    `ShipmentOption::CUSTOMS_DECLARATION` is unsupported.

  Dropping insurance or customs data is worse than refusing: the consumer believes it obtained
  cover, or a customs declaration, and only finds out when the parcel is lost or held at the
  border.
- Whether a shipment requires a customs declaration is for the driver to determine, by its
  carrier's rules. Items declared on a shipment that requires none MAY be ignored.
- When a customs declaration is made, `ShipmentRequest::$exportReason` MUST be forwarded with
  it. A driver whose carrier has no equivalent for the given reason MUST throw
  `ShipmentExceptionInterface` rather than declare another one: the reason decides the duties
  the recipient pays.

### 5.6 `track()`

- The result MUST be about the tracking number asked for: `Tracking::$trackingNumber` is that
  number.
- A well-formed number the carrier does not know yet — a label created but not yet scanned —
  MUST be reported as `pending`, not as an error. A number that cannot belong to the carrier,
  such as one of the wrong format, MUST throw `ShipmentExceptionInterface`.
- Only the number and the status are `REQUIRED`. The description, location, estimated delivery
  and checkpoints are filled in whenever the carrier provides them.
- `Tracking::$status` SHOULD be the status of the most recent checkpoint, when there are any.
- Checkpoints are exposed oldest first. The DTO sorts them on construction, so a driver MAY
  pass them in whatever order its carrier uses.
- Every checkpoint's `at` MUST carry the time zone the event happened in. A carrier that sends
  local times without an offset documents which zone they are in; the driver attaches it.
- Descriptions and locations MUST be the carrier's own wording, neither translated nor
  rephrased. The status is for code; the description is for people.
- The method has no side effect. Consumers polling for updates SHOULD stop once
  `Tracking::isFinal()` returns `true`.

Every carrier event MUST be mapped onto one of these nine statuses:

| Status | Meaning |
|---|---|
| `pending` | A label exists; the carrier does not have the parcel yet. |
| `picked_up` | The carrier has taken the parcel: collected, or dropped off by the sender. |
| `in_transit` | The parcel is moving through the carrier's network. |
| `out_for_delivery` | The parcel is on its way to the recipient for the final delivery. |
| `delivery_attempted` | Delivery was attempted and failed; the carrier will try again or awaits the recipient. |
| `available_for_pickup` | The parcel is waiting for the recipient at a pickup point, post office or locker. |
| `delivered` | The parcel was handed to the recipient, or left where they asked. Final. |
| `exception` | An incident needs attention. |
| `returned` | The parcel is going, or has gone, back to the sender. Final. |

The ambiguous cases are mapped as follows, so that two drivers describe the same situation the
same way:

- **Failed delivery attempt** (the recipient was absent): `delivery_attempted`. If the carrier
  then leaves the parcel at a pickup point or post office, the next event is
  `available_for_pickup`.
- **Customs clearance** proceeding normally: `in_transit`. Held in customs, duties unpaid or
  documents missing: `exception`.
- **Damage, loss, address problem, refusal by the recipient**, a delay the carrier reports as
  such: `exception`.
- **Return to sender**: `returned` from the moment the return is decided, not only once the
  parcel is back. A consumer otherwise sees `in_transit` for days on a parcel coming home.
- **An event the driver cannot interpret**: it MUST NOT be reported as `exception`, and MUST
  NOT invent progress. The checkpoint carries the status of the checkpoint before it — the
  last thing known to be true — or `pending` when it is the first one, with the carrier's
  wording in the description. The same applies to an event that announces nothing new, such
  as a notification sent to the recipient.

  A carrier's routine wordings outnumber its incident ones by an order of magnitude, so an
  unrecognised label is almost never an incident: reporting one fabricates an alert on a
  parcel travelling normally. Incidents are recognised by the wordings that name them, and by
  the overall status a carrier gives alongside its events.

  The cost is accepted knowingly: a mapping table that falls behind its carrier stops
  announcing itself. The carrier's own wording stays in `description`, where a maintainer
  reading a history sees it.

### 5.7 Idempotency

This revision does **not** require `createShipment()` to be idempotent: calling it twice with
the same `orderReference` MAY produce two shipments. Consumers MUST NOT retry a
`createShipment()` call that failed with `ConnectionExceptionInterface` without first checking
with the carrier whether the shipment exists.

Making booking idempotent on `orderReference` is a candidate for a future revision.

---

## 6. Error handling

Every error is catchable through a single root interface:

```
CarrierExceptionInterface  (extends Throwable)
├── InvalidArgumentExceptionInterface     — a DTO invariant was violated
├── ShipmentExceptionInterface            — the carrier rejected the request content
├── ConnectionExceptionInterface          — the carrier could not be reached
└── UnsupportedOptionExceptionInterface   — the driver does not honour this shipment option
```

Rules:

1. A driver MUST NOT let an exception that does not implement `CarrierExceptionInterface`
   escape from a contract method. Third-party exceptions MUST be wrapped, with the original
   passed as `$previous`.
2. A driver SHOULD implement the **interfaces** on its own exception classes rather than
   extend the concrete classes shipped here. Only the interfaces are part of the contract; the
   classes are a convenience.
3. The distinction between `ShipmentExceptionInterface` and `ConnectionExceptionInterface` is
   about **retryability**: the former will fail again with the same input, the latter may
   succeed later. Consumers rely on this to decide between "tell the customer" and "retry or
   fall back".
4. HTTP 4xx from a carrier is usually a `ShipmentExceptionInterface`; HTTP 5xx, timeouts and
   authentication failures are `ConnectionExceptionInterface`.

---

## 7. Design rationale

Points where the design deliberately departs from what carrier APIs do.

**One interface per operation.**
Carriers differ in scope: a rate comparison API quotes and books nothing, a locker network
lists points long before it books, and a carrier may add tracking years after its first driver
was written. A single interface declaring every operation forced each driver to implement
methods whose only body was a `throw`, and required a `supports()` rule to keep those bodies
honest. Separate interfaces make that rule unnecessary — a driver cannot claim an operation it
does not have — let a static analyser narrow the type after an `instanceof`, and, above all,
let the contract grow: a new operation is a new interface, added in a minor version, where a
new method on a shared interface breaks every existing driver. PSR-17 splits its factories the
same way.

The cost falls on decorators. A logging or caching wrapper must implement the same combination
of interfaces as the driver it wraps, and four capabilities make fifteen combinations. In
practice a decorator is written per capability — caching only makes sense on quotes and pickup
point searches — which keeps the cost small, but it is real.

**Shipment options stay values, not interfaces.**
Multi-parcel booking, insurance and customs declarations are not operations: no method
corresponds to them, only data `createShipment()` accepts or refuses. Turning them into marker
interfaces would add types with no method, multiply the combinations above, and fix at the
class level an answer that often depends on the customer's account. `supports()` answers per
instance, which is where the answer lives.

**One interface across both request objects.**
A quote and a booking describe the same physical movement at two different moments, so
everything describing that movement is declared once in `ShipmentDataInterface` and
implemented once in a shared trait. Unlike PSR-7, whose interfaces exist so that several
libraries can supply interchangeable implementations, this one exists only to let a driver
write its address- and parcel-mapping helpers a single time. It is a factoring device, not an
extension point — which is why the rule forbidding consumers to supply their own DTOs applies
to it unchanged.

**Having no rate is not an error.**
A consumer quoting a 60 kg parcel across five carriers expects to hear from the two that
carry freight, and silence from the other three. If an over-limit weight threw, every such
comparison would need a `try`/`catch` per carrier, and a carrier that cannot carry the parcel
would be indistinguishable from one that is down. The exception is kept for what is wrong
whichever carrier is asked: a request that describes no real place. Booking is different: a
`createShipment()` call names a service, so a parcel that service cannot carry is a rejection.

**No `isInternational()`.**
Whether a shipment goes through customs is not a function of two country codes. France to
Germany crosses a border and no customs; mainland Spain to the Canary Islands, or mainland
France to Guadeloupe when the address is recorded as `FR` with a `971` postal code, crosses
customs without changing country. Carriers draw these lines differently, so the contract leaves
the decision to the driver rather than offering a helper that would be wrong in exactly the
cases that matter.

**Nine tracking statuses, and the carrier's wording beside them.**
Every carrier has dozens of event codes; normalising them is only useful if two drivers map the
same situation to the same status, which a short list and written rules for the ambiguous
cases make possible. A failed delivery attempt has its own status because it is both the most
frequent incident and the one that needs no action from the merchant — left in `exception`, it
would bury the incidents that do. What the short list loses in precision, the description
gives back: it is the carrier's own text, for the customer to read.

**An unreadable event is not an incident, and gets no status of its own.**
The first rule said `exception`, on the grounds that a human look beats a guess. A real
history proved it backwards: on a parcel delivered without a hitch, four wordings out of ten
were unknown to a mapping table written from the carrier's own documentation, and not one of
them was an incident — a notification to the recipient, a handover between sites. Reporting
those as incidents is not caution, it is a false alarm on a normal delivery, shown to the
customer.

A tenth status meaning "the driver could not read this" was considered and refused: the nine
describe where the parcel is, this one would describe what the driver knows. Consumers would
have to branch on it forever — `TrackingStatus` is closed within a major version — for what is
a missing line in a driver's table. The driver's ignorance belongs in `description`, which
already carries the carrier's exact words.

**Separate `RateRequest` and `ShipmentRequest`.** A quote is asked *before* a service is
chosen — the service code is the answer, not part of the question. A single request object
would force callers to invent a service code, an order reference and a label language to ask
"how much?".

**Parcel-oriented `ShipmentResponse`.** Every carrier supporting multi-parcel booking returns
one tracking number and one label per box. A flat `(trackingNumber, labelContent)` pair makes
those carriers unrepresentable.

**`Parcel` carries its own weight; items are optional.** Weight is what the carrier prices and
what the sorting centre verifies, and it is known for every shipment. Itemised content is only
needed for customs. Deriving the weight from items would force domestic shipments to invent
fictitious lines.

**No `isOversized` flag.** Whether a parcel is oversized is a *conclusion* drawn from its
dimensions against a carrier's own thresholds — a carrier output, not a consumer input. The
contract exposes the measurements (`getCombinedDimensionsInCentimeter()`) and leaves the
verdict to the driver.

**Money as integer minor units, with a per-currency exponent.** JPY has no decimals and KWD
has three. A hard-coded division by 100 is wrong for roughly thirty currencies, which is not
acceptable in something called *universal*.

**`Price` offers comparison and rendering, and no arithmetic.** Shipping does not compute on
money. A carrier quotes a rate; a shipper declares a customs value; neither is added to or
discounted afterwards, and a rebate granted to the customer never reaches the carrier. The one
place amounts are summed — the declared value of a parcel's items — does it on plain integers,
with a `!==` check on the currency. Exposing `add()` and `subtract()` on the value object would
buy nothing and cost an invariant: since an amount may not be negative, `subtract()` would be a
partial function, failing on runtime values with nothing in its signature to warn the caller.

**No price is sent when booking a shipment.** What a shipment costs is a carrier output
(`Rate::$price`, `ShipmentResponse::$cost`), never a consumer input, so `Parcel` carries no
shipping price. This is not only a modelling preference: carriers reject a declared shipping
cost of zero — Colissimo does — so a field the consumer must fill with a number it does not
own is a field that produces errors.

**The label format is configuration, not a request field.**
Which format a label comes in depends on the printer that will print it, which is a property
of the place the driver is used from — a ZPL thermal printer at the warehouse, a PDF for the
shop — not of the shipment. A driver takes it as configuration, and an application printing
in several places instantiates one driver per configuration. `Label::$format` reports what
was produced; nothing in `ShipmentRequest` asks for it.

**A label is always its bytes, never a link.**
Some carriers answer a booking with a URL rather than a file. Letting a driver pass that URL
on — in `$metadata` — would make printing a label carrier-specific again:
the consumer would have to know which drivers download and which do not, where each one puts
its link, and how to complete it. Only the driver knows how to fetch it anyway, since the
link may need credentials, a format parameter, or expire within minutes. So the driver
downloads it (section 5.5), and the consumer always has the same one line of code.

The counterpart is that the file is handed over once. Keeping it — to print it again, or to
reprint it a week later — is the application's job, in its own storage: a carrier link, where
one exists at all, is usually deleted after a while.

For the same reason there is no "combined label" holding every parcel's label in one document.
A revision had one, so that a carrier answering a three-parcel booking with a single
three-page PDF could be represented; it made `ShipmentPackage::$label` nullable, which put a
null check in front of every consumer printing a label — for a carrier none of the drivers
written so far has met. If one turns up, the field comes back as an optional parameter, which
section 9 allows within a major version, with a real carrier to model it on.

**Format, size and DPI separated on `Label`.** They are orthogonal axes. Folding them into one
enum produces a combinatorial explosion and, in practice, silently omits the resolution — and
a ZPL program printed at the wrong DPI comes out at the wrong scale.

**One escape hatch, in both directions, rather than a raw response field.**
An earlier revision gave responses a `$rawResponse` holding the carrier's whole answer. It put
two different mechanisms in the contract — a string-keyed array going one way, an untyped
payload coming back — and it asked the wrong thing of a driver: capturing everything, so that
the consumer could go fishing in a shape nobody documents. A driver knows which three or four
values its carrier returns that this specification does not model, and naming them under its
own prefix is both smaller and honest. The whole payload remains available to whoever needs it,
in the driver's logs, where a debugging concern belongs.

Values stay `mixed`, so a SOAP driver can still publish an element as it stands rather than
flatten it, and so a static analyser keeps enforcing the "narrow before reading" rule that was
previously only a sentence in this document.

**Filenames built from carrier data are sanitised.**
`Label::getSuggestedFilename()` takes a basename that in practice is a tracking number — a
value that comes from a carrier response and is documented as being handed straight to
`file_put_contents()`. It is therefore reduced to `A-Za-z0-9._-`, so the result is always a
plain filename and never a path.

**`isOpenAt()` returns `?bool`.** "Closed" and "we don't know" are different answers.
Collapsing them costs the customer a delivery option they could have used.

**Pickup points carry their own time zone.**
Opening hours are wall-clock times local to the shop. An application whose server runs in one
zone and whose customer collects a parcel in another cannot compare the two without knowing
the shop's zone — and the failure is silent, which makes it the worst kind.

**Opening hours normalised and sorted.** One carrier returns `08:00-19:00`, another
`08:00-12:00` and `14:00-19:00` for the same shop, in arbitrary order. Sorting them and
rejecting overlaps is what makes the two comparable downstream.

---

## 8. Conformance

A driver is conforming if:

1. It implements at least one of `RateProviderInterface`, `PickupPointProviderInterface`,
   `ShipmentCreatorInterface` and `TrackingProviderInterface`, and none its carrier does not
   offer.
2. It honours every `MUST` of section 5 for the capabilities it implements.
3. It only throws exceptions implementing `CarrierExceptionInterface` from contract methods.
4. When it implements `ShipmentCreatorInterface`, `supports()` agrees with runtime behaviour
   for every `ShipmentOption`.
5. It neither extends nor replaces the DTOs of this package, nor implements
   `ShipmentDataInterface` itself.

The `universal-carrier/conformance` package checks every rule of this list that can be
observed from outside the driver, with one PHPUnit test case per capability. Passing it is
necessary for conformance, not sufficient: the mapping of carrier events and errors, and the
absence of I/O in `getCode()`, `getName()` and `supports()`, remain the driver's to test.

---

## 9. Versioning

This package follows [Semantic Versioning](https://semver.org/), and this document follows the
package: they are released together, under one number, from one tag.

What a minor release may add is listed below, and every such addition is recorded here and in
the changelog. A **patch** release never changes a normative rule — only wording, examples and
typos — and changing or removing a rule is a **major** release.

A reader therefore cites a rule by version — "section 5.5 of 1.0" — and a driver's
`universal-carrier/core` constraint already states which version of the contract it implements.

It requires **PHP 8.4 or later**. `ShipmentDataInterface` declares properties rather than
getters, which relies on the abstract property declarations introduced with property hooks in
8.4; the alternative would have been to give every read-only DTO a second, redundant way of
exposing the same value. Raising the minimum PHP version is a major-version change.

Within a major version:

- No method is added to an existing interface of the contract, and no signature changes.
- New operations MAY be added as **new capability interfaces**. Existing drivers remain
  conforming without change.
- No `REQUIRED` constructor parameter is added to a DTO, and no parameter is renamed.
- New `OPTIONAL` constructor parameters MAY be appended.
- New cases MAY be added to `ShipmentOption`, `PickupPointType`, `ExportReason`, `LabelFormat`
  and `LabelSize`.
  Consumers matching exhaustively on these enums SHOULD include a default branch.

Adding a case to an enum is technically a breaking change for exhaustive `match` expressions.
It is treated as minor here because the alternative — a major release per new label size —
would make the package unusable as a common dependency.

`TrackingStatus` is the exception: its cases only change with a major version. A new status
would not only be added, it would take events away from existing ones — a status introduced
for "held in customs" would change what `exception` means for every driver and every consumer
already relying on it.

---

## 10. Open questions

Deliberately out of scope for this revision, listed so that adopters know what is coming:

- **Cancellation.** Some carriers offer it — UPS voids a shipment, FedEx cancels one — and
  others simply let an unused label expire. An operation only part of the field supports is
  exactly what a capability interface is for, so it costs nothing to leave it for later.
- **Return labels**, manifests / end-of-day closeout, and pickup scheduling.
- **Customs**: incoterms, invoice documents, EORI and VAT identifiers.
- **A normative serialized representation**, should exchanging DTOs between independent
  systems ever become a goal. It is not one today (section 4.3), and adopting one later would
  be additive rather than breaking.
- **Batch tracking**, for carriers that accept several numbers per call. It would be a
  capability interface of its own.

Cancellation, returns, manifests, pickup scheduling and batch tracking are operations: each
will arrive as a new capability interface, in a minor version, without touching existing
drivers. Customs data extends the DTOs through optional parameters, which section 9 permits
within a major version as well.
