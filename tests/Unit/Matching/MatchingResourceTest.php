<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Matching;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\DimensionType\DimensionTypeCode;
use SolveBeam\Twinfield\Matching\MatchingException;
use SolveBeam\Twinfield\Matching\MatchInput;
use SolveBeam\Twinfield\Matching\MatchNotFoundException;
use SolveBeam\Twinfield\Matching\UndoMatchMessageCode;
use SolveBeam\Twinfield\Matching\UndoMatchMessageType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class MatchingResourceTest extends TestCase
{
    public function testMatches(): void
    {
        $http = ReplayClient::fixture('matching-match');

        $this->twinfield($http)->matching()->match($this->partPayment());

        self::assertSame(
            '<match><set><office>1000</office><matchcode>170</matchcode><matchdate>20261110</matchdate><lines>'
            . '<line><transcode>KAS</transcode><transnumber>202600014</transnumber><transline>2</transline></line>'
            . '<line><transcode>VRK</transcode><transnumber>202600070</transnumber><transline>1</transline>'
            . '<matchvalue>119.00</matchvalue></line></lines></set></match>',
            $this->xmlRequest($http),
        );
    }

    public function testThrowsWhenTwinfieldRefusesTheMatch(): void
    {
        $http = ReplayClient::fixture('matching-match-already-matched');

        $this->expectException(ProcessXmlException::class);
        $this->expectExceptionMessage('Boekingsregel VRK:202600070:1 is al afgeletterd.');

        $this->twinfield($http)->matching()->match($this->partPayment());
    }

    public function testThrowsOnAnotherRootElement(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Expected a <match> response, got <reverse>.');

        $this->twinfield(ReplayClient::fixture('reversal-transaction-undo'))->matching()->match($this->partPayment());
    }

    public function testRefusesASetWithOneLineBeforeSending(): void
    {
        $http = new ReplayClient();

        try {
            $this->twinfield($http)->matching()->match(
                MatchInput::customers(new DateTimeImmutable('2026-11-10'))->line('KAS', 202600014, 2),
            );
            self::fail('Expected a LogicException.');
        } catch (LogicException) {
            self::assertSame([], $http->requests);
        }
    }

    public function testUndoesAMatch(): void
    {
        $http = ReplayClient::fixture('matching-undo');

        $this->twinfield($http)->matching()->undo(DimensionTypeCode::AccountsReceivable, '1000', 12);

        self::assertSame('http://www.twinfield.com/UndoMatch', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringEndsWith('/webservices/matching.asmx', (string) $http->lastRequest()->getUri());
        self::assertStringContainsString(
            '<UndoMatch><office>1000</office><dimensionType>DEB</dimensionType><dimension>1000</dimension>'
            . '<matchNumber>12</matchNumber></UndoMatch>',
            $this->body($http),
        );
    }

    public function testUnknownMatchNumberIsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('matching-undo-not-found'))->matching()->undo('DEB', '1000', 999);
            self::fail('Expected MatchNotFoundException.');
        } catch (MatchNotFoundException $exception) {
            self::assertSame(UndoMatchMessageCode::MatchNotFound, $exception->messages[0]->code);
            self::assertSame(UndoMatchMessageType::Error, $exception->messages[0]->type);
            self::assertSame(
                'UndoMatch: Afletternummer 999 is niet gevonden. (MatchNotFound)',
                $exception->getMessage(),
            );
        }
    }

    public function testThrowsTheOtherErrors(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('matching-undo-dimension-not-found'))
                ->matching()
                ->undo('DEB', '9999', 1);
            self::fail('Expected MatchingException.');
        } catch (MatchingException $exception) {
            self::assertNotInstanceOf(MatchNotFoundException::class, $exception);
            self::assertSame(UndoMatchMessageCode::DimensionNotFoundByType, $exception->messages[0]->code);
        }
    }

    public function testThrowsOnAnUnexpectedUndoResponse(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Expected <UndoMatchResponse>, got <DeleteResponse>.');

        $this->twinfield(ReplayClient::fixture('hierarchies-delete'))->matching()->undo('DEB', '1000', 1);
    }

    public function testRefusesAnInvalidUndoBeforeSending(): void
    {
        $http = new ReplayClient();

        foreach ([['DEB', '', 1], ['', '1000', 1], ['DEB', '1000', 0]] as [$type, $dimension, $number]) {
            try {
                $this->twinfield($http)->matching()->undo($type, $dimension, $number);
                self::fail('Expected an InvalidArgumentException.');
            } catch (InvalidArgumentException) {
                self::assertSame([], $http->requests);
            }
        }
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(
            TestTokens::provider('https://example.test'),
            new ReplayClient(),
            $psr17,
            $psr17,
        );

        $this->expectException(LogicException::class);

        $twinfield->matching()->undo('DEB', '1000', 1);
    }

    private function partPayment(): MatchInput
    {
        return MatchInput::customers(new DateTimeImmutable('2026-11-10'))
            ->line('KAS', 202600014, 2)
            ->line('VRK', 202600070, 1, matchValue: '119.00');
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
    }

    private function body(ReplayClient $http): string
    {
        $body = (string) preg_replace('~>\s+<~', '><', (string) $http->lastRequest()->getBody());

        return (string) preg_replace('~ xmlns(:\w+)?="[^"]*"~', '', $body);
    }

    private function xmlRequest(ReplayClient $http): string
    {
        $document = new DOMDocument();
        $document->loadXML((string) $http->lastRequest()->getBody());

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('tw', 'http://www.twinfield.com/');

        $xmlRequest = $xpath->evaluate('string(//tw:ProcessXmlString/tw:xmlRequest)');

        self::assertIsString($xmlRequest);

        return $xmlRequest;
    }
}
