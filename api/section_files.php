<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/storage_helper.php';

requireAuth();
$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $deptId = (int)($_GET['department_id'] ?? 0);
        $sectionId = trim((string)($_GET['section_id'] ?? ''));
        if ($deptId <= 0) {
            jsonError('department_id is required', 400);
        }

        $isAdmin = (($_SESSION['role'] ?? 'user') === 'admin');

        $checkQuery = $isAdmin 
            ? "SELECT id FROM departments WHERE id = ?" 
            : "SELECT id FROM departments WHERE id = ? AND user_id = ?";
        
        $chk = $pdo->prepare($checkQuery);
        $chk->execute($isAdmin ? [$deptId] : [$deptId, $userId]);
        
        if (!$chk->fetch()) {
            jsonError('Department not found or access denied', 404);
        }

        if ($sectionId !== '') {
            $query = $isAdmin 
                ? "SELECT * FROM section_files WHERE department_id = ? AND section_id = ? ORDER BY created_at DESC, id DESC"
                : "SELECT * FROM section_files WHERE user_id = ? AND department_id = ? AND section_id = ? ORDER BY created_at DESC, id DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($isAdmin ? [$deptId, $sectionId] : [$userId, $deptId, $sectionId]);
        } else {
            $query = $isAdmin 
                ? "SELECT * FROM section_files WHERE department_id = ? ORDER BY section_id ASC, created_at DESC, id DESC"
                : "SELECT * FROM section_files WHERE user_id = ? AND department_id = ? ORDER BY section_id ASC, created_at DESC, id DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($isAdmin ? [$deptId] : [$userId, $deptId]);
        }

        $items = $stmt->fetchAll();
        foreach ($items as &$item) {
            $item['public_url'] = 'api/section_file_view.php?id=' . (int)$item['id'];
            $item['view_url'] = $item['public_url'];
        }
        unset($item);
        jsonResponse(['items' => $items]);
    }

    if ($method === 'DELETE') {
        verifyCsrfToken();
        $payload = readJsonInput();
        $id = (int)($payload['id'] ?? 0);
        $deptId = (int)($payload['department_id'] ?? 0);
        if ($id <= 0 || $deptId <= 0) {
            jsonError('id and department_id are required', 400);
        }

        $isAdmin = (($_SESSION['role'] ?? 'user') === 'admin');
        $query = $isAdmin
            ? "SELECT * FROM section_files WHERE id = ? AND department_id = ?"
            : "SELECT * FROM section_files WHERE id = ? AND user_id = ? AND department_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute($isAdmin ? [$id, $deptId] : [$id, $userId, $deptId]);
        $row = $stmt->fetch();
        if (!$row) {
            jsonError('File not found', 404);
        }

        $cfg = getUploadStorageConfig($pdo, $userId);
        if (($row['storage_disk'] ?? 'local') === 'local') {
            $abs = resolveStoredFileAbsolutePath((string)$row['storage_path'], $cfg);
            if ($abs && is_file($abs)) {
                @unlink($abs);
            }
        }

        $del = $pdo->prepare($isAdmin
            ? "DELETE FROM section_files WHERE id = ? AND department_id = ?"
            : "DELETE FROM section_files WHERE id = ? AND user_id = ? AND department_id = ?");
        $del->execute($isAdmin ? [$id, $deptId] : [$id, $userId, $deptId]);
        jsonResponse(['success' => true]);
    }

    jsonError('Method Not Allowed', 405);
} catch (Throwable $e) {
    jsonError($e->getMessage(), 500);
}
