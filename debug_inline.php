<?php
$sql = "/* CREATE EXTENSION stripped for sqlite */\n/* CREATE EXTENSION stripped for sqlite */\n/* CREATE EXTENSION stripped for sqlite */          -- enables EXCLUDE on static\n/* CREATE TYPE ENUM stripped for sqlite */\nCREATE TABLE currencies (\n    code               CHAR(3)        PRIMARY KEY,\n    name               TEXT           NOT NULL\n);\n";
$pdo = new PDO("sqlite::memory:");
try {
    $pdo->exec($sql);
    echo "OK\n";
} catch (PDOException $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}