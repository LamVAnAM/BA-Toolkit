<?php
// diag_pdo.php
$dbPath = __DIR__ . '/database/ba.sqlite';
$pdo = new PDO("sqlite:$dbPath");

$userId = 1;
$stmt = $pdo->prepare("SELECT key_name, value FROM settings WHERE user_id = ?");
$stmt->execute([$userId]);
$userSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

echo "FETECHED FOR USER 1:\n";
print_r($userSettings);

$globalStmt = $pdo->query("SELECT key_name, value FROM settings WHERE user_id = 0");
$globalSettings = $globalStmt->fetchAll(PDO::FETCH_KEY_PAIR);
echo "\nFETECHED FOR GLOBAL 0:\n";
print_r($globalSettings);

$merged = array_merge($globalSettings, $userSettings);
echo "\nMERGED:\n";
print_r($merged);
