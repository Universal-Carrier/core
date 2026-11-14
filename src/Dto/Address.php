<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use UniversalCarrier\Core\Exception\InvalidArgumentException;
use UniversalCarrier\Core\Support\Assert;

/**
 * Immutable Data Transfer Object representing a postal address and the party located at it.
 *
 * The same object describes both the sender and the recipient of a shipment. Because the
 * parameter list is long and mostly optional, addresses are meant to be built with named
 * arguments.
 *
 * Every field is trimmed on construction, and an optional field left blank becomes null: a
 * postal code padded with spaces is rejected by most carrier APIs, and " " is no more
 * informative than an absent value.
 *
 * A carrier needs someone to hand the parcel to, so an address MUST carry either a personal
 * name or a company name. Business shipments frequently have no individual contact, and
 * forcing a placeholder first name is precisely the kind of dirty data a standard should
 * prevent.
 */
final readonly class Address
{

    public string $address1;

    public string $postalCode;

    public string $city;

    public string $countryCode;

    public ?string $firstName;

    public ?string $lastName;

    public ?string $company;

    public ?string $state;

    public ?string $address2;

    public ?string $address3;

    public ?string $phone;

    public ?string $email;

    /**
     * @param string $address1 The street address (number, street name).
     * @param string $postalCode The postal code. Formats vary widely and are not validated here.
     * @param string $city The city or locality name.
     * @param string $countryCode The 2-letter ISO 3166-1 alpha-2 country code, case insensitive.
     * @param string|null $firstName The given name of the contact, if any.
     * @param string|null $lastName The family name of the contact, if any.
     * @param string|null $company The company name. Required when no personal name is given.
     * @param string|null $state The state, province or region. Mandatory for US, CA, AU, BR, IN
     *   and several other destinations; carriers reject those shipments without it.
     * @param string|null $address2 Second address line (building, floor, apartment).
     * @param string|null $address3 Third address line (delivery hints, place names).
     * @param string|null $phone A phone number, ideally in E.164 format. Often mandatory for
     *   express services and for pickup point notifications.
     * @param string|null $email An email address, used by most carriers for tracking notices.
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $address1,
        string $postalCode,
        string $city,
        string $countryCode,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $company = null,
        ?string $state = null,
        ?string $address2 = null,
        ?string $address3 = null,
        ?string $phone = null,
        ?string $email = null
    ) {
        $this->address1 = Assert::notBlank($address1, 'address1');
        $this->postalCode = Assert::notBlank($postalCode, 'postalCode');
        $this->city = Assert::notBlank($city, 'city');
        $this->countryCode = Assert::countryCode($countryCode);

        $this->firstName = Assert::trimToNull($firstName);
        $this->lastName = Assert::trimToNull($lastName);
        $this->company = Assert::trimToNull($company);
        $this->state = Assert::trimToNull($state);
        $this->address2 = Assert::trimToNull($address2);
        $this->address3 = Assert::trimToNull($address3);
        $this->phone = Assert::trimToNull($phone);
        $this->email = Assert::trimToNull($email);

        if ($this->getFullName() === null && $this->company === null) {
            throw new InvalidArgumentException(
                'An address must carry a contact name (firstName and/or lastName) or a company name.'
            );
        }
    }

    /**
     * The contact's full name, or null when the address only identifies a company.
     */
    public function getFullName(): ?string
    {
        $fullName = trim(sprintf('%s %s', $this->firstName ?? '', $this->lastName ?? ''));

        return $fullName === '' ? null : $fullName;
    }

    /**
     * The name to print on the label: the contact when there is one, the company otherwise.
     *
     * Guaranteed to be non-empty by the constructor invariants.
     */
    public function getDisplayName(): string
    {
        return $this->getFullName() ?? (string)$this->company;
    }

    /**
     * Whether the address designates a company rather than an individual.
     */
    public function isBusiness(): bool
    {
        return $this->company !== null;
    }
}
