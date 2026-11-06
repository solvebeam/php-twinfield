<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support\Setup;

/**
 * Minimal terminal prompt. Writes to STDERR so the output of examples stays clean.
 */
final readonly class Prompt
{
    /**
     * @param resource $input
     * @param resource $output
     */
    public function __construct(
        private mixed $input,
        private mixed $output,
        private bool $interactive,
    ) {
    }

    public static function terminal(): self
    {
        return new self(STDIN, STDERR, stream_isatty(STDIN));
    }

    public function isInteractive(): bool
    {
        return $this->interactive;
    }

    public function line(string $text = ''): void
    {
        fwrite($this->output, $text . PHP_EOL);
    }

    public function ask(string $question, ?string $default = null, bool $required = true): string
    {
        while (true) {
            fwrite($this->output, $default === null ? $question . ': ' : sprintf('%s [%s]: ', $question, $default));

            $answer = $this->readLine();

            if ($answer === '' && $default !== null) {
                return $default;
            }

            if ($answer !== '' || !$required) {
                return $answer;
            }

            $this->line('  A value is required.');
        }
    }

    /**
     * Like ask(), but the typed text is not shown in the terminal (macOS/Linux).
     */
    public function askSecret(string $question, bool $keepCurrent = false): string
    {
        $hide = $this->input === STDIN && stream_isatty(STDIN) && PHP_OS_FAMILY !== 'Windows';

        if (!$hide && PHP_OS_FAMILY === 'Windows') {
            $this->line('  (Input will be visible.)');
        }

        while (true) {
            fwrite($this->output, $question . ($keepCurrent ? ' [Enter keeps current]' : '') . ': ');

            if ($hide) {
                shell_exec('stty -echo');
            }

            try {
                $answer = $this->readLine();
            } finally {
                if ($hide) {
                    shell_exec('stty echo');
                    fwrite($this->output, PHP_EOL);
                }
            }

            if ($answer !== '' || $keepCurrent) {
                return $answer;
            }

            $this->line('  A value is required.');
        }
    }

    private function readLine(): string
    {
        $line = fgets($this->input);

        if ($line === false) {
            throw new SetupException('Setup was cancelled: no more input.');
        }

        return trim($line);
    }
}
