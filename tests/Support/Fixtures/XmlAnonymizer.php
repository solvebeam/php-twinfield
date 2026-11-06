<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support\Fixtures;

use DateTimeImmutable;
use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use RuntimeException;

/**
 * Replaces every value in a Twinfield XML response by a fake value with the
 * same format, so fixtures keep the real structure without real data.
 *
 * - Element names, attribute names, namespaces and order stay the same.
 * - Digits stay digits, letters stay letters, GUIDs stay GUIDs, dates stay
 *   valid dates (all shifted by the same number of days).
 * - The same input always gives the same output (keyed with a secret key), so
 *   a code that appears in two places still matches after anonymizing.
 * - XML embedded as text (ProcessXml responses) is anonymized recursively.
 * - Harmless values are kept: see the KEEP_* lists below.
 */
final class XmlAnonymizer
{
    /** Values kept as is, compared case-insensitively. */
    private const KEEP_VALUES = [
        'true', 'false', 'yes', 'no', 'normal', 'active', 'deleted', 'hide', 'error', 'warning', 'info',
        'DIM', 'DEB', 'CRD', 'KPL', 'PNL', 'BAS', 'ACT', 'PRJ', 'AST', 'OFF', 'EUR',
        'Error', 'Warning', 'Informational',
    ];

    /** Elements whose text is kept as is. */
    private const KEEP_ELEMENTS = ['TotalRows', 'MessageType', 'Type', 'type', 'result', 'currency'];

    /** Text within these elements is kept (column headers, not data). */
    private const KEEP_WITHIN = ['Columns'];

    /** Attributes whose value is kept as is. */
    private const KEEP_ATTRIBUTES = [
        'result', 'msgtype', 'type', 'status', 'inuse', 'behaviour', 'field', 'label', 'hideforuser',
    ];

    /** Elements whose text is always replaced, overriding all keep rules. */
    private const ALWAYS_REPLACE = ['AccessToken', 'CompanyCode', 'office', 'clusterUrl'];

    private int $dayOffset;

    /** @var array<string, int> */
    private array $kept = [];

    private int $replaced = 0;

    public function __construct(
        private readonly string $key,
    ) {
        if (strlen($key) < 16) {
            throw new RuntimeException('Anonymizer key must be at least 16 characters.');
        }

        // Same shift for all dates, so their order and intervals stay intact.
        $this->dayOffset = 30 + hexdec(substr(hash_hmac('sha256', 'dates', $key), 0, 4)) % 700;
    }

    /**
     * Loads the key from `{$configDir}/anonymizer.key`, creating it on first use.
     * The key is secret: with it, short values (like 4-digit codes) could be guessed back.
     */
    public static function fromConfigDir(string $configDir): self
    {
        $file = $configDir . '/anonymizer.key';

        if (!is_readable($file)) {
            if (!is_dir($configDir)) {
                mkdir($configDir, 0700, true);
            }

            file_put_contents($file, bin2hex(random_bytes(32)) . "\n");
            chmod($file, 0600);
        }

        return new self(trim((string) file_get_contents($file)));
    }

    public function anonymize(string $xml): string
    {
        $document = new DOMDocument();
        $document->preserveWhiteSpace = true;

        if (@$document->loadXML($xml) === false || $document->documentElement === null) {
            throw new RuntimeException('Cannot anonymize: input is not valid XML.');
        }

        $this->walk($document->documentElement, []);

        $hasDeclaration = str_starts_with(ltrim($xml), '<?xml');

        return $hasDeclaration
            ? (string) $document->saveXML()
            : (string) $document->saveXML($document->documentElement);
    }

    /**
     * Values kept as is during the last runs, with counts, for review.
     *
     * @return array<string, int>
     */
    public function keptValues(): array
    {
        ksort($this->kept);

        return $this->kept;
    }

    public function replacedCount(): int
    {
        return $this->replaced;
    }

