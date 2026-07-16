<?php
$sql = file_get_contents("/app/schema-neon/V1-schema.sql");
// Apply only the partial-index regex
$partialIndexRegex = '/CREATE\s+(UNIQUE\s+)?INDEX\s+\w+\s+ON\s+(\w+)\s*\([^)]*\)\s*WHERE[^;]+;/i';
$replace = preg_replace($partialIndexRegex, '/* CREATE PARTIAL INDEX stripped */', $sql, -1, $count);
echo "Replacements: $count\n";
file_put_contents("/app/rewritten_pi.sql", $replace);
$lines = explode("\n", $replace);
echo "=== Lines 185-205 ===\n";
for ($i = 185; $i < min(205, count($lines)); $i++) {
    echo str_pad(strval($i+1), 4) . ": " . $lines[$i] . "\n";
}