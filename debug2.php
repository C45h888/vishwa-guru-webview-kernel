<?php
$sql = file_get_contents("/app/schema-neon/V1-schema.sql");
$pattern = "/COMMENT\\s+ON\\s+(?:TABLE|COLUMN|EXTENSION|SCHEMA|INDEX|CONSTRAINT|SEQUENCE|VIEW|FUNCTION|TRIGGER|TYPE|RULE|POLICY|EVENT\\s+TRIGGER)\\s+\\w+(?:\\.\\w+)*\\s+IS\\s+\x27(?:[^\x27]|\x27\x27)*\x27\\s*;/i";
$replace = preg_replace($pattern, "/* COMMENT ON stripped for sqlite */", $sql);
file_put_contents("/app/rewritten.sql", $replace);
$rewritten = $replace;
// Apply array
$rewritten = preg_replace('/\b\w+(?:\s*\([^)]*\))?\s*\[\s*\]/i', 'TEXT', $rewritten);
$rewritten = preg_replace('/\bTIMESTAMPTZ\b/i', 'TEXT', $rewritten);
$rewritten = preg_replace('/\bJSONB\b/i', 'TEXT', $rewritten);
$rewritten = preg_replace('/\bBOOLEAN\b/i', 'INTEGER', $rewritten);
$rewritten = preg_replace('/\bUUID\b/i', 'TEXT', $rewritten);
$rewritten = preg_replace('/::TEXT\b/i', '', $rewritten);
file_put_contents("/app/rewritten2.sql", $rewritten);
echo "OK\n";