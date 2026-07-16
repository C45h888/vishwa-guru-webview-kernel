<?php
$sql = file_get_contents("/app/schema-neon/V1-schema.sql");
$rewrites = [
    '/CREATE\s+EXTENSION[^;]*;/i' => '/* CREATE EXTENSION stripped for sqlite */',
    '/CREATE\s+TYPE\s+\w+\s+AS\s+ENUM\s*\([^)]+\)\s*;/i' => '/* CREATE TYPE ENUM stripped for sqlite */',
    '/CREATE\s+(OR\s+REPLACE\s+)?FUNCTION[\s\S]*?LANGUAGE\s+\w+;/i' => '/* CREATE FUNCTION stripped for sqlite */',
    '/CREATE\s+(OR\s+REPLACE\s+)?(CONSTRAINT\s+)?TRIGGER[\s\S]*?;/i' => '/* CREATE TRIGGER stripped for sqlite */',
    "/COMMENT\\s+ON\\s+(?:TABLE|COLUMN|EXTENSION|SCHEMA|INDEX|CONSTRAINT|SEQUENCE|VIEW|FUNCTION|TRIGGER|TYPE|RULE|POLICY|EVENT\\s+TRIGGER)\\s+\\w+(?:\\.\\w+)*\\s+IS\\s+'(?:[^']|'')*'\\s*;/i" => '/* COMMENT ON stripped for sqlite */',
    '/\b\w+(?:\s*\([^)]*\))?\s*\[\s*\]/i' => 'TEXT',
    '/\bTIMESTAMPTZ\b/i' => 'TEXT',
    '/\bJSONB\b/i' => 'TEXT',
    '/\bBOOLEAN\b/i' => 'INTEGER',
    '/\bUUID\b/i' => 'TEXT',
    '/GENERATED\s+ALWAYS\s+AS\s+IDENTITY/i' => '',
    '/CREATE\s+(UNIQUE\s+)?INDEX\s+\w+\s+ON\s+(\w+)\s*\([^)]*\)\s*WHERE[^;]+;/i' => '/* CREATE PARTIAL INDEX stripped for sqlite */',
];
$out = preg_replace(array_keys($rewrites), array_values($rewrites), $sql);
$lines = explode("\n", $out);
echo "=== Lines 185-205 ===\n";
for ($i = 185; $i < min(205, count($lines)); $i++) {
    echo str_pad(strval($i+1), 4) . ": " . $lines[$i] . "\n";
}