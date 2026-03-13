<?php
// diag_settings.php
require_once __DIR__ . '/config/bootstrap.php';
header('Content-Type: text/plain');

$userId = getCurrentUserId();
echo "Current User ID: $userId\n";

$dbPath = __DIR__ . '/database/ba.sqlite';
$pdo = new PDO("sqlite:$dbPath");

echo "\n--- ALL SETTINGS IN DB ---\n";
$stmt = $pdo->query("SELECT * FROM settings");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- SETTINGS RETURNED BY loadSettingsMap ---\n";
$settings = loadSettingsMap($pdo, $userId);
print_r($settings);
