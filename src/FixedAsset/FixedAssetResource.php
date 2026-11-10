<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use InvalidArgumentException;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Dimension\DimensionRequests;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Twinfield;

/**
 * Fixed assets (dimension type `AST`) of the company: the third dimension (`dim3`) of
 * transaction lines on investment accounts.
 *
 *   $fixedAssets = $twinfield->company('1000')->fixedAssets();
 *
 *   foreach ($fixedAssets->search()->group('VEHICLES')->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $asset = $fixedAssets->get('60000');
 *
 *   $created = $fixedAssets->create(
 *       FixedAssetInput::create('Delivery van')
 *           ->method('5YLIN')
 *           ->costCenter('00000')
 *           ->purchaseDate(new DateTimeImmutable('2026-03-15'))
 *           ->depreciateFrom(Period::of(2026, 4)),
 *   );
 *
 *   $fixedAssets->update($asset->edit()->residualValue('1000.00'));
 *   $fixedAssets->delete($created->code);
 *
 * Twinfield refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/fixed-assets/
 */
final readonly class FixedAssetResource
{
    private DimensionRequests $requests;

    public function __construct(Twinfield $twinfield)
    {
        $this->requests = new DimensionRequests($twinfield, 'AST', 'Fixed asset', 'Fixed assets');
    }

    /**
     * Search fixed assets by code and name, e.g. `search('6*')->inCode()->get()`, or by
     * dimension group: `search()->group('VEHICLES')`.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<FixedAssetSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        return $this->requests->search($pattern, FixedAssetSummary::fromFinderRow(...));
    }

    /**
     * @throws DimensionNotFoundException When there is no fixed asset with this code.
     */
    public function get(string $code): FixedAsset
    {
        return FixedAsset::fromXml($this->requests->read($code), $this->requests->organisation());
    }

    /**
     * The fixed asset, or null when there is no fixed asset with this code.
     */
    public function find(string $code): ?FixedAsset
    {
        try {
            return $this->get($code);
        } catch (DimensionNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new fixed asset. Without a code, Twinfield gives it the next free code; with a
     * code, this reads first, so an existing asset is never overwritten.
     *
     * @throws DimensionAlreadyExistsException When the code is already in use.
     * @throws ProcessXmlException When Twinfield refuses the asset.
     */
    public function create(FixedAssetInput $asset): FixedAsset
    {
        if ($asset->code !== null && $this->find($asset->code) !== null) {
            throw new DimensionAlreadyExistsException('AST', $asset->code);
        }

        return $this->save($asset);
    }

    /**
     * Updates an existing fixed asset. Start from `$asset->edit()`.
     *
     * @throws InvalidArgumentException When the input has no code.
     * @throws DimensionNotFoundException When there is no fixed asset with this code.
     * @throws ProcessXmlException When Twinfield refuses the changes.
     */
    public function update(FixedAssetInput $asset): FixedAsset
    {
        if ($asset->code === null) {
            throw new InvalidArgumentException('Updating a fixed asset needs its code: start from $asset->edit().');
        }

        $this->get($asset->code);

        return $this->save($asset);
    }

    /**
     * Creates the fixed asset, or updates it when the code exists (one request, no check).
     *
     * @throws ProcessXmlException When Twinfield refuses the asset.
     */
    public function save(FixedAssetInput $asset): FixedAsset
    {
        $xml = $asset->toXml($this->requests->company());

        return FixedAsset::fromXml($this->requests->save($xml), $this->requests->organisation());
    }

    /**
     * Deletes the fixed asset. When it is used in a transaction, Twinfield hides it instead.
     *
     * @throws DimensionNotFoundException When there is no fixed asset with this code.
     */
    public function delete(string $code): DeleteResult
    {
        return $this->requests->delete($code);
    }

    /**
     * Makes a deleted or hidden fixed asset active again.
     *
     * @throws DimensionNotFoundException When there is no fixed asset with this code.
     */
    public function activate(string $code): void
    {
        $this->requests->activate($code);
    }
}
