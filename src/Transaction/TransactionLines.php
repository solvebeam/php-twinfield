<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * The `<lines>` of a transaction response, by line type.
 *
 * @internal
 */
final readonly class TransactionLines
{
    /**
     * @param list<DetailLine> $details
     * @param list<VatLine> $vat
     */
    private function __construct(
        public ?TotalLine $total,
        public array $details,
        public array $vat,
    ) {
    }

    public static function fromXml(XmlElement $lines): self
    {
        $total = null;
        $details = [];
        $vat = [];

        foreach ($lines->list('line') as $line) {
            if (preg_match('~^\d+$~', $line->attribute('id')) !== 1) {
                throw new UnexpectedResponseException(sprintf(
                    '%s/@id is "%s", expected an integer.',
                    $line->path,
                    $line->attribute('id'),
                ));
            }

            switch ($line->enumAttribute('type', LineType::class)) {
                case LineType::Total:
                    if ($total !== null) {
                        throw new UnexpectedResponseException(
                            sprintf('%s has more than one total line.', $lines->path),
                        );
                    }

                    $total = TotalLine::fromXml($line);
                    break;
                case LineType::Detail:
                    $details[] = DetailLine::fromXml($line);
                    break;
                case LineType::Vat:
                    $vat[] = VatLine::fromXml($line);
                    break;
            }
        }

        return new self($total, $details, $vat);
    }

    public function requireTotal(XmlElement $lines): TotalLine
    {
        return $this->total ?? throw new UnexpectedResponseException(sprintf('%s has no total line.', $lines->path));
    }
}
