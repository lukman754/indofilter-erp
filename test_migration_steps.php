<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

@unlink('G:/My Drive/ERP/database.sqlite');
touch('G:/My Drive/ERP/database.sqlite');

$migrationFiles = glob(__DIR__ . '/database/migrations/*.php');
sort($migrationFiles);

foreach ($migrationFiles as $file) {
    $filename = basename($file);
    echo "Running migration: {$filename}... ";
    
    Artisan::call('migrate', [
        '--path' => 'database/migrations/' . $filename,
        '--force' => true,
    ]);
    
    if (Schema::hasTable('documents')) {
        $cols = Schema::getColumnListing('documents');
        $hasIsp = in_array('is_ppn', $cols) ? "is_ppn:YES" : "is_ppn:NO";
        $hasRec = in_array('recipient_address', $cols) ? "rec_addr:YES" : "rec_addr:NO";
        echo "{$hasIsp}, {$hasRec} (Total: " . count($cols) . ")\n";
    } else {
        echo "documents table does not exist.\n";
    }
}
