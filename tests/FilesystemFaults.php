<?php

declare(strict_types=1);

namespace DrupalSdcSkill;

final class FilesystemFaults
{
    public static ?string $operation = null;
}

function fopen(string $path, string $mode)
{
    if (FilesystemFaults::$operation === 'open-lock' && $mode === 'r') {
        return false;
    }
    return \fopen($path, $mode);
}

function flock($stream, int $operation): bool
{
    return FilesystemFaults::$operation !== 'lock' && \flock($stream, $operation);
}

function fwrite($stream, string $contents): int|false
{
    $suffix = match (FilesystemFaults::$operation) {
        'short-write' => '.component.yml',
        'test-short-write', 'cleanup-failure' => 'SdcTest.php',
        default => null,
    };
    if ($suffix !== null && str_ends_with(stream_get_meta_data($stream)['uri'], $suffix)) {
        return \fwrite($stream, substr($contents, 0, 5));
    }
    return \fwrite($stream, $contents);
}

function unlink(string $path): bool
{
    if (FilesystemFaults::$operation === 'cleanup-failure' && str_ends_with($path, 'SdcTest.php')) {
        return false;
    }
    return \unlink($path);
}

function file_get_contents(string $path): string|false
{
    return FilesystemFaults::$operation === 'read-spec' ? false : \file_get_contents($path);
}
