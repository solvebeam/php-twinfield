<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield;

use InvalidArgumentException;
use Stringable;

/**
 * One email address as Twinfield validates it in the XML web services, e.g. for a user or an
 * address of a customer or supplier.
 *
 *   EmailAddress::of('john.doe@example.com');
 *   EmailAddress::isValid('"John Doe"@example.com');   // false
 *
 * Twinfield has its own rules, which differ from the RFCs (and so from PHP's
 * `FILTER_VALIDATE_EMAIL`):
 *
 * - The local part takes letters, digits and ``! # $ % & ' * + - / = ? ^ _ ` { | } ~``, and a `.`
 *   that is not the first or last character.
 * - The domain takes letters, digits, `-` and `_`, and a `.` that is not the first or last
 *   character and does not follow another `.`.
 * - A quoted local part such as `"John Doe"@example.com` is refused, although the RFCs allow it.
 * - Addresses that are not valid elsewhere are accepted, such as `john.doe@111.222.333.444.555`
 *   or a domain with an underscore.
 * - White space around the address is allowed; the SDK removes it.
 *
 * "Letters" are read as A-Z and a-z: the documentation says "uppercase and lowercase characters"
 * without naming a character set.
 *
 * @see https://developers.twinfield.com/documentation/api/types/guidelines#e-mail-address-validation
 */
final readonly class EmailAddress implements Stringable
{
    /** The `~` is escaped as it is the delimiter of the pattern. */
    private const string LOCAL_CHARACTER = '[A-Za-z0-9!#$%&\'*+\-/=?^_`{|}\~]';

    private const string DOMAIN_LABEL = '[A-Za-z0-9_-]+';

    private function __construct(
        /** The address without white space around it, e.g. `john.doe@example.com`. */
        public string $value,
    ) {
    }

    /**
     * @throws InvalidArgumentException When Twinfield would refuse the address.
     */
    public static function of(self|string $email): self
    {
        if ($email instanceof self) {
            return $email;
        }

        $value = trim($email);

        if (preg_match(self::pattern(), $value) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not an email address.', $email));
        }

        return new self($value);
    }

    public static function isValid(string $email): bool
    {
        return preg_match(self::pattern(), trim($email)) === 1;
    }

    /**
     * The part before the `@`, e.g. `john.doe`.
     */
    public function localPart(): string
    {
        return substr($this->value, 0, (int) strrpos($this->value, '@'));
    }

    /**
     * The part after the `@`, e.g. `example.com`.
     */
    public function domain(): string
    {
        return substr($this->value, (int) strrpos($this->value, '@') + 1);
    }

    /**
     * True for the same address. The domain is compared without regard to case, the local part
     * exactly.
     */
    public function equals(self $other): bool
    {
        return $this->localPart() === $other->localPart()
            && strtolower($this->domain()) === strtolower($other->domain());
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function pattern(): string
    {
        return sprintf(
            '~^%1$s(?:(?:%1$s|\.)*%1$s)?@%2$s(?:\.%2$s)*$~D',
            self::LOCAL_CHARACTER,
            self::DOMAIN_LABEL,
        );
    }
}
