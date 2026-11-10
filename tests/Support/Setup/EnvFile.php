<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support\Setup;

use RuntimeException;

/**
 * Updates keys in a `.env` file, keeping all other lines and comments.
 * The file is private: directory 0700, file 0600.
 */
final readonly class EnvFile
{
    private const array HEADER = [
        '# Twinfield credentials for local development, tests and examples (twinfield-php).',
        '# Keep this file private: chmod 600.',
        '',
    ];

    public function __construct(
        public string $path,
    ) {
    }

    /**
     * @param array<string, string> $values
     */
    public function set(array $values): void
    {
        $lines = is_readable($this->path)
            ? (file($this->path, FILE_IGNORE_NEW_LINES) ?: [])
            : self::HEADER;

        foreach ($values as $key => $value) {
            $line = $key . '=' . self::quote($value);
            $pattern = '~^\s*(export\s+)?' . preg_quote($key, '~') . '\s*=~';
            $replaced = false;

            foreach ($lines as $index => $existing) {
                if (preg_match($pattern, $existing) === 1) {
                    $lines[$index] = $line;
                    $replaced = true;
                }
            }

            if (!$replaced) {
                $lines[] = $line;
            }
        }

        $this->write(implode("\n", $lines) . "\n");
    }

    /**
     * Quotes a value so phpdotenv reads it back literally.
     */
    public static function quote(string $value): string
    {
        if (preg_match('~^[A-Za-z0-9_./:,@-]*$~', $value) === 1) {
            return $value;
        }

        // Single quotes are literal in phpdotenv (no escapes, no variable expansion).
        if (!str_contains($value, "'")) {
            return "'" . $value . "'";
        }

        return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
    }

    private function write(string $contents): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create directory "%s".', $directory));
        }

        $tmp = $this->path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, $contents);
        chmod($tmp, 0600);
        rename($tmp, $this->path);
    }
}
