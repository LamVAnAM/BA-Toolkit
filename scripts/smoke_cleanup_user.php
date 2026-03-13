<?php
require __DIR__ . '/../config/bootstrap.php';

$stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
$stmt->execute(['smoke_user']);
echo 'deleted=' . $stmt->rowCount() . PHP_EOL;
