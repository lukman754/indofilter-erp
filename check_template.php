<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (glob(base_path('templates/*.docx')) as $f) {
    if (str_contains(basename($f), '~$')) continue;
    try {
        $tp = new \PhpOffice\PhpWord\TemplateProcessor($f);
        echo basename($f) . ":\n";
        foreach ($tp->getVariables() as $v) {
            echo "  - \${$v}\n";
        }
    } catch (\Exception $e) {
        echo "Error " . basename($f) . ": " . $e->getMessage() . "\n";
    }
}
