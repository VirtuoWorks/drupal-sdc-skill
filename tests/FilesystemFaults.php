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
    if (FilesystemFaults::$operation === 'short-write' && str_ends_with(stream_get_meta_data($stream)['uri'], '.component.yml')) {
        return \fwrite($stream, substr($contents, 0, 5));
    }
    return \fwrite($stream, $contents);
}

function file_get_contents(string $path): string|false
{
    return FilesystemFaults::$operation === 'read-spec' ? false : \file_get_contents($path);
}
