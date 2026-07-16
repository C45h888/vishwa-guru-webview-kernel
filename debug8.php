<?php
$sql = file_get_contents("/app/schema-neon/V1-schema.sql");
$regex = '/\b\w+(?:\s*\([^)]*\))?\s*\[\s*\]/i';
preg_match_all($regex, $sql, $matches, PREG_OFFSET_CAPTURE);
foreach ($matches[0] as $i => $match) {
    $offset = $match[1];
    $context = substr($sql, max(0, $offset - 40), 80);
    echo "Match #$i: '{$match[0]}' at offset $offset\n";
    echo "  Context: ..." . str_replace("\n", "\\n", $context) . "...\n";
}