<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Examples;

use SolveBeam\Twinfield\BankBook\BankBook;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\Tests\Support\Config;
use SolveBeam\Twinfield\Tests\Support\Environment;
use SolveBeam\Twinfield\Tests\Support\Setup\Prompt;
use SolveBeam\Twinfield\Tests\Support\Setup\Setup;
use SolveBeam\Twinfield\Tests\Support\Setup\SetupException;
use SolveBeam\Twinfield\Twinfield;
use Throwable;

/**
 * Setup for the examples, so each example only shows what it is about.
 *
 *   $twinfield = Example::twinfield();
 *
 * The first time, it asks for your client ID, client secret and redirect
 * URI, opens the Twinfield login in your browser and asks for a default
 * company. Everything is saved in ~/.config/twinfield/, so later runs start
 * right away.
 *
 * In your own application you create the client yourself; see
 * examples/getting-started/authentication.
 */
final class Example
{
    public static function twinfield(): Twinfield
    {
        return self::environment()->client();
    }

    /**
     * An activity code that is not in use yet, from A999 down to A900, for the examples that
     * create an activity. Deleted activities keep their code, so each run takes a new one.
     */
    public static function unusedActivityCode(Twinfield $twinfield): string
    {
        for ($number = 999; $number >= 900; $number--) {
            $code = sprintf('A%03d', $number);

            if ($twinfield->activities()->find($code) === null) {
                return $code;
            }
        }

        throw new \RuntimeException('Activity codes A900–A999 are all in use.');
    }

    /**
     * A cost center code that is not in use yet, for the examples that create a cost center.
     *
     * The code format of cost centers differs per company, so this takes the length of an
     * existing numeric code (at least 3) and tries 999…, 998…, down to 900….
     */
    public static function unusedCostCenterCode(Twinfield $twinfield): string
    {
        $length = 3;

        foreach ($twinfield->costCenters()->search()->limit(20)->get() as $costCenter) {
            if (ctype_digit($costCenter->code)) {
                $length = max($length, strlen($costCenter->code));

                break;
            }
        }

        for ($number = 999; $number >= 900; $number--) {
            $code = str_pad((string) $number, $length, '9', STR_PAD_LEFT);

            // find(), not search(): deleted cost centers keep their code.
            if ($twinfield->costCenters()->find($code) === null) {
                return $code;
            }
        }

        throw new \RuntimeException('Cost center codes 999 down to 900 are all in use.');
    }

    /**
     * A currency code that is not in use yet, from ZZZ down to ZZA, for the examples that
     * create a currency.
     */
    public static function unusedCurrencyCode(Twinfield $twinfield): string
    {
        foreach (array_reverse(range('A', 'Z')) as $letter) {
            $code = 'ZZ' . $letter;

            if (!$twinfield->currencies()->exists($code)) {
                return $code;
            }
        }

        throw new \RuntimeException('Currency codes ZZA–ZZZ are all in use.');
    }

    /**
     * An asset method code that is not in use yet, from EXAMPLE999 down to EXAMPLE900, for the
     * examples that create an asset method.
     */
    public static function unusedAssetMethodCode(Twinfield $twinfield): string
    {
        for ($number = 999; $number >= 900; $number--) {
            $code = sprintf('EXAMPLE%03d', $number);

            if ($twinfield->assetMethods()->find($code) === null) {
                return $code;
            }
        }

        throw new \RuntimeException('Asset method codes EXAMPLE900–EXAMPLE999 are all in use.');
    }

    /**
     * The first bank book with a ledger account, for the bank transaction examples.
     */
    public static function bankBook(Twinfield $twinfield): BankBook
    {
        foreach ($twinfield->bankBooks()->search()->all() as $summary) {
            $bankBook = $twinfield->bankBooks()->get($summary->code);

            if ($bankBook->generalLedgerAccount !== null) {
                return $bankBook;
            }
        }

        throw new \RuntimeException('The company has no bank book with a ledger account.');
    }

    /**
     * The first profit and loss account of the company, e.g. for bank costs in the examples.
     */
    public static function profitAndLossAccount(Twinfield $twinfield): string
    {
        $row = $twinfield->finder(FinderType::Dimensions)
            ->option('dimtype', 'PNL')
            ->limit(1)
            ->get()
            ->rows[0] ?? throw new \RuntimeException('The company has no profit and loss accounts.');

        return $row[0];
    }

    /**
     * A ProcessXml request, indented, to show what an example would send.
     */
    public static function xml(XmlBuilder $xml): string
    {
        $document = new \DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;
        $document->loadXML($xml->toXml());

        return (string) $document->saveXML($document->documentElement);
    }

    /**
     * The stored login (access token, provider) behind Example::twinfield().
     */
    public static function environment(): Environment
    {
        // Show API errors as one readable line instead of a stack trace.
        set_exception_handler(static function (Throwable $exception): void {
            fwrite(STDERR, sprintf('%s: %s%s', (new \ReflectionClass($exception))->getShortName(), $exception->getMessage(), PHP_EOL));
            exit(1);
        });

        try {
            $config = (new Setup(Prompt::terminal()))->ensure(Config::load());
        } catch (SetupException $exception) {
            fwrite(STDERR, PHP_EOL . $exception->getMessage() . PHP_EOL);
            exit(1);
        }

        return Environment::fromConfig($config);
    }
}