    /**
     * @param list<string> $ancestors Local names of the parent elements, outermost first.
     */
    private function walk(DOMNode $node, array $ancestors): void
    {
        if ($node instanceof DOMElement) {
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $this->anonymizeAttribute($attribute);
            }

            $ancestors[] = (string) $node->localName;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                $this->anonymizeText($child, $ancestors);
            } else {
                $this->walk($child, $ancestors);
            }
        }
    }

    private function anonymizeAttribute(DOMAttr $attribute): void
    {
        // Namespaced attributes such as xsi:nil and xsi:type are structural.
        if ($attribute->namespaceURI !== null || in_array($attribute->localName, self::KEEP_ATTRIBUTES, true)) {
            $this->keep($attribute->value);

            return;
        }

        $attribute->value = $this->value($attribute->value);
    }

    /**
     * @param list<string> $ancestors
     */
    private function anonymizeText(DOMText $text, array $ancestors): void
    {
        $value = $text->data;

        if (trim($value) === '') {
            return;
        }

        $element = $ancestors[count($ancestors) - 1] ?? '';

        if (in_array($element, self::ALWAYS_REPLACE, true)) {
            $text->data = $this->value($value, force: true);

            return;
        }

        $trimmed = trim($value);

        if (str_starts_with($trimmed, '<') && @(new DOMDocument())->loadXML($trimmed) !== false) {
            $text->data = $this->anonymize($trimmed);

            return;
        }

        if (in_array($element, self::KEEP_ELEMENTS, true) || array_intersect($ancestors, self::KEEP_WITHIN) !== []) {
            $this->keep($value);

            return;
        }

        $text->data = $this->value($value);
    }

    private function value(string $value, bool $force = false): string
    {
        $trimmed = trim($value);

        if (!$force && $this->isHarmless($trimmed)) {
            $this->keep($trimmed);

            return $value;
        }

        $this->replaced++;

        return str_replace($trimmed, $this->fake($trimmed), $value);
    }

    private function isHarmless(string $value): bool
    {
        if ($value === '' || preg_match('~^-?\d{1,2}(\.0+)?$~', $value) === 1) {
            return true;
        }

        foreach (self::KEEP_VALUES as $keep) {
            if (strcasecmp($keep, $value) === 0) {
                return true;
            }
        }

        return false;
    }

    private function fake(string $value): string
    {
        if (preg_match('~^https?://~i', $value) === 1) {
            return 'https://example.test';
        }

        if (preg_match('~^\{?[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\}?$~i', $value) === 1) {
            return $this->scramble($value, hex: true);
        }

        $date = $this->fakeDate($value);

        return $date ?? $this->scramble($value);
    }

    /**
     * Shifts `YYYYMMDD`, `YYYY-MM-DD` and `YYYY-MM-DDTHH:MM:SS…` dates; returns null for other values.
     */
    private function fakeDate(string $value): ?string
    {
        if (preg_match('~^(\d{4})(-?)(\d{2})\2(\d{2})(T.*)?$~', $value, $matches) !== 1) {
            return null;
        }

        [, $year, $separator, $month, $day] = $matches;

        if (!checkdate((int) $month, (int) $day, (int) $year) || (int) $year < 1900) {
            return null;
        }

        $date = new DateTimeImmutable(sprintf('%s-%s-%s', $year, $month, $day));
        $date = $date->modify(sprintf('-%d days', $this->dayOffset));
        $format = $separator === '-' ? 'Y-m-d' : 'Ymd';

        return $date->format($format) . ($matches[5] ?? '');
    }

    /**
     * Format-preserving replacement: digits → digits, letters → letters (same case), rest unchanged.
     */
    private function scramble(string $value, bool $hex = false): string
    {
        $bytes = '';
        $result = '';
        $characters = mb_str_split($value);

        for ($counter = 0; strlen($bytes) < count($characters); $counter++) {
            $bytes .= hash_hmac('sha512', $counter . "\0" . $value, $this->key, true);
        }

        foreach ($characters as $index => $character) {
            $byte = ord($bytes[$index]);

            $result .= match (true) {
                $hex && ctype_xdigit($character) => dechex($byte % 16),
                ctype_digit($character) => (string) ($byte % 10),
                ctype_upper($character) => chr(65 + $byte % 26),
                preg_match('~^\p{L}$~u', $character) === 1 => chr(97 + $byte % 26),
                default => $character,
            };
        }

        return $result;
    }

    private function keep(string $value): void
    {
        $value = trim($value);

        if ($value !== '') {
            $this->kept[$value] = ($this->kept[$value] ?? 0) + 1;
        }
    }
}
