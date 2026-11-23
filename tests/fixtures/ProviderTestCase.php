<?php

declare(strict_types=1);

use DrupalSdcSkill\FilesystemFaults;
use DrupalSdcSkill\ScaffoldCommand;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../FilesystemFaults.php';
require_once __DIR__ . '/../../scripts/ScaffoldCommand.php';

/**
 * Provides a temporary Drupal provider and in-process CLI runner.
 */
abstract class ProviderTestCase extends TestCase {

  protected const ID = 'my_theme:teaser-card';

  /**
   * The temporary Drupal provider directory for the current test.
   */
  protected string $provider;

  /**
   * Creates a temporary Drupal provider for each test.
   */
  protected function setUp(): void {
    $this->provider = sys_get_temp_dir() . '/sdc-test-' . bin2hex(random_bytes(8));
    mkdir($this->provider);
    file_put_contents($this->provider . '/my_theme.info.yml', "name: My theme\ntype: theme\n");
  }

  /**
   * Removes temporary providers and clears simulated faults.
   */
  protected function tearDown(): void {
    FilesystemFaults::$operation = NULL;
    $entries = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($this->provider, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($entries as $entry) {
      if ($entry->isDir() && !$entry->isLink()) {
        rmdir($entry->getPathname());
        continue;
      }
      unlink($entry->getPathname());
    }
    rmdir($this->provider);
  }

  /**
   * Runs the command against in-memory streams and captures its output.
   */
  protected function runCommand(array $args, string $input = ''): array {
    $stdin = fopen('php://memory', 'w+');
    $stdout = fopen('php://memory', 'w+');
    $stderr = fopen('php://memory', 'w+');
    try {
      fwrite($stdin, $input);
      rewind($stdin);
      $status = (new ScaffoldCommand())->run(['scaffold.php', ...$args], $stdin, $stdout, $stderr);
      rewind($stdout);
      rewind($stderr);
      return [$status, stream_get_contents($stdout), stream_get_contents($stderr)];
    }
    finally {
      fclose($stdin);
      fclose($stdout);
      fclose($stderr);
    }
  }

}
