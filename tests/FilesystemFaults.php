<?php

declare(strict_types=1);

namespace DrupalSdcSkill;

/**
 * Selects the filesystem failure simulated for a test.
 */
final class FilesystemFaults {

  /**
   * The operation to fail, or NULL for normal filesystem calls.
   */
  public static ?string $operation = NULL;

}

/**
 * Simulates an unavailable lock stream.
 */
function fopen(string $path, string $mode) {
  if (FilesystemFaults::$operation === 'open-lock' && $mode === 'r') {
    return FALSE;
  }
  return \fopen($path, $mode);
}

/**
 * Simulates a provider lock failure.
 */
function flock($stream, int $operation): bool {
  return FilesystemFaults::$operation !== 'lock' && \flock($stream, $operation);
}

/**
 * Simulates a partial write to a selected destination.
 */
function fwrite($stream, string $contents): int|false {
  $suffix = match (FilesystemFaults::$operation) {
    'short-write' => '.component.yml',
    'test-short-write', 'cleanup-failure' => 'SdcTest.php',
    default => NULL,
  };
  if ($suffix !== NULL && str_ends_with(stream_get_meta_data($stream)['uri'], $suffix)) {
    return \fwrite($stream, substr($contents, 0, 5));
  }
  return \fwrite($stream, $contents);
}

/**
 * Simulates a file that cannot be removed during rollback.
 */
function unlink(string $path): bool {
  if (FilesystemFaults::$operation === 'cleanup-failure' && str_ends_with($path, 'SdcTest.php')) {
    return FALSE;
  }
  return \unlink($path);
}

/**
 * Simulates an unreadable specification file.
 */
function file_get_contents(string $path): string|false {
  return FilesystemFaults::$operation === 'read-spec' ? FALSE : \file_get_contents($path);
}
