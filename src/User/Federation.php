<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Login through the identity provider of the organisation (`user/federation`). Twinfield
 * Support enables this on request.
 */
final readonly class Federation
{
    public function __construct(
        /** Domain of the identity provider registered in the Wolters Kluwer account. */
        public string $domain,
        /** Email address of the user at the identity provider. */
        public string $email,
    ) {
        if ($domain === '' || $email === '') {
            throw new InvalidArgumentException('Federation domain and email must not be empty.');
        }
    }

    /**
     * @internal
     */
    public static function fromXml(XmlElement $federation): self
    {
        return new self($federation->string('domain'), $federation->string('email'));
    }
}
