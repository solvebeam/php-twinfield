<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration;

use InvalidArgumentException;
use SolveBeam\Twinfield\Office\NamedReference;
use SolveBeam\Twinfield\Organisation\CompanyReference;

/**
 * A VAT, ICT or tax group return, as listed by `declarations()->all()` and `list()`.
 *
 *   foreach ($twinfield->company('1000')->declarations()->list(year: 2026) as $declaration) {
 *       echo $declaration->id, ' ', $declaration->name, ': ', $declaration->status->description, PHP_EOL;
 *   }
 */
final readonly class DeclarationSummary
{
    public function __construct(
        /** Document ID, to download the return or change its status. */
        public int $id,
        public DeclarationType $type,
        /** E.g. `Btw-aangifte 2026 Kwartaal jan t/m mrt`, in the language of the user. */
        public string $name,
        public int $year,
        /** E.g. `jan`, `Kwartaal jan t/m mrt` or `Jaaropgave`, in the language of the user. */
        public string $period,
        public DeclarationStatus $status,
        /** User the return is assigned to. */
        public NamedReference $assignee,
        /** The company of the return; with `*` this is not the company of the client. */
        public CompanyReference $company,
        public string $companyName,
    ) {
        foreach (['name' => $name, 'period' => $period, 'companyName' => $companyName] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Declaration %s must not be empty.', $field));
            }
        }
    }
}
