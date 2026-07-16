<?php
$sql = "/* a */\n-- comment\nCREATE TABLE foo (\n    id INT\n);\n";
$lines = explode("\n", $sql);
$current = '';
$statements = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '--')) {
        continue;
    }
    $current .= $line . "\n";
    if (str_ends_with($trimmed, ';')) {
        $statements[] = trim($current);
        $current = '';
    }
}
print_r($statements);