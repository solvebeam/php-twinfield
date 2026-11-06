<?php

/**
 * Small dev CLI to try live Twinfield calls quickly.
 *
 * Usage:
 *   composer twinfield -- whoami
 *   composer twinfield -- refresh
 *   composer twinfield -- finder DIM            # search dimensions
 *   composer twinfield -- finder OFF "*" 50     # type, pattern, max rows
 *   composer twinfield -- finder DIM "*" 10 --company=1002_3 --quiet
 *   composer twinfield -- periods               # years of the company
 *   composer twinfield -- periods 2025          # periods of a year
 *
 * Without --quiet, the full SOAP request and response are printed (token masked).
 */

declare(strict_types=1);

use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Soap\SoapFaultException;
use SolveBeam\Twinfield\Tests\Support\Config;
use SolveBeam\Twinfield\Tests\Support\Environment;

require dirname(__DIR__) . '/vendor/autoload.php';

$options = getopt('', ['company:', 'quiet'], $restIndex);
$arguments = array_slice($_SERVER['argv'] ?? [], $restIndex);
$command = array_shift($arguments) ?? 'help';

if (!isset($options['quiet']) && getenv('TWINFIELD_DEBUG') === false) {
    putenv('TWINFIELD_DEBUG=1');
}

$environment = Environment::fromConfig(Config::load(), 'cli-' . $command);
$company = is_string($options['company'] ?? null) ? $options['company'] : null;

try {
    switch ($command) {
        case 'whoami':
            $session = $environment->session;
            echo 'Organisation: ', $session->organisationCode(), PHP_EOL;
            echo 'Cluster:      ', $session->clusterUrl(), PHP_EOL;
            echo 'Company:      ', $company ?? $environment->config->company(), PHP_EOL;
            break;

        case 'refresh':
            $data = $environment->session->refresh();
            echo 'Access token refreshed, valid until ', date('Y-m-d H:i:s', (int) $data['expires']), PHP_EOL;
            break;

        case 'finder':
            $twinfield = $environment->client();
            $twinfield = $company === null ? $twinfield : $twinfield->company($company);
            $type = FinderType::tryFrom(strtoupper($arguments[0] ?? 'DIM'))
                ??
            throw new InvalidArgumentException(sprintf(
                'Unknown Finder type "%s". Known: %s',
                $arguments[0] ?? '',
                implode(', ', array_map(static fn (FinderType $type): string => $type->value, FinderType::cases())),
            ));

            $result = $twinfield->finder($type)
                ->pattern($arguments[1] ?? '*')
                ->limit((int) ($arguments[2] ?? 10))
                ->get();

            echo PHP_EOL, sprintf('Total rows: %d', $result->totalRows), PHP_EOL;
            echo implode(' | ', $result->columns), PHP_EOL;

        foreach ($result as $row) {
            echo implode(' | ', $row), PHP_EOL;
        }
            break;

        case 'periods':
            $twinfield = $environment->client();
            $twinfield = $company === null ? $twinfield : $twinfield->company($company);

            if (!isset($arguments[0])) {
                echo PHP_EOL, 'Years: ', implode(', ', $twinfield->periods()->years()), PHP_EOL;
                break;
            }

            echo PHP_EOL;

            foreach ($twinfield->periods()->list((int) $arguments[0]) as $period) {
                $endDate = $period->endDate?->format('Y-m-d') ?? '-';
                $status = $period->open ? 'open' : 'closed';

                echo $period->number, ' | ', $period->name, ' | ', $endDate, ' | ', $status, PHP_EOL;
            }
            break;

        default:
            echo <<<'TXT'
                Usage: composer twinfield -- <command> [arguments] [--company=CODE] [--quiet]

                  whoami                         Show organisation, cluster and company
                  refresh                        Refresh the access token now
                  finder [type] [pattern] [max]  Finder search, e.g. finder DIM "*" 10
                  periods [year]                 Years, or the periods of a year (PeriodService)

                TXT;
    }
} catch (SoapFaultException $exception) {
    fwrite(STDERR, 'SOAP fault: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
