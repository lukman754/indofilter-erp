<?php
$s1 = '${company_name}';
echo "Single-quoted: " . $s1 . "\n";
echo "Length: " . strlen($s1) . "\n";
echo "Chars: ";
for ($i = 0; $i < strlen($s1); $i++) {
    echo sprintf("0x%02X ", ord($s1[$i]));
}
echo "\n";

$s2 = '${company_name}';
file_put_contents('test_output.txt', $s2);
echo "Written to test_output.txt\n";
