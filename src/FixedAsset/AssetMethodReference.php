<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * The asset method of a fixed asset, with the name Twinfield adds, e.g.
 * `<method name="5 years linear" shortname="5YLIN">5YLIN</method>`.
 *
 * For the depreciation accounts and free text labels, read the method itself:
 *
 *   $method = $twinfield->assetMethods()->get($asset->method->code);
 */
final readonly class AssetMethodReference
{
    public function __construct(
        /** Asset method code, e.g. `5YLIN`. */
        public string $code,
        public string $name,
        /** Short name; null when not set. */
        public ?string $shortName,
    ) {
        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('Asset method code and name must not be empty.');
        }

        if ($shortName === '') {
            throw new InvalidArgumentException('Asset method short name must not be empty: use null for none.');
        }
    }

    /**
     * From the `<method>` element; null when it is empty, which Twinfield allows for assets
     * whose method was removed or never set.
     */
    public static function fromXml(XmlElement $method): ?self
    {
        if ($method->text() === '') {
            return null;
        }

        $name = $method->attribute('name');

        if ($name === '') {
            throw new UnexpectedResponseException(sprintf('%s/@name is empty.', $method->path));
        }

        $shortName = $method->attribute('shortname');

        return new self($method->text(), $name, $shortName === '' ? null : $shortName);
    }
}
