<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Remittance advice of a customer or supplier (`remittanceadvice`): where Twinfield puts the
 * specification of a payment.
 */
final readonly class RemittanceAdvice
{
    public function __construct(
        public RemittanceAdviceSendType $sendType,
        /** Email address when sent by email; null when not set. */
        public ?string $email,
    ) {
    }

    /**
     * @internal
     */
    public static function fromXml(XmlElement $advice): self
    {
        return new self(
            $advice->enum('sendtype', RemittanceAdviceSendType::class),
            $advice->nullableString('sendmail'),
        );
    }
}
