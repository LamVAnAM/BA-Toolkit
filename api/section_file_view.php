<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/storage_helper.php';

requireAuth();
$userId = (int)getCurrentUserId();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonError('id is required', 400);
}

try {
    $isAdmin = (($_SESSION['role'] ?? 'user') === 'admin');
    $query = $isAdmin
        ? "SELECT * FROM section_files WHERE id = ?"
        : "SELECT * FROM section_files WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute($isAdmin ? [$id] : [$id, $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }

    if (($row['storage_disk'] ?? 'local') !== 'local') {
        http_response_code(501);
        echo 'Storage backend not supported';
        exit;
    }

    $cfg = getUploadStorageConfig($pdo, $isAdmin ? (int)$row['user_id'] : $userId);
    $absPath = resolveStoredFileAbsolutePath((string)$row['storage_path'], $cfg);
    if (!$absPath || !is_file($absPath)) {
        http_response_code(404);
        echo 'File missing';
        exit;
    }

    header('Content-Type: ' . ((string)($row['mime_type'] ?? 'application/octet-stream')));
    header('Content-Length: ' . (string)filesize($absPath));
    header('Content-Disposition: inline; filename="' . rawurlencode((string)($row['original_name'] ?? basename($absPath))) . '"');
    readfile($absPath);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Internal error';
    exit;
}
