<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use DOMElement;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Period\Period;

/**
 * What the inputs of customers (CustomerInput) and suppliers (SupplierInput) have in common:
 * the general settings, the remittance advice, the lists (addresses, bank accounts, groups,
 * posting rules, payment conditions) and the `<dimension>` request. Every setter returns a
 * new input.
 *
 * The input class has the properties `code`, `name`, `values`, `addresses`, `banks`, `groups`,
 * `postingRules` and `paymentConditions`, and the constants `RELATION` (e.g. `customer`, for
 * messages) and `ELEMENTS` (the elements in `values`, in the order of the documentation).
 *
 * @internal
 */
trait RelationInput
{
    /**
     * @param array<string, mixed> $changes
     */
    abstract private function with(array $changes): self;

    /**
     * Code in the format of the dimension type of the company, e.g. `1100`; null lets
     * Twinfield give the next free code.
     */
    public function code(?string $code): self
    {
        return $this->with(['code' => $code === '' ? null : $code]);
    }

    /**
     * Name, at most 80 characters.
     */
    public function name(string $name): self
    {
        return $this->with(['name' => $name]);
    }

    /**
     * Short name, at most 20 characters.
     */
    public function shortName(?string $shortName): self
    {
        return $this->set('shortname', self::maxLength(self::RELATION . ' short name', $shortName, 20));
    }

    /**
     * Website, at most 40 characters, e.g. `www.example.com`.
     */
    public function website(?string $website): self
    {
        return $this->set('website', self::maxLength(self::RELATION . ' website', $website, 40));
    }

    /**
     * First period it can be used in; null for no limit.
     */
    public function activeFrom(?Period $period): self
    {
        return $this->setMany([
            'beginperiod' => (string) ($period->number ?? 0),
            'beginyear' => (string) ($period->year ?? 0),
        ]);
    }

    /**
     * Last period it can be used in; null for no limit.
     */
    public function activeUntil(?Period $period): self
    {
        return $this->setMany([
            'endperiod' => (string) ($period->number ?? 0),
            'endyear' => (string) ($period->year ?? 0),
        ]);
    }

    /**
     * Payment term in days, e.g. `30`.
     */
    public function dueDays(int $days): self
    {
        if ($days < 0) {
            throw new InvalidArgumentException(sprintf('Due days must be 0 or more, %d given.', $days));
        }

        return $this->set('financials/duedays', (string) $days);
    }

    /**
     * Balance sheet account, e.g. `1300` for a customer or `1600` for a supplier. By default the
     * account of the dimension type.
     */
    public function balanceAccount(string $account): self
    {
        return $this->set('financials/substitutewith', XmlValue::code($account));
    }

    /**
     * Default VAT code, e.g. `VH` or `IH`; `#` for no VAT.
     */
    public function vatCode(?string $vatCode): self
    {
        return $this->set(
            'financials/vatcode',
            $vatCode === null || $vatCode === '' ? null : XmlValue::vatCode($vatCode),
        );
    }

    /**
     * Where to put the remittance advice: in the file manager, or by email to this address
     * (at most 200 characters).
     *
     * @throws InvalidArgumentException For RemittanceAdviceSendType::ByEmail without email address.
     */
    public function remittanceAdvice(RemittanceAdviceSendType $sendType, ?string $email = null): self
    {
        $email = $email === '' ? null : $email;

        if ($sendType === RemittanceAdviceSendType::ByEmail && $email === null) {
            throw new InvalidArgumentException('A remittance advice by email needs an email address.');
        }

        return $this->setMany([
            'remittanceadvice/sendtype' => $sendType->value,
            'remittanceadvice/sendmail' => self::maxLength('remittance advice email', $email, 200),
        ]);
    }

    /**
     * Adds an address, or replaces the address with the same id. A default address makes the
     * other addresses non-default.
     */
    public function address(AddressInput $address): self
    {
        $addresses = $this->addresses ?? [];

        if ($address->default) {
            $addresses = array_map(static fn (AddressInput $other): AddressInput => $other->default(false), $addresses);
        }

        return $this->with(['addresses' => self::replaceOrAppend($addresses, $address)]);
    }

    /**
     * Replaces all addresses; addresses you leave out are removed.
     */
    public function addresses(AddressInput ...$addresses): self
    {
        return $this->with(['addresses' => array_values($addresses)]);
    }

    /**
     * Removes the address with this id. Start from edit().
     *
     * @throws InvalidArgumentException When there is no address with this id.
     */
    public function withoutAddress(int $id): self
    {
        return $this->with(['addresses' => self::remove($this->addresses, $id, 'address', 'addresses')]);
    }

