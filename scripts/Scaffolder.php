<?php

declare(strict_types=1);

namespace DrupalSdcSkill;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class Scaffolder
{
    /** @return list<string> Provider-relative file paths. */
    public function generate(string $path, ComponentSpec $spec, bool $dryRun): array
    {
        $provider = realpath($path);
        if ($provider === false || !is_dir($provider) || !is_file("$provider/$spec->provider.info.yml")) {
            throw new InvalidArgumentException("Expected '$spec->provider.info.yml' in provider directory '$path'.");
        }

        $folders = ['components', ...$spec->subdirectories, $spec->component];
        $relative = implode('/', $folders);
        $files = $this->renderFiles($spec);

        if ($dryRun) {
            $this->inspectDestination($provider, $folders, $spec->component);
        } else {
            $this->writeFiles($provider, $folders, $spec->component, $files);
        }

        return array_map(static fn (string $name): string => "$relative/$name", array_keys($files));
    }

    /**
     * Check the selected path and return any directories that need to be created.
     *
     * @param list<string> $folders
     * @return list<string>
     */
    private function inspectDestination(string $provider, array $folders, string $component): array
    {
        $missing = [];
        $directory = $provider;
        foreach ($folders as $index => $folder) {
            $directory .= "/$folder";
            clearstatcache(true, $directory);
            if (is_link($directory)) {
                throw new RuntimeException("Refusing to use symlink '$directory'.");
            }
            if (!file_exists($directory)) {
                $missing[] = $directory;
                continue;
            }
            if (!is_dir($directory) || $index === array_key_last($folders)) {
                throw new RuntimeException("Destination already exists at '$directory'.");
            }
        }

        $root = "$provider/components";
        if (is_dir($root)) {
            $entries = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            );
            foreach ($entries as $entry) {
                if ($entry->getFilename() === "$component.component.yml") {
                    throw new RuntimeException("Component '$component' already exists at '" . $entry->getPathname() . "'.");
                }
            }
        }

        return $missing;
    }

    /** @return array<string, string> Filenames mapped to contents in write order. */
    private function renderFiles(ComponentSpec $spec): array
    {
        $component = $spec->component;
        $metadata = 'name: ' . self::quote($spec->name) . "\n";
        if ($spec->description !== null) {
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
        } else {
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
            } else {
                $files["$component.$asset"] = ".$component {\n  /* Add component styles here. */\n}\n";
            }
        }

        // Write metadata after the template and assets it describes.
        $files["$component.component.yml"] = $metadata;
        return $files;
    }

    private static function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * @param list<string> $folders
     * @param array<string, string> $files
     */
    private function writeFiles(string $provider, array $folders, string $component, array $files): void
    {
        // Hold a lock on the existing provider so concurrent runs in different
        // groups cannot both claim the same component ID.
        $lock = @fopen($provider, 'r');
        if ($lock === false) {
            throw new RuntimeException("Cannot open provider directory '$provider' for locking.");
        }

        $createdDirectories = [];
        $createdFiles = [];
        try {
            if (!@flock($lock, LOCK_EX)) {
                throw new RuntimeException("Cannot lock provider directory '$provider'.");
            }
            foreach ($this->inspectDestination($provider, $folders, $component) as $directory) {
                if (!@mkdir($directory)) {
                    throw new RuntimeException("Cannot create directory '$directory'.");
                }
                $createdDirectories[] = $directory;
            }

            $target = $provider . '/' . implode('/', $folders);
            foreach ($files as $name => $contents) {
                $file = "$target/$name";
                $stream = @fopen($file, 'xb');
                if ($stream === false) {
                    throw new RuntimeException("Cannot create file '$file'.");
                }
                $createdFiles[] = $file;
                $written = @fwrite($stream, $contents);
                fclose($stream);
                if ($written !== strlen($contents)) {
                    throw new RuntimeException("Cannot write complete file '$file'.");
                }
            }
        } catch (Throwable $exception) {
            foreach (array_reverse($createdFiles) as $file) {
                @unlink($file);
            }
            foreach (array_reverse($createdDirectories) as $directory) {
                @rmdir($directory);
            }
            throw $exception;
        } finally {
            fclose($lock);
        }
    }
}
