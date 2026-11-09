<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A Twinfield office (company, administration) with all its settings, as Twinfield returns it.
 *
 *   $office = $twinfield->offices()->get('1000');
 *
 *   echo $office->code, ' ', $office->name, PHP_EOL;
 *   echo $office->general->baseCurrency->code, ' ', $office->general->vatNumber, PHP_EOL;
 *   echo $office->systemDimensions->accountsReceivable?->code, PHP_EOL;
 *
 * Offices are read-only in the API. Twinfield is moving from the term "office" to "company";
 * this class keeps the old name for now.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/offices
 */
final readonly class Office
{
    /**
     * @param list<OfficeField> $lockedFields
     */
    public function __construct(
        /** Organisation the office belongs to; the office code is only unique within it. */
        public Organisation $organisation,
        /** Office code, e.g. `1000`; pass it (or this object) to `$twinfield->company()`. */
        public string $code,
        public string $name,
        /** Null when not set. */
        public ?string $shortName,
        public OfficeStatus $status,
        public DateTimeImmutable $created,
        /** Last change of the settings. */
        public DateTimeImmutable $modified,
        /** Number of times the settings were changed. */
        public int $touched,
        /** User who last changed the settings. */
        public NamedReference $user,
        public OfficeGeneral $general,
        public SystemDimensions $systemDimensions,
        public SystemDimensionTypes $systemDimensionTypes,
        public CreditManagement $creditManagement,
        public VatManagement $vatManagement,
        public FixedAssetSettings $fixedAssets,
        public Intercompany $intercompany,
        public RegionalSettings $regional,
        /** Settings Twinfield does not allow to change now. */
        public array $lockedFields,
    ) {
        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('Office code and name must not be empty.');
        }

        if ($touched < 0) {
            throw new InvalidArgumentException('Office touched count must be 0 or more.');
        }
    }

    /**
     * From the `<office>` root of a read response.
     *
     * @param Organisation $organisation Organisation of the access token: Twinfield leaves it out
     *                                   of the response.
     */
    public static function fromXml(XmlElement $office, Organisation $organisation): self
    {
        $lockedFields = array_values(array_filter(
            OfficeField::cases(),
            static fn (OfficeField $field): bool => self::element($office, $field)->boolAttribute('locked'),
        ));

        // The short name is optional in Twinfield.
        $shortName = $office->nullableString('shortname');

        return new self(
            organisation: $organisation,
            code: $office->string('code'),
            name: $office->string('name'),
            shortName: $shortName,
            status: $office->enumAttribute('status', OfficeStatus::class),
            created: $office->dateTime('created'),
            modified: $office->dateTime('modified'),
            touched: $office->int('touched'),
            user: NamedReference::fromXml($office->child('user')),
            general: OfficeGeneral::fromXml($office->child('general')),
            systemDimensions: SystemDimensions::fromXml($office->child('systemdimensions')),
            systemDimensionTypes: SystemDimensionTypes::fromXml($office->child('systemdimensiontypes')),
            creditManagement: CreditManagement::fromXml($office->child('creditmanagement')),
            vatManagement: VatManagement::fromXml($office->child('vatmanagement')),
            fixedAssets: FixedAssetSettings::fromXml($office->child('fixedassets')),
            intercompany: Intercompany::fromXml($office->child('intercompany')),
            regional: RegionalSettings::fromXml($office->child('regional')),
            lockedFields: $lockedFields,
        );
    }

    /**
     * The organisation and code of the office.
     */
    public function reference(): CompanyReference
    {
        return new CompanyReference($this->organisation, $this->code);
    }

    public function isLocked(OfficeField $field): bool
    {
        return in_array($field, $this->lockedFields, true);
    }

    private static function element(XmlElement $office, OfficeField $field): XmlElement
    {
        $element = $office;

        foreach (explode('/', $field->value) as $name) {
            $element = $element->child($name);
        }

        return $element;
    }
}
