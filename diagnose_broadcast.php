<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

echo "Broadcast connection: ".Config::get('broadcasting.default').PHP_EOL;
echo "Queue connection: ".Config::get('queue.default').PHP_EOL;

try {
    $jobs = DB::table('jobs')->count();
    echo "Jobs pending: {$jobs}".PHP_EOL;
} catch (\Throwable $e) {
    echo "Jobs pending: n/a (".$e->getMessage().")".PHP_EOL;
}

try {
    $failed = DB::table('failed_jobs')->count();
    echo "Failed jobs: {$failed}".PHP_EOL;
} catch (\Throwable $e) {
    echo "Failed jobs: n/a (".$e->getMessage().")".PHP_EOL;
}

echo "OK".PHP_EOL;


