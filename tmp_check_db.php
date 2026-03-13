<?php
$dbPath = __DIR__ . '/database/ba.sqlite';
$pdo = new PDO("sqlite:$dbPath");
$stmt = $pdo->query("SELECT * FROM settings");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT);
