<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\EmailAddress;

/**
 * The rules of https://developers.twinfield.com/documentation/api/types/guidelines#e-mail-address-validation.
 */
final class EmailAddressTest extends TestCase
{
    #[DataProvider('accepted')]
    public function testAcceptsWhatTwinfieldAccepts(string $email): void
    {
        self::assertTrue(EmailAddress::isValid($email));
        self::assertSame($email, (string) EmailAddress::of($email));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function accepted(): iterable
    {
        yield 'plain' => ['john.doe@example.com'];
        yield 'upper case' => ['John.Doe@Example.COM'];
        yield 'digits' => ['user123@example2.com'];
        yield 'special characters in the local part' => ['!#$%&\'*+-/=?^_`{|}~@example.com'];
        yield 'one character local part' => ['j@example.com'];
        yield 'consecutive dots in the local part' => ['john..doe@example.com'];
        yield 'underscore in the domain' => ['john@my_company.example.com'];
        yield 'hyphen in the domain' => ['john@my-company.example.com'];
        yield 'domain without dot' => ['john@localhost'];
        yield 'the invalid address of the documentation' => ['john.doe@111.222.333.444.555'];
    }

    #[DataProvider('refused')]
    public function testRefusesWhatTwinfieldRefuses(string $email): void
    {
        self::assertFalse(EmailAddress::isValid($email));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('"%s" is not an email address.', $email));

        EmailAddress::of($email);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'empty' => [''];
        yield 'no at sign' => ['john.doe'];
        yield 'no local part' => ['@example.com'];
        yield 'no domain' => ['john@'];
        yield 'quoted local part of the documentation' => ['"John Doe"@example.com'];
        yield 'local part starts with a dot' => ['.john@example.com'];
        yield 'local part ends with a dot' => ['john.@example.com'];
        yield 'domain starts with a dot' => ['john@.example.com'];
        yield 'domain ends with a dot' => ['john@example.com.'];
        yield 'consecutive dots in the domain' => ['john@example..com'];
        yield 'two at signs' => ['john@doe@example.com'];
        yield 'space inside' => ['john doe@example.com'];
        yield 'not a letter A-Z in the local part' => ['jöhn@example.com'];
        yield 'not a letter A-Z in the domain' => ['john@exämple.com'];
        yield 'several addresses' => ['john@example.com, jane@example.com'];
        yield 'line break inside' => ["john@example.com\njane@example.com"];
    }

    public function testRemovesWhiteSpaceAround(): void
    {
        self::assertTrue(EmailAddress::isValid("  john.doe@example.com\n"));
        self::assertSame('john.doe@example.com', EmailAddress::of("  john.doe@example.com\n")->value);
    }

    public function testParts(): void
    {
        $email = EmailAddress::of('john.doe@example.com');

        self::assertSame('john.doe', $email->localPart());
        self::assertSame('example.com', $email->domain());
    }

    public function testEqualsIgnoresTheCaseOfTheDomainOnly(): void
    {
        $email = EmailAddress::of('john.doe@example.com');

        self::assertTrue($email->equals(EmailAddress::of('john.doe@EXAMPLE.com')));
        self::assertFalse($email->equals(EmailAddress::of('John.Doe@example.com')));
        self::assertFalse($email->equals(EmailAddress::of('john.doe@example.org')));
    }

    public function testOfKeepsAnEmailAddress(): void
    {
        $email = EmailAddress::of('john.doe@example.com');

        self::assertSame($email, EmailAddress::of($email));
    }
}
