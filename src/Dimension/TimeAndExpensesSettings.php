<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use DateTimeInterface;

/**
 * Fluent setters for the time & expenses settings (the `projects` element) of an input that
 * has them, such as ActivityInput and ProjectInput. Every setter returns a new input.
 *
 * Settings with ChangeMode::Inherit come from another level: an activity inherits from its
 * project or the user, a project from the activity or the user.
 *
 * @internal
 */
trait TimeAndExpensesSettings
{
    /**
     * Replaces all time & expenses settings at once.
     */
    abstract public function projects(ProjectSettings $projects): self;

    /**
     * First date users can book hours; null for no limit.
     */
    public function validFrom(?DateTimeInterface $date): self
    {
        return $this->withProjects(['validFrom' => $date]);
    }

    /**
     * Last date users can book hours; null for no limit.
     */
    public function validTill(?DateTimeInterface $date): self
    {
        return $this->withProjects(['validTill' => $date]);
    }

    /**
     * Longer description for the invoice template, at most 128 characters. Null removes it.
     */
    public function invoiceDescription(?string $description): self
    {
        return $this->withProjects(['invoiceDescription' => $description === '' ? null : $description]);
    }

    /**
     * User who authorises the hours, e.g. `authoriser('SUPER', ChangeMode::Disallow)`.
     * `authoriser(null, ChangeMode::Inherit)` inherits it.
     */
    public function authoriser(?string $user, ChangeMode $change = ChangeMode::Allow): self
    {
        return $this->withProjects(['authoriser' => new CodeSetting($user, $change)]);
    }

    /**
     * Customer to invoice the hours to, e.g. `customer('1010')`.
     * `customer(null, ChangeMode::Inherit)` inherits it.
     */
    public function customer(?string $customer, ChangeMode $change = ChangeMode::Allow): self
    {
        return $this->withProjects(['customer' => new CodeSetting($customer, $change)]);
    }

    /**
     * Rate code for the hours, e.g. `rate('T001', ChangeMode::Disallow)`.
     * `rate(null, ChangeMode::Inherit)` inherits it.
     */
    public function rate(?string $rate, ChangeMode $change = ChangeMode::Allow): self
    {
        return $this->withProjects(['rate' => new CodeSetting($rate, $change)]);
    }

    /**
     * Whether hours are billable, and whether they count for the productivity ratio.
     * `billable(null, ChangeMode::Inherit)` inherits it.
     */
    public function billable(
        ?bool $billable = true,
        ChangeMode $change = ChangeMode::Allow,
        bool $forRatio = false,
    ): self {
        return $this->withProjects(['billable' => new BillableSetting($billable, $change, $forRatio)]);
    }

    /**
     * Adds an extra quantity, such as kilometres.
     */
    public function quantity(Quantity $quantity): self
    {
        return $this->withProjects(['quantities' => [...$this->projects->quantities, $quantity]]);
    }

    /**
     * Replaces all quantities; `quantities()` without arguments removes them.
     */
    public function quantities(Quantity ...$quantities): self
    {
        return $this->withProjects(['quantities' => array_values($quantities)]);
    }

    /**
     * @param array{
     *     validFrom?: ?DateTimeInterface,
     *     validTill?: ?DateTimeInterface,
     *     invoiceDescription?: ?string,
     *     authoriser?: CodeSetting,
     *     customer?: CodeSetting,
     *     billable?: BillableSetting,
     *     rate?: CodeSetting,
     *     quantities?: list<Quantity>,
     * } $changes
     */
    private function withProjects(array $changes): self
    {
        $projects = $this->projects;

        return $this->projects(new ProjectSettings(
            validFrom: array_key_exists('validFrom', $changes) ? $changes['validFrom'] : $projects->validFrom,
            validTill: array_key_exists('validTill', $changes) ? $changes['validTill'] : $projects->validTill,
            invoiceDescription: array_key_exists('invoiceDescription', $changes)
                ? $changes['invoiceDescription']
                : $projects->invoiceDescription,
            authoriser: $changes['authoriser'] ?? $projects->authoriser,
            customer: $changes['customer'] ?? $projects->customer,
            billable: $changes['billable'] ?? $projects->billable,
            rate: $changes['rate'] ?? $projects->rate,
            quantities: $changes['quantities'] ?? $projects->quantities,
        ));
    }
}
