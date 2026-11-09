<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DimensionGroup;

use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A dimension group of the company with its dimensions, as Twinfield returns it.
 *
 *   $group = $twinfield->dimensionGroups()->get('NL-CUSTOMERS');
 *   echo $group->code, ' ', $group->name, PHP_EOL;
 *
 *   foreach ($group->dimensions as $dimension) {
 *       echo $dimension->type, ' ', $dimension->code, ' ', $dimension->name, PHP_EOL;
 *   }
 *
 * To change it, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/dimension-groups/
 */
final readonly class DimensionGroup
{
    /**
     * @param list<DimensionGroupDimension> $dimensions In the order of Twinfield; each type and code once.
     */
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        /** Dimension group code, e.g. `NL-CUSTOMERS`. */
        public string $code,
        /** Name, at most 40 characters. */
        public string $name,
        /** Short name, at most 20 characters; null when it is not set. */
        public ?string $shortName,
        public DimensionGroupStatus $status,
        /** Number of times Twinfield processed the group. Every read counts as well. */
        public int $touched,
        /** Dimensions in the group, of one or more dimension types. */
        public array $dimensions,
    ) {
        foreach (['code' => $code, 'name' => $name] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Dimension group %s must not be empty.', $field));
            }
        }

        if ($touched < 0) {
            throw new InvalidArgumentException('Dimension group touched count must be 0 or more.');
        }

        $keys = array_map(
            static fn (DimensionGroupDimension $dimension): string => $dimension->type . ' ' . $dimension->code,
            $dimensions,
        );
        $duplicates = array_keys(array_filter(array_count_values($keys), static fn (int $count): bool => $count > 1));

        if ($duplicates !== []) {
            throw new InvalidArgumentException(sprintf(
                'Dimension group %s has %s more than once.',
                $code,
                implode(', ', $duplicates),
            ));
        }
    }

    /**
     * From a `<dimensiongroup>` element of a save response.
     */
    public static function fromXml(XmlElement $group, Organisation $organisation): self
    {
        // A group without dimensions has an empty <dimensions />, or (right after a delete) none.
        $dimensions = $group->nullableChild('dimensions')?->list('dimension') ?? [];

        return new self(
            company: new CompanyReference($organisation, $group->string('office')),
            code: $group->string('code'),
            name: $group->string('name'),
            shortName: $group->nullableString('shortname'),
            status: $group->enumAttribute('status', DimensionGroupStatus::class),
            touched: $group->int('touched'),
            dimensions: array_map(DimensionGroupDimension::fromXml(...), $dimensions),
        );
    }

    /**
     * Whether the dimension with this type and code is in the group, e.g. `has('DEB', '1000')`.
     */
    public function has(string $type, string $code): bool
    {
        foreach ($this->dimensions as $dimension) {
            if ($dimension->is($type, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Codes of the dimensions in the group, optionally of one dimension type only.
     *
     *   $customerCodes = $group->codes('DEB');
     *
     * @return list<string>
     */
    public function codes(?string $type = null): array
    {
        $codes = [];

        foreach ($this->dimensions as $dimension) {
            if ($type === null || $dimension->type === $type) {
                $codes[] = $dimension->code;
            }
        }

        return $codes;
    }

    /**
     * Dimension types in the group, in order of appearance, e.g. `['DEB']`.
     *
     * @return list<string>
     */
    public function types(): array
    {
        return array_values(array_unique(array_map(
            static fn (DimensionGroupDimension $dimension): string => $dimension->type,
            $this->dimensions,
        )));
    }

    /**
     * Input with all values of this group, including its dimensions, to change and pass to
     * update() or save().
     *
     *   $groups->update($group->edit()->add('DEB', '1003')->remove('DEB', '1000'));
     */
    public function edit(): DimensionGroupInput
    {
        $input = DimensionGroupInput::create($this->code, $this->name)->shortName($this->shortName);

        foreach ($this->dimensions as $dimension) {
            $input = $input->add($dimension->type, $dimension->code);
        }

        return $input;
    }
}
