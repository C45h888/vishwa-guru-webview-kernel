<?php
$sql = file_get_contents("/app/schema-neon/V1-schema.sql");
$commentRegex = "/COMMENT\\s+ON\\s+(?:TABLE|COLUMN|EXTENSION|SCHEMA|INDEX|CONSTRAINT|SEQUENCE|VIEW|FUNCTION|TRIGGER|TYPE|RULE|POLICY|EVENT\\s+TRIGGER)\\s+\\w+(?:\\.\\w+)*\\s+IS\\s+'(?:[^']|'')*'\\s*;/i";
$replace = preg_replace($commentRegex, '/* COMMENT ON stripped */', $sql, -1, $count);
echo "Comment replacements: $count\n";
$lines = explode("\n", $replace);
echo "=== Lines 185-210 ===\n";
for ($i = 185; $i < min(210, count($lines)); $i++) {
    echo str_pad(strval($i+1), 4) . ": " . $lines[$i] . "\n";
}