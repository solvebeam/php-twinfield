<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

/**
 * Rights of a user on the template companies (`user/office/templatecompanyaccess`).
 *
 * The documentation writes `NoUse`, `UseAndManage` and `UseOnlyForCompanyCreation`; Twinfield
 * returns them in lower case, which is what the SDK sends too.
 */
enum TemplateCompanyAccess: string
{
    /** No access to the template companies. */
    case None = 'nouse';

    /** Use and edit the template companies. */
    case UseAndManage = 'useandmanage';

    /** Only create new companies from a template company, without editing the template. */
    case CompanyCreationOnly = 'useonlyforcompanycreation';

    /**
     * Case-insensitive, so both the documented and the returned spelling work.
     */
    public static function fromXml(string $value): ?self
    {
        return self::tryFrom(strtolower($value));
    }
}
