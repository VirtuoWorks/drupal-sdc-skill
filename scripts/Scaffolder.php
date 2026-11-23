<?php

declare(strict_types=1);

namespace DrupalSdcSkill;

require_once __DIR__ . '/ComponentUnitTest.php';

/**
 * Generates component starter files in a Drupal provider.
 */
final class Scaffolder {

  /**
   * Generates or previews the requested component starter.
   *
   * @return list<string>
   *   The provider-relative file paths.
   */
  public function generate(string $path, ComponentSpec $spec, bool $dryRun, bool $withUnitTest = FALSE): array {
    $provider = realpath($path);
    if ($provider === FALSE || !is_dir($provider) || !is_file("$provider/$spec->provider.info.yml")) {
      throw new \InvalidArgumentException("Expected '$spec->provider.info.yml' in provider directory '$path'.");
    }

    $folders = ['components', ...$spec->subdirectories, $spec->component];
    $relative = implode('/', $folders);
    $files = [];
    foreach ($this->renderFiles($spec) as $filename => $contents) {
      $files["$relative/$filename"] = $contents;
    }
    $testFilename = NULL;
    if ($withUnitTest) {
      $test = new ComponentUnitTest($spec, $relative);
      $testFilename = $test->filename();
      $files[ComponentUnitTest::DIRECTORY . '/' . $testFilename] = $test->render();
    }

    $inspect = fn (): array => $this->inspectDestination($provider, $folders, $spec->component, $testFilename);
    if ($dryRun) {
      $inspect();
    }
    else {
      $this->writeFiles($provider, $files, $inspect);
    }

    return array_keys($files);
  }

  /**
   * Check the selected path and return any directories that need to be created.
   *
   * @param string $provider
   *   The canonical provider directory path.
   * @param list<string> $folders
   *   The relative segments leading to the component directory.
   * @param string $component
   *   The component machine name.
   * @param string|null $testFilename
   *   The optional filename for the generated unit test.
   *
   * @return list<string>
   *   The absolute paths of directories that must be created.
   */
  private function inspectDestination(string $provider, array $folders, string $component, ?string $testFilename): array {
    $missing = $this->inspectPath($provider, $folders, TRUE);

    $root = "$provider/components";
    if (is_dir($root)) {
      $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS),
        );
      foreach ($entries as $entry) {
        if ($entry->getFilename() === "$component.component.yml") {
          throw new \RuntimeException("Component '$component' already exists at '" . $entry->getPathname() . "'.");
        }
      }
    }

    if ($testFilename === NULL) {
      return $missing;
    }

    $testFolders = explode('/', ComponentUnitTest::DIRECTORY);
    return [...$missing, ...$this->inspectPath($provider, [...$testFolders, $testFilename], FALSE)];
  }

  /**
   * Check each path segment and return missing directories to create.
   *
   * @param string $directory
   *   The absolute starting directory.
   * @param list<string> $segments
   *   The path segments to inspect in order.
   * @param bool $endsInDirectory
   *   Whether the final segment is a directory.
   *
   * @return list<string>
   *   The absolute paths of directories that must be created.
   */
  private function inspectPath(string $directory, array $segments, bool $endsInDirectory): array {
    $missing = [];
    $last = array_key_last($segments);
    foreach ($segments as $index => $segment) {
      $directory .= "/$segment";
      clearstatcache(TRUE, $directory);
      if (is_link($directory)) {
        throw new \RuntimeException("Refusing to use symlink '$directory'.");
      }
      if (file_exists($directory)) {
        if (!is_dir($directory) || $index === $last) {
          throw new \RuntimeException("Destination already exists at '$directory'.");
        }
      }
      elseif ($endsInDirectory || $index !== $last) {
        $missing[] = $directory;
      }
    }
    return $missing;
  }

  /**
   * Renders the metadata, Twig template, and any requested assets.
   *
   * @return array<string, string>
   *   Filenames mapped to contents in write order.
   */
  private function renderFiles(ComponentSpec $spec): array {
    $component = $spec->component;
    $metadata = 'name: ' . self::quote($spec->name) . "\n";
    if ($spec->description !== NULL) {
      $metadata .= 'description: ' . self::quote($spec->description) . "\n";
    }
    $metadata .= "props:\n  type: object\n";
    if ($spec->required !== []) {
      $metadata .= "  required:\n";
      foreach ($spec->required as $prop) {
        $metadata .= '    - ' . self::quote($prop) . "\n";
      }
    }
    if ($spec->props === []) {
      $metadata .= "  properties: {}\n";
    }
    else {
      $metadata .= "  properties:\n";
      foreach ($spec->props as $prop => $type) {
        $title = ucwords(str_replace('_', ' ', $prop));
        $metadata .= '    ' . self::quote($prop) . ":\n";
        $metadata .= '      type: ' . self::quote($type) . "\n";
        $metadata .= '      title: ' . self::quote($title) . "\n";
      }
    }

    $twig = "<div{{ attributes.addClass('$component') }}>\n";
    if ($spec->slots !== []) {
      $metadata .= "slots:\n";
      foreach ($spec->slots as $slot) {
        $title = ucwords(str_replace('_', ' ', $slot));
        $metadata .= '  ' . self::quote($slot) . ":\n";
        $metadata .= '    title: ' . self::quote($title) . "\n";
        $twig .= "  {% block $slot %}{{ $slot|default('') }}{% endblock %}\n";
      }
    }
    $twig .= "</div>\n";

    $files = ["$component.twig" => $twig];
    foreach ($spec->assets as $asset) {
      if ($asset === 'js') {
        $metadata .= "libraryOverrides:\n  dependencies:\n    - core/drupal\n    - core/once\n";
        $files["$component.js"] = <<<JS
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors['$spec->provider:$component'] = {
    attach(context) {
      once('$spec->provider:$component', '.$component', context).forEach((element) => {
        // Add component behavior here.
      });
    },
  };
})(Drupal, once);

JS;
      }
      else {
        $files["$component.$asset"] = ".$component {\n  /* Add component styles here. */\n}\n";
      }
    }

    // Write metadata after the template and assets it describes.
    $files["$component.component.yml"] = $metadata;
    return $files;
  }

  /**
   * Quotes a scalar for the generated YAML metadata.
   */
  private static function quote(string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
  }

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
  private function writeFiles(string $provider, array $files, callable $inspect): void {
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
    catch (\Throwable $exception) {
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

}
