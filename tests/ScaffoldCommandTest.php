<?php

declare(strict_types=1);

use DrupalSdcSkill\FilesystemFaults;

require_once __DIR__ . '/fixtures/ProviderTestCase.php';

/**
 * Checks the command options, input streams, and diagnostics.
 */
final class ScaffoldCommandTest extends ProviderTestCase {

  /**
   * Checks that a CLI dry run accepts JSON from standard input.
   */
  public function testCommandDryRunWithStdin(): void {
    [$status, $output, $error] = $this->runCommand(
      ['--dry-run', $this->provider, self::ID, '-'],
      '{"subdirectory":"foo","slots":["body"]}',
    );

    self::assertSame(0, $status, $error);
    self::assertSame('', $error);
    self::assertSame([
      'id' => self::ID,
      'files' => ['components/foo/teaser-card/teaser-card.twig', 'components/foo/teaser-card/teaser-card.component.yml'],
      'dry_run' => TRUE,
    ], json_decode($output, TRUE, 512, JSON_THROW_ON_ERROR));
    self::assertDirectoryDoesNotExist($this->provider . '/components');
  }

  /**
   * Checks that the CLI reads a declaration file.
   */
  public function testCommandReadsDeclarationFile(): void {
    $spec = $this->provider . '/component.json';
    file_put_contents($spec, '{"assets":["css"]}');
    [$status, $output, $error] = $this->runCommand([$this->provider, self::ID, $spec]);

    self::assertSame(0, $status, $error);
    self::assertStringContainsString('teaser-card.css', $output);
    self::assertFileExists($this->provider . '/components/teaser-card/teaser-card.css');
  }

  /**
   * Checks that either option order previews the same files without writes.
   */
  public function testDryRunListsTestPathInEitherFlagOrderWithoutWriting(): void {
    $spec = '{"subdirectory":"layouts/cards","slots":["body"]}';
    foreach ([['--dry-run', '--with-unit-test'], ['--with-unit-test', '--dry-run']] as $flags) {
      [$status, $output, $error] = $this->runCommand([...$flags, $this->provider, self::ID, '-'], $spec);

      self::assertSame(0, $status, $error);
      self::assertSame([
        'id' => self::ID,
        'files' => [
          'components/layouts/cards/teaser-card/teaser-card.twig',
          'components/layouts/cards/teaser-card/teaser-card.component.yml',
          'tests/src/Unit/TeaserCardSdcTest.php',
        ],
        'dry_run' => TRUE,
      ], json_decode($output, TRUE, 512, JSON_THROW_ON_ERROR));
    }
    self::assertDirectoryDoesNotExist($this->provider . '/components');
    self::assertDirectoryDoesNotExist($this->provider . '/tests');
  }

  /**
   * Checks CLI diagnostics for invalid arguments and input failures.
   */
  public function testCommandDiagnostics(): void {
    [$status, $output, $error] = $this->runCommand([]);
    self::assertSame(1, $status);
    self::assertSame('', $output);
    self::assertStringContainsString('Usage:', $error);

    [$status, , $error] = $this->runCommand(['--with-unit-test', '--with-unit-test', $this->provider, self::ID]);
    self::assertSame(1, $status);
    self::assertStringContainsString('may only be supplied once', $error);

    [$status, , $error] = $this->runCommand([$this->provider, self::ID, $this->provider . '/missing.json']);
    self::assertSame(1, $status);
    self::assertStringContainsString('local JSON declaration file', $error);

    [$status, , $error] = $this->runCommand([$this->provider, 'other:teaser-card']);
    self::assertSame(1, $status);
    self::assertStringContainsString('other.info.yml', $error);

    $spec = $this->provider . '/component.json';
    file_put_contents($spec, '{}');
    FilesystemFaults::$operation = 'read-spec';
    [$status, , $error] = $this->runCommand([$this->provider, self::ID, $spec]);
    self::assertSame(1, $status);
    self::assertStringContainsString('Cannot read the JSON declaration', $error);
    self::assertDirectoryDoesNotExist($this->provider . '/components');
  }

}
