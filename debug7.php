<?php
$sql = file_get_contents("/app/schema-neon/V1-schema.sql");
$rewrites = [
    '/\b\w+(?:\s*\([^)]*\))?\s*\[\s*\]/i' => 'TEXT',
    '/\bTIMESTAMPTZ\b/i' => 'TEXT',
    '/\bJSONB\b/i' => 'TEXT',
    '/\bBOOLEAN\b/i' => 'INTEGER',
    '/\bUUID\b/i' => 'TEXT',
];
$replace = preg_replace(array_keys($rewrites), array_values($rewrites), $sql);
$lines = explode("\n", $replace);
echo "=== Lines 185-205 ===\n";
for ($i = 185; $i < min(205, count($lines)); $i++) {
    echo str_pad(strval($i+1), 4) . ": " . $lines[$i] . "\n";
}