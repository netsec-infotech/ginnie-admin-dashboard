<?php

use App\Services\SystemUpdater;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 2);
if (PHP_SAPI !== 'cli' || ! isset($argv[1]) || ! ctype_digit($argv[1])) {
    throw new RuntimeException('A valid system update ID is required.');
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$app->make(SystemUpdater::class)->run((int) $argv[1]);
