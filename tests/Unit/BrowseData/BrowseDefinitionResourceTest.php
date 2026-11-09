<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\BrowseData;

use DOMDocument;
use DOMXPath;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\BrowseData\BrowseFieldDataType;
use SolveBeam\Twinfield\BrowseData\BrowseOperator;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;

final class BrowseDefinitionResourceTest extends TestCase
{
    public function testReadsABrowseDefinition(): void
    {
        $http = ReplayClient::fixture('browse-definition-030_3');

        $definition = $this->twinfield($http)->browseDefinitions()->get(BrowseCode::GeneralLedgerDetailsV3);

        self::assertSame(
            '<read><type>browse</type><office>1000</office><code>030_3</code></read>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertTrue($definition->company->equals(TestTokens::company('1000')));
        self::assertSame('030_3', $definition->code);
        self::assertSame('Grootboek (details) (v3)', $definition->name);
        self::assertFalse($definition->visible);
        self::assertCount(51, $definition->columns);

        $period = $definition->column('fin.trs.head.yearperiod');
        self::assertNotNull($period);
        self::assertSame(5, $period->id);
        self::assertSame('Jaar/periode (JJJJ/PP)', $period->label);
        self::assertFalse($period->visible);
        self::assertTrue($period->ask);
        self::assertSame(BrowseOperator::Between, $period->operator);
        self::assertNull($period->from);

        $status = $definition->column('fin.trs.head.status');
        self::assertNotNull($status);
        self::assertSame(BrowseOperator::Equal, $status->operator);
        self::assertSame('normal', $status->from);
        self::assertNull($status->to);

        self::assertSame(
            [
                'fin.trs.head.yearperiod',
                'fin.trs.head.status',
                'fin.trs.head.inpdate',
                'fin.trs.head.modified',
                'fin.trs.line.dim1',
                'fin.trs.line.dim2',
                'fin.trs.line.dim3',
                'fin.trs.head.regime',
                'fin.trs.head.reportingstructure',
            ],
            array_map(static fn ($column) => $column->field, $definition->criteria()),
        );
        self::assertNull($definition->column('fin.trs.nope'));
    }

    public function testThrowsForAnUnknownBrowseCode(): void
    {
        $this->expectException(ProcessXmlException::class);
        $this->expectExceptionMessage('Opvraagcode 999 bestaat niet.');

        $this->twinfield(ReplayClient::fixture('browse-definition-unknown'))->browseDefinitions()->get('999');
    }

    public function testListsBrowseFields(): void
    {
        $http = ReplayClient::fixture('browse-fields');

        $fields = $this->twinfield($http)->browseDefinitions()->fields();

        self::assertSame('<list><type>browsefields</type></list>', $this->xmlRequest($http->lastRequest()));
        self::assertCount(9, $fields);

        $byCode = [];

        foreach ($fields as $field) {
            $byCode[$field->code] = $field;
        }

        $status = $byCode['fin.trs.head.status'];
        self::assertSame(BrowseFieldDataType::Special, $status->dataType);
        self::assertNull($status->finder);
        self::assertSame(
            ['draft', 'temporary', 'final', 'inuse', 'normal'],
            array_map(static fn ($option) => $option->value, $status->options),
        );
        self::assertSame('Klad', $status->options[0]->name);

        self::assertSame('TRS', $byCode['fin.trs.head.code']->finder);
        self::assertSame([], $byCode['fin.trs.head.code']->options);
        self::assertSame(BrowseFieldDataType::DateTime, $byCode['fin.trs.head.modified']->dataType);
        self::assertSame(BrowseFieldDataType::Long, $byCode['fin.rmd.detail.remindcount']->dataType);
        self::assertNull($byCode['fin.trs.head.bankcode']->options[0]->name);
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
    }

    private function xmlRequest(RequestInterface $request): string
    {
        $document = new DOMDocument();
        $document->loadXML((string) $request->getBody());

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('tw', 'http://www.twinfield.com/');

        $xmlRequest = $xpath->evaluate('string(//tw:ProcessXmlString/tw:xmlRequest)');

        self::assertIsString($xmlRequest);

        return $xmlRequest;
    }
}
