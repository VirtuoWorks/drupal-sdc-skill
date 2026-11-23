<?php

declare(strict_types=1);

namespace DrupalSdcSkill;

require_once __DIR__ . '/ComponentSpec.php';
require_once __DIR__ . '/Scaffolder.php';

/**
 * Parses CLI arguments and reports scaffolding results.
 */
final class ScaffoldCommand {
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
   * Runs the command and returns its process exit code.
   *
   * @param list<string> $argv
   *   The command-line arguments, including the script name.
   * @param resource $stdin
   *   The input stream for declarations passed on standard input.
   * @param resource $stdout
   *   The output stream for help and successful results.
   * @param resource $stderr
   *   The output stream for diagnostics.
   */
  public function run(array $argv, $stdin, $stdout, $stderr): int {
    $args = array_slice($argv, 1);
    if ($args === ['--help']) {
      fwrite($stdout, self::USAGE . "\n\n" . self::HELP . "\n");
      return 0;
    }

    try {
      $options = [];
      while (in_array($args[0] ?? NULL, ['--dry-run', '--with-unit-test'], TRUE)) {
        $option = array_shift($args);
        if (isset($options[$option])) {
          throw new \InvalidArgumentException("Option '$option' may only be supplied once.");
        }
        $options[$option] = TRUE;
      }

      if (count($args) < 2 || count($args) > 3 || str_starts_with($args[0], '--')) {
        throw new \InvalidArgumentException(self::USAGE);
      }

      [$path, $componentId] = $args;
      $input = $args[2] ?? NULL;
      $json = match ($input) {
        NULL => '{}',
        '-' => stream_get_contents($stdin),
        default => $this->readSpecFile($input),
      };
      if ($json === FALSE) {
        throw new \RuntimeException('Cannot read the JSON declaration.');
      }

      $spec = ComponentSpec::fromJson($componentId, $json);
      $dryRun = isset($options['--dry-run']);
      $files = (new Scaffolder())->generate($path, $spec, $dryRun, isset($options['--with-unit-test']));

      fwrite($stdout, json_encode(
        ['id' => $componentId, 'files' => $files, 'dry_run' => $dryRun],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
      ) . "\n");
      return 0;
    }
    catch (\InvalidArgumentException | \JsonException | \RuntimeException $exception) {
      fwrite($stderr, 'Error: ' . $exception->getMessage() . "\n");
      return 1;
    }
  }

  /**
   * Reads the JSON declaration from a local file.
   */
  private function readSpecFile(string $path): string|false {
    if (!is_file($path)) {
      throw new \InvalidArgumentException("Expected a local JSON declaration file at '$path'.");
    }

    return @file_get_contents($path);
  }

}
