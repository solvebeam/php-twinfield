<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support;

use RuntimeException;

/**
 * Runs an example from examples/ as a separate PHP process.
 */
final class ExampleRunner
{
    /**
     * @param array<string, string> $environment
     * @return array{int, string, string} Exit code, stdout and stderr.
     */
    public static function run(string $name, array $environment): array
    {
        $command = [PHP_BINARY, dirname(__DIR__, 2) . '/examples/' . $name . '/example.php'];
        $descriptors = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, null, $environment);

        if (!is_resource($process)) {
            throw new RuntimeException(sprintf('Cannot start example %s.', $name));
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
