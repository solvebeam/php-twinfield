<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Examples;

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
