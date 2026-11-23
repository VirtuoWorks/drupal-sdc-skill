<?php

declare(strict_types=1);

use DrupalSdcSkill\FilesystemFaults;

require_once __DIR__ . '/fixtures/ProviderTestCase.php';

/**
 * Checks collisions, locking, and rollback when writing components.
 */
final class ScaffolderFilesystemTest extends ProviderTestCase {

  /**
   * Checks that retries and nested duplicates preserve existing files.
   */
  public function testRetryAndNestedDuplicateLeaveOriginalFilesIntact(): void {
    [$status, , $error] = $this->runCommand([$this->provider, self::ID, '-'], '{"subdirectory":"nested/cards"}');
    self::assertSame(0, $status, $error);

    $twig = $this->provider . '/components/nested/cards/teaser-card/teaser-card.twig';
    file_put_contents($twig, 'edited');
    [$status, , $error] = $this->runCommand([$this->provider, self::ID, '-'], '{"subdirectory":"nested/cards"}');
    self::assertSame(1, $status);
    self::assertStringContainsString('Destination already exists', $error);

    [$status, , $error] = $this->runCommand([$this->provider, self::ID]);
    self::assertSame(1, $status);
    self::assertStringContainsString("Component 'teaser-card' already exists", $error);
    self::assertSame('edited', file_get_contents($twig));
    self::assertDirectoryDoesNotExist($this->provider . '/components/teaser-card');
  }

  /**
   * Checks that the component destination cannot be a symlink.
   */
  public function testRefusesSymlinkInDestination(): void {
    mkdir($this->provider . '/elsewhere');
    symlink($this->provider . '/elsewhere', $this->provider . '/components');
    [$status, , $error] = $this->runCommand([$this->provider, self::ID]);

    self::assertSame(1, $status);
    self::assertStringContainsString('Refusing to use symlink', $error);
    self::assertDirectoryDoesNotExist($this->provider . '/elsewhere/teaser-card');
  }

  /**
   * Checks rollback after a directory creation failure.
   */
  public function testRollsBackAfterDirectoryCreationFailure(): void {
    $spec = json_encode(['subdirectory' => str_repeat('a', 256)], JSON_THROW_ON_ERROR);
    [$status, , $error] = $this->runCommand([$this->provider, self::ID, '-'], $spec);

    self::assertSame(1, $status);
    self::assertStringContainsString('Cannot create directory', $error);
    self::assertDirectoryDoesNotExist($this->provider . '/components');
  }

  /**
   * Checks rollback after a file creation failure.
   */
  public function testRollsBackAfterFileCreationFailure(): void {
    $component = str_repeat('a', 245);
    [$status, , $error] = $this->runCommand([$this->provider, "my_theme:$component"]);

    self::assertSame(1, $status);
    self::assertStringContainsString('Cannot create file', $error);
    self::assertDirectoryDoesNotExist($this->provider . '/components');
  }

  /**
   * Checks partial-write cleanup and a subsequent successful retry.
   */
  public function testRollsBackOnShortWriteAndCanRetry(): void {
    mkdir($this->provider . '/components');
    file_put_contents($this->provider . '/components/existing.txt', 'existing');
    FilesystemFaults::$operation = 'short-write';
    [$status, , $error] = $this->runCommand([$this->provider, self::ID]);

    self::assertSame(1, $status);
    self::assertStringContainsString('Cannot write complete file', $error);
    self::assertDirectoryDoesNotExist($this->provider . '/components/teaser-card');
    self::assertSame('existing', file_get_contents($this->provider . '/components/existing.txt'));

    FilesystemFaults::$operation = NULL;
    [$status, , $error] = $this->runCommand([$this->provider, self::ID]);
    self::assertSame(0, $status, $error);
  }

  /**
   * Checks that a lock failure prevents writes.
   */
  public function testExitsBeforeWritingWhenLockIsUnavailable(): void {
    foreach (['open-lock' => 'Cannot open provider directory', 'lock' => 'Cannot lock provider directory'] as $fault => $message) {
      FilesystemFaults::$operation = $fault;
      [$status, , $error] = $this->runCommand([$this->provider, self::ID]);

      self::assertSame(1, $status);
      self::assertStringContainsString($message, $error);
      self::assertDirectoryDoesNotExist($this->provider . '/components');
    }
  }

  /**
   * Checks that concurrent runs create only one component per ID.
   */
  public function testConcurrentCliRunsInDifferentGroupsProduceOneComponent(): void {
    $processes = [];
    foreach (['one', 'two'] as $group) {
      $process = proc_open(
        [PHP_BINARY, __DIR__ . '/../scripts/scaffold.php', '--with-unit-test', $this->provider, self::ID, '-'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
      );
      self::assertIsResource($process);
      fwrite($pipes[0], json_encode(['subdirectory' => $group], JSON_THROW_ON_ERROR));
      fclose($pipes[0]);
      $processes[] = [$process, $pipes[1], $pipes[2]];
    }

    $results = [];
    foreach ($processes as [$process, $out, $err]) {
      $output = stream_get_contents($out);
      $error = stream_get_contents($err);
      fclose($out);
      fclose($err);
      $results[proc_close($process)] = [$output, $error];
    }

    self::assertCount(2, $results);
    self::assertSame('', $results[0][1]);
    self::assertStringContainsString('already exists', $results[1][1]);
    self::assertCount(1, glob($this->provider . '/components/*/teaser-card/*.component.yml'));
    self::assertFileExists($this->provider . '/tests/src/Unit/TeaserCardSdcTest.php');
  }

}