    /**
     * Adds a bank account, or replaces the account with the same id. A default account makes
     * the other accounts non-default.
     */
    public function bank(BankInput $bank): self
    {
        $banks = $this->banks ?? [];

        if ($bank->default) {
            $banks = array_map(static fn (BankInput $other): BankInput => $other->default(false), $banks);
        }

        return $this->with(['banks' => self::replaceOrAppend($banks, $bank)]);
    }

    /**
     * Replaces all bank accounts; accounts you leave out are removed.
     */
    public function banks(BankInput ...$banks): self
    {
        return $this->with(['banks' => array_values($banks)]);
    }

    /**
     * Removes the bank account with this id. Start from edit().
     *
     * @throws InvalidArgumentException When there is no bank account with this id.
     */
    public function withoutBank(int $id): self
    {
        return $this->with(['banks' => self::remove($this->banks, $id, 'bank account', 'bank accounts')]);
    }

    /**
     * Adds it to a dimension group.
     */
    public function group(string $group): self
    {
        return $this->groups(...array_unique([...$this->groups ?? [], $group]));
    }

    /**
     * Replaces all dimension groups.
     */
    public function groups(string ...$groups): self
    {
        foreach ($groups as $group) {
            XmlValue::code($group);
        }

        return $this->with(['groups' => array_values($groups)]);
    }

    /**
     * Adds a posting rule, or replaces the rule with the same id.
     */
    public function postingRule(PostingRuleInput $rule): self
    {
        return $this->with(['postingRules' => self::replaceOrAppend($this->postingRules ?? [], $rule)]);
    }

    /**
     * Sets the posting rules to send. To delete an existing rule, use withoutPostingRule().
     */
    public function postingRules(PostingRuleInput ...$rules): self
    {
        return $this->with(['postingRules' => array_values($rules)]);
    }

    /**
     * Deletes the posting rule with this id. Start from edit().
     *
     * @throws InvalidArgumentException When there is no posting rule with this id.
     */
    public function withoutPostingRule(int $id): self
    {
        foreach ($this->postingRules ?? [] as $index => $rule) {
            if ($rule->id === $id) {
                $rules = $this->postingRules;
                $rules[$index] = $rule->deleted();

                return $this->with(['postingRules' => $rules]);
            }
        }

        throw new InvalidArgumentException(sprintf(
            'There is no posting rule %d: start from $%s->edit().',
            $id,
            self::RELATION,
        ));
    }

    /**
     * Adds a payment discount, e.g. `paymentCondition(8, '2')` for 2% within 8 days.
     *
     * @param numeric-string $discountPercentage
     */
    public function paymentCondition(int $discountDays, string $discountPercentage): self
    {
        return $this->paymentConditions(
            ...[...$this->paymentConditions ?? [], PaymentCondition::of($discountDays, $discountPercentage)],
        );
    }

    /**
     * Replaces all payment discounts; `paymentConditions()` without arguments removes them.
     */
    public function paymentConditions(PaymentCondition ...$conditions): self
    {
        return $this->with(['paymentConditions' => array_values($conditions)]);
    }

    /**
     * The value that will be sent for an element, e.g. `value('financials/duedays')`, or null
     * when it is empty or not set. See has() to tell these apart.
     */
    public function value(string $element): ?string
    {
        return $this->values[$element] ?? null;
    }

    /**
     * True when the element will be sent, e.g. `has('financials/duedays')`.
     */
    public function has(string $element): bool
    {
        return array_key_exists($element, $this->values);
    }

    /**
     * Checks the values every input has; call it from the constructor.
     *
     * @param ?list<AddressInput> $addresses
     * @param ?list<BankInput> $banks
     */
    private static function validate(?string $code, string $name, ?array $addresses, ?array $banks): void
    {
        if ($code !== null) {
            XmlValue::code($code);
        }

        if ($name === '') {
            throw new InvalidArgumentException(sprintf('%s name must not be empty.', ucfirst(self::RELATION)));
        }

        XmlValue::maxLength(self::RELATION . ' name', $name, 80);

        if (count(array_filter($addresses ?? [], static fn (AddressInput $address): bool => $address->default)) > 1) {
            throw new InvalidArgumentException(sprintf('A %s has at most one default address.', self::RELATION));
        }

        if (count(array_filter($banks ?? [], static fn (BankInput $bank): bool => $bank->default)) > 1) {
            throw new InvalidArgumentException(sprintf('A %s has at most one default bank account.', self::RELATION));
        }
    }

