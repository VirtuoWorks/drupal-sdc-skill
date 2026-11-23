<?php

declare(strict_types=1);

namespace DrupalSdcSkill;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

require_once __DIR__ . '/ComponentSpec.php';
require_once __DIR__ . '/Scaffolder.php';

final class ScaffoldCommand
{
    private const USAGE = 'Usage: php scaffold.php [--dry-run] [--with-unit-test] <provider-dir> <provider:component> [<spec.json>|-]';

    private const HELP = <<<'HELP'
Generate a Drupal 10.3+ Single-Directory Component starter. Requires PHP 8.2+.
Omit the spec for a minimal starter. Use - to read JSON from stdin.
--dry-run checks the destination and lists paths without creating files.
--with-unit-test adds an isolated Twig and metadata test to tests/src/Unit.

Example spec:
{"name":"Teaser card","description":"A compact article preview.","props":{"title":"string"},"required":["title"],"slots":["body"],"subdirectory":"cards","assets":["scss","js"]}

Props accept string, number, integer, boolean, array, object, or null as type names.
Assets accept css, scss, or js. Errors go to stderr and exit with status 1.
HELP;

    /**
     * @param list<string> $argv
     * @param resource $stdin
     * @param resource $stdout
     * @param resource $stderr
     */
    public function run(array $argv, $stdin, $stdout, $stderr): int
    {
        $args = array_slice($argv, 1);
        if ($args === ['--help']) {
            fwrite($stdout, self::USAGE . "\n\n" . self::HELP . "\n");
            return 0;
        }

        try {
            $options = [];
            while (in_array($args[0] ?? null, ['--dry-run', '--with-unit-test'], true)) {
                $option = array_shift($args);
                if (isset($options[$option])) {
                    throw new InvalidArgumentException("Option '$option' may only be supplied once.");
                }
                $options[$option] = true;
            }

            if (count($args) < 2 || count($args) > 3 || str_starts_with($args[0], '--')) {
                throw new InvalidArgumentException(self::USAGE);
            }

            [$path, $id] = $args;
            $input = $args[2] ?? null;
            $json = match ($input) {
                null => '{}',
                '-' => stream_get_contents($stdin),
                default => $this->readSpecFile($input),
            };
            if ($json === false) {
                throw new RuntimeException('Cannot read the JSON declaration.');
            }

            $spec = ComponentSpec::fromJson($id, $json);
            $dryRun = isset($options['--dry-run']);
            $files = (new Scaffolder())->generate($path, $spec, $dryRun, isset($options['--with-unit-test']));

            fwrite($stdout, json_encode(
                ['id' => $id, 'files' => $files, 'dry_run' => $dryRun],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ) . "\n");
            return 0;
        } catch (InvalidArgumentException | JsonException | RuntimeException $exception) {
            fwrite($stderr, 'Error: ' . $exception->getMessage() . "\n");
            return 1;
        }
    }

    private function readSpecFile(string $path): string|false
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Expected a local JSON declaration file at '$path'.");
        }

        return @file_get_contents($path);
    }
}
