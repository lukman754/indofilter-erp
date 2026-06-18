<?php
require __DIR__ . '/vendor/autoload.php';

$r = new ReflectionMethod('PhpOffice\PhpWord\TemplateProcessor', 'setValue');
$file = $r->getFileName();
$lines = file($file);
foreach ($lines as $i => $l) {
    if (strpos($l, 'setValue') !== false || strpos($l, 'ensureLeadingDollar') !== false) {
        echo ($i+1) . ': ' . $l;
    }
}
echo "\n---\n";

// Check the replace function
$content = file_get_contents($file);
if (preg_match('/function replace.*?\n.*?\n.*?\n.*?\n.*?\n/', $content, $m)) {
    echo $m[0] . "\n";
}

if (preg_match('/\$\{.*?\}/', $content, $m)) {
    echo "Found placeholder pattern: " . $m[0] . "\n";
}

// Look for the actual search string pattern
if (preg_match('/\\$search.*?\$/', $content, $m)) {
    echo "Search pattern area: " . substr($content, strpos($content, 'setValue('), 500) . "\n";
}
