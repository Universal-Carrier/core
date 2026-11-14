<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Dto\Address;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

#[CoversClass(Address::class)]
class AddressTest extends TestCase
{
    public function testItBuildsAnAddressAndNormalizesTheCountryCode(): void
    {
        $address = new Address(
            address1: '1 Main St',
            postalCode: '75001',
            city: 'Paris',
            countryCode: ' fr ',
            firstName: 'Jane',
            lastName: 'Doe',
            company: 'Acme',
            state: 'Ile-de-France',
            phone: '+33123456789',
            email: 'jane@example.com'
        );

        $this->assertSame('FR', $address->countryCode);
        $this->assertSame('Jane', $address->firstName);
        $this->assertSame('Acme', $address->company);
        $this->assertSame('Ile-de-France', $address->state);
    }

    public function testItAcceptsAPersonalNameWithoutACompany(): void
    {
        $address = new Address('1 Main St', '75001', 'Paris', 'FR', firstName: 'Jane', lastName: 'Doe');

        $this->assertSame('Jane Doe', $address->getFullName());
        $this->assertSame('Jane Doe', $address->getDisplayName());
        $this->assertFalse($address->isBusiness());
    }

    public function testItAcceptsACompanyWithoutAPersonalName(): void
    {
        $address = new Address('1 Main St', '75001', 'Paris', 'FR', company: 'Acme');

        $this->assertNull($address->getFullName());
        $this->assertSame('Acme', $address->getDisplayName());
        $this->assertTrue($address->isBusiness());
    }

    public function testItPrefersThePersonalNameForDisplay(): void
    {
        $address = new Address('1 Main St', '75001', 'Paris', 'FR', firstName: 'Jane', company: 'Acme');

        $this->assertSame('Jane', $address->getDisplayName());
    }

    public function testItTrimsEveryField(): void
    {
        $address = new Address(
            address1: '  1 Main St  ',
            postalCode: " 75001\t",
            city: ' Paris ',
            countryCode: 'FR',
            firstName: ' Jane ',
            lastName: ' Doe ',
            state: '  Ile-de-France  ',
            phone: ' +33123456789 '
        );

        $this->assertSame('1 Main St', $address->address1);
        $this->assertSame('75001', $address->postalCode);
        $this->assertSame('Paris', $address->city);
        $this->assertSame('Jane', $address->firstName);
        $this->assertSame('Doe', $address->lastName);
        $this->assertSame('Ile-de-France', $address->state);
        $this->assertSame('+33123456789', $address->phone);
    }

    public function testABlankOptionalFieldBecomesNull(): void
    {
        $address = new Address(
            '1 Main St',
            '75001',
            'Paris',
            'FR',
            firstName: 'Jane',
            company: '   ',
            address2: '',
            email: " \t "
        );

        $this->assertNull($address->company);
        $this->assertNull($address->address2);
        $this->assertNull($address->email);
        $this->assertFalse($address->isBusiness());
    }

    public function testAWhitespaceOnlyCompanyDoesNotSatisfyTheNameRequirement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must carry a contact name');

        new Address('1 Main St', '75001', 'Paris', 'FR', company: '   ');
    }

    public function testItRejectsAnAddressWithNoNameAtAll(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must carry a contact name');

        new Address('1 Main St', '75001', 'Paris', 'FR');
    }

    #[DataProvider('invalidCountryCodeProvider')]
    public function testItRejectsAnInvalidCountryCode(string $countryCode): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected a 2-letter ISO 3166-1 alpha-2 code.');

        new Address('1 Main St', '75001', 'Paris', $countryCode, firstName: 'Jane');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCountryCodeProvider(): iterable
    {
        yield 'alpha-3' => ['FRA'];
        yield 'too short' => ['F'];
        yield 'digits' => ['12'];
        yield 'empty' => [''];
    }

    #[DataProvider('blankFieldProvider')]
    public function testItRejectsBlankMandatoryFields(string $address1, string $postalCode, string $city, string $expected): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expected);

        new Address($address1, $postalCode, $city, 'FR', firstName: 'Jane');
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function blankFieldProvider(): iterable
    {
        yield 'address1' => ['  ', '75001', 'Paris', 'address1 cannot be blank.'];
        yield 'postalCode' => ['1 Main St', '', 'Paris', 'postalCode cannot be blank.'];
        yield 'city' => ['1 Main St', '75001', ' ', 'city cannot be blank.'];
    }
}
