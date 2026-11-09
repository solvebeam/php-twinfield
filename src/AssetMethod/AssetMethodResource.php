<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\AssetMethod;

use LogicException;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Asset methods of the company: how fixed assets are depreciated and on which accounts.
 *
 *   $assetMethods = $twinfield->company('1000')->assetMethods();
 *
 *   foreach ($assetMethods->search()->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $method = $assetMethods->get('MACHINE');
 *
 *   $assetMethods->create(AssetMethodInput::create('LAPTOP', 'Laptops')->linear(36));
 *   $assetMethods->update($method->edit()->name('Machines'));
 *   $assetMethods->delete('LAPTOP');
 *
 * Twinfield refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/asset-methods/
 */
final readonly class AssetMethodResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Search asset methods by code and name, e.g. `search('MACH*')->limit(10)->get()`.
     *
     * Finder type `ASM`; the company comes from the client, so no `office` option is sent.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<AssetMethodSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        $query = $this->twinfield->finder(FinderType::AssetMethods);

        return CodeNameSearch::forCompany($query, AssetMethodSummary::fromFinderRow(...))->pattern($pattern);
    }

    /**
     * Twinfield has no `<read>` for asset methods: an `<assetmethod>` with only office and
     * code reads it.
     *
     * @throws AssetMethodNotFoundException When there is no asset method with this code.
     */
    public function get(string $code): AssetMethod
    {
        $xml = XmlBuilder::create('assetmethod');
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'code', $code);

        return AssetMethod::fromXml(XmlElement::root($this->send($xml, $code)), $this->twinfield->organisation());
    }

    /**
     * The asset method, or null when there is no asset method with this code.
     */
    public function find(string $code): ?AssetMethod
    {
        try {
            return $this->get($code);
        } catch (AssetMethodNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new asset method. Reads first, so an existing one is never overwritten.
     *
     * @throws AssetMethodAlreadyExistsException When the code is already in use.
     */
    public function create(AssetMethodInput $method): AssetMethod
    {
        if ($this->find($method->code) !== null) {
            throw new AssetMethodAlreadyExistsException($method->code);
        }

        return $this->save($method);
    }

    /**
     * Updates an existing asset method with all values of the input. Start from
     * `$method->edit()` to keep the values you do not change.
     *
     * @throws AssetMethodNotFoundException When there is no asset method with this code.
     */
    public function update(AssetMethodInput $method): AssetMethod
    {
        $this->get($method->code);

        return $this->save($method);
    }

    /**
     * Creates the asset method, or updates it when the code exists (no existence check).
     * Reads it back afterwards, so you always get the complete asset method.
     */
    public function save(AssetMethodInput $method): AssetMethod
    {
        $this->send($method->toXml($this->company()), $method->code);

        return $this->get($method->code);
    }

    /**
     * Deletes the asset method. When fixed assets use it, Twinfield hides it instead.
     *
     * @throws AssetMethodNotFoundException When there is no asset method with this code.
     */
    public function delete(string $code): DeleteResult
    {
        $result = $this->changeStatus($code, AssetMethodStatus::Deleted);
        $status = XmlElement::root($result)->enumAttribute('status', AssetMethodStatus::class);

        return match ($status) {
            AssetMethodStatus::Deleted => DeleteResult::Deleted,
            AssetMethodStatus::Hidden => DeleteResult::Hidden,
            AssetMethodStatus::Active => throw new UnexpectedResponseException(sprintf(
                'Asset method %s is still active after deleting it.',
                $code,
            )),
        };
    }

    /**
     * Makes a deleted or hidden asset method active again.
     *
     * @throws AssetMethodNotFoundException When there is no asset method with this code.
     */
    public function activate(string $code): void
    {
        $this->changeStatus($code, AssetMethodStatus::Active);
    }

    private function changeStatus(string $code, AssetMethodStatus $status): ProcessXmlResult
    {
        $xml = XmlBuilder::create('assetmethod', ['status' => $status->value]);
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'code', $code);

        return $this->send($xml, $code);
    }

    private function send(XmlBuilder $xml, string $code): ProcessXmlResult
    {
        try {
            return $this->twinfield->processXml($xml->toXml());
        } catch (ProcessXmlException $exception) {
            throw AssetMethodNotFoundException::fromProcessXml($exception, $code) ?? $exception;
        }
    }

    private function company(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException('Asset methods belong to a company: use company() first.');
    }
}
