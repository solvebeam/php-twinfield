<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CostCenter;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Dimension\DimensionBehaviour;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A cost center (dimension type `KPL`) as Twinfield returns it.
 *
 *   $costCenter = $twinfield->costCenters()->get('100');
 *   echo $costCenter->code, ' ', $costCenter->name, PHP_EOL;
 *
 * To change it, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/cost-centers/
 */
final readonly class CostCenter
{
    public function __construct(
        /** Company (office) code. */
        public string $company,
        /** Code in the format of the KPL dimension type of the company, e.g. `100`. */
        public string $code,
        /** Unique ID of the dimension, e.g. `5f3c2a1e-7b4d-4e8f-9a6b-1c2d3e4f5a6b`. */
        public string $uid,
        /** Name, at most 80 characters. */
        public string $name,
        public DimensionStatus $status,
        /** True when the cost center is used in a transaction. Deleting it then hides it. */
        public bool $inUse,
        public DimensionBehaviour $behaviour,
        /** Number of times the settings were changed. */
        public int $touched,
        /** Last change. Null when Twinfield leaves it out: it is not in every documented response. */
        public ?DateTimeImmutable $modified,
    ) {
        foreach (['company' => $company, 'code' => $code, 'uid' => $uid, 'name' => $name] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Cost center %s must not be empty.', $field));
            }
        }

        if ($touched < 0) {
            throw new InvalidArgumentException('Cost center touched count must be 0 or more.');
        }
    }

    /**
     * From a `<dimension>` element of a read or save response.
     */
    public static function fromXml(XmlElement $dimension): self
    {
        $type = $dimension->string('type');

        if ($type !== 'KPL') {
            throw $dimension->child('type')->invalid('KPL');
        }

        return new self(
            company: $dimension->string('office'),
            code: $dimension->string('code'),
            uid: $dimension->string('uid'),
            name: $dimension->string('name'),
            status: $dimension->enumAttribute('status', DimensionStatus::class),
            inUse: $dimension->bool('inuse'),
            behaviour: $dimension->enum('behaviour', DimensionBehaviour::class),
            touched: $dimension->int('touched'),
            // Not in the save response of the documentation, but in every read response.
            modified: $dimension->nullableChild('modified') === null ? null : $dimension->dateTime('modified'),
        );
    }

    /**
     * Input with all values of this cost center, to change and pass to update() or save().
     *
     *   $costCenters->update($costCenter->edit()->name('Marketing'));
     */
    public function edit(): CostCenterInput
    {
        return CostCenterInput::create($this->code, $this->name);
    }
}
