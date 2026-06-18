<?php
// Fix ALL placeholder syntax in generate_templates.php
// Replace {word} with ${word} in string literals, but NOT in PHP code
$file = __DIR__ . '/generate_templates.php';
$content = file_get_contents($file);

// Replace all {placeholder} with ${placeholder} inside strings
// This pattern matches { followed by word chars, followed by }
$content = preg_replace(
    '/(?<=[\'"])[^\'"]*?\K\{([a-z_][a-z0-9_]*)\}(?=[^\'"]*?[\'"])/i',
    '$${$1}',
    $content
);

file_put_contents($file, $content);
echo "Fixed all placeholder syntax in generate_templates.php\n";