    /**
     * The `<dimension>` request: the values in the order of ELEMENTS, then the lists that are set.
     *
     * @param string $type Dimension type, e.g. `DEB`.
     */
    private function dimensionXml(string $company, string $type): XmlBuilder
    {
        $xml = XmlBuilder::create('dimension');
        $dimension = $xml->root();

        $xml->add($dimension, 'office', $company);
        $xml->add($dimension, 'type', $type);

        if ($this->code !== null) {
            $xml->add($dimension, 'code', $this->code);
        }

        $xml->add($dimension, 'name', $this->name);

        // Fixed values for customers and suppliers, according to the documentation.
        $financials = $xml->document()->createElement('financials');
        $xml->add($financials, 'matchtype', 'customersupplier');
        $xml->add($financials, 'accounttype', 'inherit');
        $xml->add($financials, 'subanalyse', 'false');
        $xml->add($financials, 'substitutionlevel', '1');

        /** @var array<string, DOMElement> $parents */
        $parents = ['financials' => $financials];

        foreach (self::ELEMENTS as $path) {
            if (!array_key_exists($path, $this->values)) {
                continue;
            }

            // <financials> follows the dimension fields, also when none of its values are set.
            if (str_contains($path, '/') && $financials->parentNode === null) {
                $dimension->appendChild($financials);
            }

            $names = explode('/', $path);
            $name = array_pop($names);
            $parent = $dimension;
            $parentPath = '';

            foreach ($names as $parentName) {
                $parentPath .= ($parentPath === '' ? '' : '/') . $parentName;
                $parents[$parentPath] ??= $xml->add($parent, $parentName);
                $parent = $parents[$parentPath];
            }

            $xml->add($parent, $name, $this->values[$path]);
        }

        if ($financials->parentNode === null) {
            $dimension->appendChild($financials);
        }

        if ($this->addresses !== null) {
            $element = $xml->add($dimension, 'addresses');

            foreach ($this->addresses as $address) {
                $address->appendTo($xml, $element);
            }
        }

        if ($this->banks !== null) {
            $element = $xml->add($dimension, 'banks');

            foreach ($this->banks as $bank) {
                $bank->appendTo($xml, $element);
            }
        }

        if ($this->groups !== null) {
            $element = $xml->add($dimension, 'groups');

            foreach ($this->groups as $group) {
                $xml->add($element, 'group', $group);
            }
        }

        if ($this->postingRules !== null) {
            $element = $xml->add($dimension, 'postingrules');

            foreach ($this->postingRules as $rule) {
                $rule->appendTo($xml, $element);
            }
        }

        if ($this->paymentConditions !== null) {
            $element = $xml->add($dimension, 'paymentconditions');

            foreach ($this->paymentConditions as $condition) {
                $item = $xml->add($element, 'paymentcondition');
                $xml->add($item, 'discountdays', (string) $condition->discountDays);
                $xml->add($item, 'discountpercentage', $condition->discountPercentage);
            }
        }

        return $xml;
    }

    private function set(string $element, ?string $value): self
    {
        return $this->setMany([$element => $value]);
    }

    /**
     * @param array<string, ?string> $values
     */
    private function setMany(array $values): self
    {
        return $this->with(['values' => [...$this->values, ...$values]]);
    }

    private static function maxLength(string $field, ?string $value, int $max): ?string
    {
        return $value === null || $value === '' ? null : XmlValue::maxLength($field, $value, $max);
    }

    private static function nullableCode(?string $code): ?string
    {
        return $code === null || $code === '' ? null : XmlValue::code($code);
    }

    /**
     * @template T of AddressInput|BankInput|PostingRuleInput
     *
     * @param list<T> $items
     * @param T $item
     *
     * @return list<T>
     */
    private static function replaceOrAppend(array $items, AddressInput|BankInput|PostingRuleInput $item): array
    {
        if ($item->id !== null) {
            foreach ($items as $index => $existing) {
                if ($existing->id === $item->id) {
                    $items[$index] = $item;

                    return $items;
                }
            }
        }

        $items[] = $item;

        return $items;
    }

    /**
     * @template T of AddressInput|BankInput
     *
     * @param ?list<T> $items
     *
     * @return list<T>
     */
    private static function remove(?array $items, int $id, string $label, string $plural): array
    {
        if ($items === null) {
            throw new LogicException(sprintf(
                'There are no %s to remove from: start from $%s->edit().',
                $plural,
                self::RELATION,
            ));
        }

        $remaining = array_values(array_filter(
            $items,
            static fn (AddressInput|BankInput $item): bool => $item->id !== $id,
        ));

        if (count($remaining) === count($items)) {
            throw new InvalidArgumentException(sprintf('There is no %s %d.', $label, $id));
        }

        return $remaining;
    }
}
