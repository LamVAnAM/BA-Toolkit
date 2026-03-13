<?php
// api/departments.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_helper.php';
require_once __DIR__ . '/../config/storage_helper.php';

requireAuth();
$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // List user's departments
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE user_id = ? ORDER BY name ASC");
    $stmt->execute([$userId]);
    jsonResponse($stmt->fetchAll());
} elseif ($method === 'POST') {
    verifyCsrfToken();
    // Create new department for current user
    $data = readJsonInput();
    if (!isset($data['name'])) {
        jsonError('Department name is required', 400);
    }

    $stmt = $pdo->prepare("INSERT INTO departments (user_id, name, sponsor) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $data['name'], $data['sponsor'] ?? '']);
    
    jsonResponse(['id' => $pdo->lastInsertId(), 'status' => 'success']);
} elseif ($method === 'DELETE') {
    verifyCsrfToken();
    // Delete user's department
    $id = $_GET['id'] ?? null;
    if (!$id) {
        jsonError('ID is required', 400);
    }

    try {
        $pdo->beginTransaction();

        // Verify ownership
        $stmt = $pdo->prepare("SELECT id FROM departments WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetch()) {
            throw new Exception('Department not found or access denied');
        }

        // Explicit cascade cleanup
        $filesStmt = $pdo->prepare("SELECT storage_disk, storage_path FROM section_files WHERE department_id = ? AND user_id = ?");
        $filesStmt->execute([$id, $userId]);
        $files = $filesStmt->fetchAll();
        foreach ($files as $fileRow) {
            if (($fileRow['storage_disk'] ?? 'local') === 'local' && !empty($fileRow['storage_path'])) {
                $absPath = resolveStoredFileAbsolutePath((string)$fileRow['storage_path'], loadSettingsMap($pdo, $userId));
                if ($absPath && is_file($absPath)) {
                    @unlink($absPath);
                }
            }
        }

        foreach ([
            'surveys',
            'department_modules',
            'department_kpis',
            'department_processes',
            'department_entities',
            'department_integrations',
            'department_backlog',
            'section_files',
            'ai_jobs',
            'report_versions'
        ] as $table) {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE department_id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
        }

        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonError($e->getMessage(), 500);
    }
    
    jsonResponse(['status' => 'success']);
} elseif ($method === 'PUT') {
    verifyCsrfToken();
    // Update user's department
    $data = readJsonInput();
    $id = $data['id'] ?? null;
    
    if (!$id || !isset($data['name'])) {
        jsonError('ID and Name are required', 400);
    }

    $stmt = $pdo->prepare("UPDATE departments SET name = ?, sponsor = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$data['name'], $data['sponsor'] ?? '', $id, $userId]);
    
    if ($stmt->rowCount() === 0) {
        jsonError('Department not found or access denied', 404);
    }

    jsonResponse(['status' => 'success']);
}

jsonError('Method Not Allowed', 405);
?>
