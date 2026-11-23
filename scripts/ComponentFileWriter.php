<?php

declare(strict_types=1);

namespace DrupalSdcSkill;

/**
 * Writes component files under a provider lock and rolls back partial writes.
 */
final class ComponentFileWriter {

  /**
   * Writes the requested files under a lock and rolls back failed writes.
   *
   * @param string $provider
   *   The canonical provider directory path.
   * @param array<string, string> $files
   *   Provider-relative paths mapped to their contents.
   * @param callable(): list<string> $inspect
   *   The destination check that returns the directories to create.
   */
  public function write(string $provider, array $files, callable $inspect): void {
    // Hold a lock on the existing provider so concurrent runs in different
    // groups cannot both claim the same component ID.
    $lock = @fopen($provider, 'r');
    if ($lock === FALSE) {
      throw new \RuntimeException("Cannot open provider directory '$provider' for locking.");
    }

    $createdDirectories = [];
    $createdFiles = [];
    try {
      if (!@flock($lock, LOCK_EX)) {
        throw new \RuntimeException("Cannot lock provider directory '$provider'.");
      }
      foreach ($inspect() as $directory) {
        if (!@mkdir($directory)) {
          throw new \RuntimeException("Cannot create directory '$directory'.");
        }
        $createdDirectories[] = $directory;
      }

      $this->createFiles($provider, $files, $createdFiles);
    }
    catch (\Throwable $exception) {
      $remaining = $this->removeCreatedPaths($createdFiles, $createdDirectories);
      if ($remaining !== []) {
        throw new \RuntimeException(
          $exception->getMessage() . ' Could not clean up: ' . implode(', ', $remaining) . '.',
          0,
          $exception,
        );
      }
      throw $exception;
    }
    finally {
      fclose($lock);
    }
  }

  /**
   * Creates files exclusively and records each path before writing to it.
   *
   * @param string $provider
   *   The canonical provider directory path.
   * @param array<string, string> $files
   *   The provider-relative paths and their contents.
   * @param list<string> $createdFiles
   *   The absolute paths to remove if this operation fails.
   */
  private function createFiles(string $provider, array $files, array &$createdFiles): void {
    foreach ($files as $name => $contents) {
      $file = "$provider/$name";
      $stream = @fopen($file, 'xb');
      if ($stream === FALSE) {
        throw new \RuntimeException("Cannot create file '$file'.");
      }
      $createdFiles[] = $file;
      $written = @fwrite($stream, $contents);
      fclose($stream);
      if ($written !== strlen($contents)) {
        throw new \RuntimeException("Cannot write complete file '$file'.");
      }
    }
  }

  /**
   * Removes newly created paths in reverse order after a failed write.
   *
   * @param list<string> $createdFiles
   *   The absolute paths of files created by this run.
   * @param list<string> $createdDirectories
   *   The absolute paths of directories created by this run.
   *
   * @return list<string>
   *   The absolute paths that could not be removed.
   */
  private function removeCreatedPaths(array $createdFiles, array $createdDirectories): array {
    $remaining = [];
    foreach (array_reverse($createdFiles) as $file) {
      if (!@unlink($file) && file_exists($file)) {
        $remaining[] = $file;
      }
    }
    foreach (array_reverse($createdDirectories) as $directory) {
      if (!@rmdir($directory) && is_dir($directory)) {
        $remaining[] = $directory;
      }
    }
    return $remaining;
  }

}
