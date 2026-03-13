<?php
require __DIR__ . '/../config/bootstrap.php';

$username = 'smoke_user';
$password = 'Smoke@123456';

$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute([$username]);
$row = $stmt->fetch();

if (!$row) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO users (username,password_hash,full_name,role,is_approved,approved_at,email_verified_at) VALUES (?,?,?,?,1,datetime('now'),datetime('now'))");
    $ins->execute([$username, $hash, 'Smoke User', 'user']);
    echo "created\n";
} else {
    $upd = $pdo->prepare("UPDATE users SET role='user', is_approved=1, approved_at=COALESCE(approved_at, datetime('now')) WHERE id=?");
    $upd->execute([(int)$row['id']]);
    echo "updated\n";
}

echo "username=$username\npassword=$password\n";
