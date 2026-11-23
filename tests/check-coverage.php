<?php

/**
 * @file
 * Checks that a Clover report records complete PHP line coverage.
 */

declare(strict_types=1);

$report = simplexml_load_file($argv[1]);
$metrics = $report->project->metrics ?? NULL;
$lines = (int) ($metrics['statements'] ?? 0);
$covered = (int) ($metrics['coveredstatements'] ?? 0);

if ($lines === 0 || $lines !== $covered) {
  fwrite(STDERR, "Expected 100% PHP line coverage (covered $covered of $lines lines).\n");
  exit(1);
}
