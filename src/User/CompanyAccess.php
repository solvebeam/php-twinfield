<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

use SolveBeam\Twinfield\Office\NamedReference;
use SolveBeam\Twinfield\Office\Office;
use SolveBeam\Twinfield\Office\OfficeSummary;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The companies (offices) a user has access to (`user/office`).
 *
 *   if ($user->companies->hasAccessTo('1000')) { … }
 *
 * A user is either restricted to the listed companies, or has access to all companies of the
 * organisation; the list is then empty.
 */
final readonly class CompanyAccess
{
    /**
     * @param list<NamedReference> $companies
     */
    public function __construct(
        /** Organisation of the user: the companies are companies of this organisation. */
        public Organisation $organisation,
        /** True when the user only has access to $companies; false for access to all companies. */
        public bool $restricted,
        /** The companies of a restricted user, with their name. Empty when not restricted. */
        public array $companies,
        /** Company opened after logging in; null to let the user choose. */
        public ?NamedReference $defaultCompany,
        public TemplateCompanyAccess $templateCompanyAccess,
    ) {
    }

    /**
     * @internal
     */
    public static function fromXml(XmlElement $office, Organisation $organisation): self
    {
        $templateCompanyAccess = $office->child('templatecompanyaccess');

        return new self(
            organisation: $organisation,
            // Documented as "not in use"; live it is false with an empty list for users with access to all companies.
            restricted: $office->bool('restricted'),
            companies: array_map(
                static fn (XmlElement $company): NamedReference => NamedReference::fromXml($company),
                $office->child('offices')->list('office'),
            ),
            defaultCompany: NamedReference::nullable($office->child('default')),
            templateCompanyAccess: TemplateCompanyAccess::fromXml($templateCompanyAccess->text())
                ?? throw $templateCompanyAccess->invalid('nouse, useandmanage or useonlyforcompanycreation'),
        );
    }

    /**
     * True when the user may open the company: always for a user that is not restricted. Never
     * for a company of another organisation.
     */
    public function hasAccessTo(string|CompanyReference|Office|OfficeSummary $company): bool
    {
        if (is_string($company)) {
            $code = $company;
        } else {
            $reference = $company instanceof CompanyReference ? $company : $company->reference();

            if (!$reference->organisation->equals($this->organisation)) {
                return false;
            }

            $code = $reference->code;
        }

        return !$this->restricted || in_array($code, $this->codes(), true);
    }

    /**
     * Codes of the companies of a restricted user, e.g. `['1000', '1001']`.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map(static fn (NamedReference $company): string => $company->code, $this->companies);
    }
}
