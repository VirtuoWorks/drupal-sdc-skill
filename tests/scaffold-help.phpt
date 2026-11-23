--TEST--
The PHP entry point prints concise usage and accepts help without a declaration.
--ARGS--
--help
--FILE--
<?php
require __DIR__ . '/../scripts/scaffold.php';
--EXPECTF--
Usage: php scaffold.php [--dry-run] [--with-unit-test] <provider-dir> <provider:component> [<spec.json>|-]

Generate a Drupal 10.3+ Single-Directory Component starter.%A
