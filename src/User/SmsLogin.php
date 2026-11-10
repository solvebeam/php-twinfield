<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Logging in with a code by text message (`user/smslogin`).
 */
final readonly class SmsLogin
{
    public function __construct(
        /** True when the SMS logon package is enabled for the user. */
        public bool $enabled,
        /** Phone number the code is sent to; null when not set. */
        public ?string $phoneNumber,
    ) {
    }

    /**
     * @internal
     */
    public static function fromXml(XmlElement $smsLogin): self
    {
        $service = $smsLogin->child('service');

        // 0 is disabled; the documentation has 1 for enabled, live <services> also lists other packages.
        if (!ctype_digit($service->text())) {
            throw $service->invalid('a number (0 is disabled)');
        }

        return new self($service->text() !== '0', $smsLogin->nullableString('phonenumber'));
    }
}
