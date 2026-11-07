<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Customer;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Remittance advice of a customer (`remittanceadvice`).
 */
final readonly class RemittanceAdvice
{
    public function __construct(
        public RemittanceAdviceSendType $sendType,
        /** Email address when sent by email; null when not set. */
        public ?string $email,
    ) {
    }

    public static function fromXml(XmlElement $advice): self
    {
        return new self(
            $advice->enum('sendtype', RemittanceAdviceSendType::class),
            $advice->nullableString('sendmail'),
        );
    }
}
