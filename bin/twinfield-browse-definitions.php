<?php

/**
 * Saves the browse definition of every browse code, and the browse fields, in browse-definitions/.
 *
 * Usage:
 *   composer twinfield:browse-definitions                     # default company (TWINFIELD_COMPANY)
 *   composer twinfield:browse-definitions -- --company=1000   # another company
 *
 * The definitions are read-only requests. Company data is replaced: the company code and name by
 * those of an example company, and the options of fin.trs.head.bankcode (the bank and cash day
 * books of the company) are left out.
 */

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlString;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\Soap\RateLimitExceededException;
use SolveBeam\Twinfield\Tests\Support\Environment;
use SolveBeam\Twinfield\Twinfield;

require dirname(__DIR__) . '/vendor/autoload.php';

$targetDir = dirname(__DIR__) . '/browse-definitions';
$options = getopt('', ['company:']);
$twinfield = Environment::fromConfig()->client();

if (isset($options['company']) && is_string($options['company'])) {
    $twinfield = $twinfield->company($options['company']);
}

$company = $twinfield->companyReference()->code;
$codes = BrowseCode::cases();
$counts = ['saved' => 0, 'unchanged' => 0, 'failed' => 0];

echo sprintf('Saving %d browse definitions and the browse fields of company %s', count($codes), $company), PHP_EOL;

/**
 * The response XML, indented, with an example company instead of the real one.
 */
$normalise = static function (string $xml) use ($company): string {
    $document = new DOMDocument();
    $document->preserveWhiteSpace = false;
    $document->formatOutput = true;
    $document->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE);

    $xpath = new DOMXPath($document);

    foreach ($xpath->query('/browse/office') ?: [] as $office) {
        if ($office instanceof DOMElement) {
            if ($office->textContent !== $company) {
                throw new RuntimeException(sprintf('Expected company %s, got %s.', $company, $office->textContent));
            }

            $office->textContent = '1000';
            $office->setAttribute('name', 'Example company');
            $office->setAttribute('shortname', '');
        }
    }

    // The bank and cash day books of the company.
    foreach ($xpath->query('//browsefield[code="fin.trs.head.bankcode"]/dropdown/option') ?: [] as $option) {
        if ($option instanceof DOMElement) {
            $option->parentNode?->removeChild($option);
        }
    }

    return (string) $document->saveXML($document->documentElement) . "\n";
};

$save = static function (string $file, string $xml) use ($targetDir, &$counts): string {
    $path = $targetDir . '/' . $file;

    if (is_file($path) && file_get_contents($path) === $xml) {
        $counts['unchanged']++;

        return 'unchanged';
    }

    file_put_contents($path, $xml);
    $counts['saved']++;

    return 'saved';
};

// Waits and tries again when Twinfield's rate limit is reached, at most 5 times.
$read = static function (Twinfield $twinfield, string $request): string {
    for ($attempt = 1;; $attempt++) {
        try {
            return ProcessXmlResult::xmlOf($twinfield->call('processxml', ProcessXmlString::fromXml($request)));
        } catch (RateLimitExceededException $exception) {
            if ($attempt === 5) {
                throw $exception;
            }

            $seconds = $exception->retryAfterSeconds ?? $attempt;

            echo sprintf('  rate limit reached, waiting %d second(s)', $seconds), PHP_EOL;
            sleep($seconds);
        }
    }
};

foreach ($codes as $index => $code) {
    $prefix = sprintf('[%d/%d] %s %s', $index + 1, count($codes) + 1, $code->value, $code->label());

    $xml = XmlBuilder::create('read');
    $xml->add($xml->root(), 'type', 'browse');
    $xml->add($xml->root(), 'office', $company);
    $xml->add($xml->root(), 'code', $code->value);

    try {
        $response = $read($twinfield, $xml->toXml());
        $result = ProcessXmlResult::fromXml($response);

        if (!$result->successful()) {
            throw new RuntimeException($result->errors()[0]->text ?? 'no message');
        }

        $columns = $result->xpath()->query('/browse/columns/column');
        $status = $save($code->value . '.xml', $normalise($response));

        echo sprintf('%s: %s (%d columns)', $prefix, $status, $columns === false ? 0 : $columns->length), PHP_EOL;
    } catch (Throwable $exception) {
        $counts['failed']++;

        echo sprintf('%s: failed, %s', $prefix, $exception->getMessage()), PHP_EOL;
    }
}

$prefix = sprintf('[%d/%d] browse fields', count($codes) + 1, count($codes) + 1);

try {
    $response = $read($twinfield, '<list><type>browsefields</type></list>');
    $status = $save('browsefields.xml', $normalise($response));

    echo sprintf('%s: %s (%d fields)', $prefix, $status, substr_count($response, '<browsefield>')), PHP_EOL;
} catch (Throwable $exception) {
    $counts['failed']++;

    echo sprintf('%s: failed, %s', $prefix, $exception->getMessage()), PHP_EOL;
}

echo PHP_EOL, sprintf(
    'Done: %d saved, %d unchanged, %d failed. Review with: git diff browse-definitions',
    $counts['saved'],
    $counts['unchanged'],
    $counts['failed'],
), PHP_EOL;

exit($counts['failed'] > 0 ? 1 : 0);
