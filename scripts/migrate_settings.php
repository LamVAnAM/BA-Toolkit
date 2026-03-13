<?php

$dbPath = __DIR__ . '/../database/ba.sqlite';
$pdo = new PDO("sqlite:$dbPath");
$pdo->exec("UPDATE settings SET user_id = 0 WHERE user_id IS NULL");

echo "Done migration\n";
