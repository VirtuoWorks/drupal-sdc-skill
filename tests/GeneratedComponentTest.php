<?php

declare(strict_types=1);

use DrupalSdcSkill\FilesystemFaults;

require_once __DIR__ . '/fixtures/ProviderTestCase.php';

/**
 * Exercises generated tests and checks their destination handling.
 */
final class GeneratedComponentTest extends ProviderTestCase {

  /**
   * Runs generated tests against valid and broken components.
   */
  public function testGeneratedUnitTestsRunAndDetectContractAndTemplateRegressions(): void {
    [$status, $output, $error] = $this->runCommand(['--with-unit-test', $this->provider, 'my_theme:plain']);
    self::assertSame(0, $status, $error);
    $plain = json_decode($output, TRUE, 512, JSON_THROW_ON_ERROR)['files'][2];
    self::assertSame('tests/src/Unit/PlainSdcTest.php', $plain);
    [$status, $output] = $this->runGeneratedTest($plain);
    self::assertSame(0, $status, $output);
    self::assertStringContainsString('OK (2 tests,', $output);

    [$status, $output, $error] = $this->runCommand(
      ['--with-unit-test', $this->provider, self::ID, '-'],
      '{"props":{"title":"string","count":"integer"},"required":["title"],"slots":["body","footer"],"subdirectory":"cards/nested"}',
    );
    self::assertSame(0, $status, $error);
    [$twig, $metadata, $test] = json_decode($output, TRUE, 512, JSON_THROW_ON_ERROR)['files'];
    [$status, $output] = $this->runGeneratedTest($test);
    self::assertSame(0, $status, $output);
    self::assertStringContainsString('OK (2 tests,', $output);

    $metadataPath = $this->provider . '/' . $metadata;
    $original = file_get_contents($metadataPath);
    file_put_contents($metadataPath, str_replace("type: 'integer'", "type: 'number'", $original));
    [$status, $output] = $this->runGeneratedTest($test);
    self::assertNotSame(0, $status);
    self::assertStringContainsString('testMetadataContract', $output);
    file_put_contents($metadataPath, $original);

    file_put_contents($this->provider . '/' . $twig, '{% missing_tag %}');
    [$status, $output] = $this->runGeneratedTest($test);
    self::assertNotSame(0, $status);
    self::assertStringContainsString('testTwigStarter', $output);
    self::assertStringContainsString('Twig\Error\SyntaxError', $output);
  }

  /**
   * Checks that a conflicting test filename prevents all writes.
   */
  public function testRefusesCollidingTestNamesBeforeWritingComponent(): void {
    [$status, $output, $error] = $this->runCommand(['--with-unit-test', $this->provider, self::ID]);
    self::assertSame(0, $status, $error);
    $test = $this->provider . '/' . json_decode($output, TRUE, 512, JSON_THROW_ON_ERROR)['files'][2];
    $original = file_get_contents($test);

    [$status, , $error] = $this->runCommand(['--with-unit-test', $this->provider, 'my_theme:teaser_card']);
    self::assertSame(1, $status);
    self::assertStringContainsString("Destination already exists at '$test'", $error);
    self::assertSame($original, file_get_contents($test));
    self::assertDirectoryDoesNotExist($this->provider . '/components/teaser_card');
  }

  /**
   * Checks that generated tests never follow destination symlinks.
   */
  public function testRefusesSymlinkAtTestDirectoryOrFile(): void {
    $elsewhere = $this->provider . '/elsewhere';
    mkdir($elsewhere);
    $tests = $this->provider . '/tests';
    symlink($elsewhere, $tests);
    [$status, , $error] = $this->runCommand(['--with-unit-test', $this->provider, self::ID]);
    self::assertSame(1, $status);
    self::assertStringContainsString("Refusing to use symlink '$tests'", $error);
    self::assertSame([], glob($elsewhere . '/*'));
    self::assertDirectoryDoesNotExist($this->provider . '/components');

    unlink($tests);
    mkdir($tests . '/src/Unit', 0777, TRUE);
    $test = $tests . '/src/Unit/TeaserCardSdcTest.php';
    symlink($elsewhere . '/missing.php', $test);
    [$status, , $error] = $this->runCommand(['--dry-run', '--with-unit-test', $this->provider, self::ID]);
    self::assertSame(1, $status);
    self::assertStringContainsString("Refusing to use symlink '$test'", $error);
    self::assertDirectoryDoesNotExist($this->provider . '/components');
  }

  /**
   * Checks rollback after a partial generated-test write.
   */
  public function testTestWriteFailureRollsBackBothDestinations(): void {
    mkdir($this->provider . '/tests');
    FilesystemFaults::$operation = 'test-short-write';
    [$status, , $error] = $this->runCommand(['--with-unit-test', $this->provider, self::ID]);

    self::assertSame(1, $status);
    self::assertStringContainsString('Cannot write complete file', $error);
    self::assertStringContainsString('tests/src/Unit/TeaserCardSdcTest.php', $error);
    self::assertDirectoryDoesNotExist($this->provider . '/components');
    self::assertDirectoryDoesNotExist($this->provider . '/tests/src');
    self::assertDirectoryExists($this->provider . '/tests');
  }

  /**
   * Checks that rollback failures identify the paths left on disk.
   */
  public function testReportsPathsLeftAfterCleanupFailure(): void {
    FilesystemFaults::$operation = 'cleanup-failure';
    [$status, , $error] = $this->runCommand(['--with-unit-test', $this->provider, self::ID]);
    $leftover = $this->provider . '/tests/src/Unit/TeaserCardSdcTest.php';

    self::assertSame(1, $status);
    self::assertStringContainsString("Could not clean up: $leftover", $error);
    self::assertFileExists($leftover);
    self::assertDirectoryDoesNotExist($this->provider . '/components');
  }

  /**
   * Runs a generated test in a separate PHPUnit process.
   */
  private function runGeneratedTest(string $test): array {
    $process = proc_open([
      PHP_BINARY,
      __DIR__ . '/../vendor/bin/phpunit',
      '--no-configuration',
      '--bootstrap',
      __DIR__ . '/GeneratedTestBootstrap.php',
      $this->provider . '/' . $test,
    ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
    self::assertIsResource($process);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return [proc_close($process), $output];
  }

}
