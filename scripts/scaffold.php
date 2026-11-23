<?php

declare(strict_types=1);

use DrupalSdcSkill\ScaffoldCommand;

require_once __DIR__ . '/ScaffoldCommand.php';

exit((new ScaffoldCommand())->run($argv, STDIN, STDOUT, STDERR));
