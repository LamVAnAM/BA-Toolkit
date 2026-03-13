<?php
require_once __DIR__ . '/../config/bootstrap.php';

requireAdmin();
$adminId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $status = strtolower(trim((string)($_GET['status'] ?? 'all')));
        $where = '';
        $params = [];

        if ($status === 'pending') {
            $where = 'WHERE COALESCE(is_approved, 0) = 0';
        } elseif ($status === 'approved') {
            $where = 'WHERE COALESCE(is_approved, 0) = 1';
        }

        $stmt = $pdo->prepare("
            SELECT id, username, full_name, email, role, COALESCE(is_approved, 0) AS is_approved, approved_at, created_at
            FROM users
            {$where}
            ORDER BY created_at DESC
        ");
        $stmt->execute($params);
        jsonResponse(['users' => $stmt->fetchAll()]);
    }

    if ($method === 'POST') {
        verifyCsrfToken();
        $input = readJsonInput();
        $action = strtolower(trim((string)($input['action'] ?? '')));
        $targetId = (int)($input['user_id'] ?? 0);

        if ($targetId <= 0) {
            jsonError('user_id is required', 400);
        }
        if ($targetId === (int)$adminId && in_array($action, ['revoke', 'set_role'], true)) {
            jsonError('You cannot change your own admin access from this action.', 400);
        }

        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE users SET is_approved = 1, approved_at = ?, approved_by = ? WHERE id = ?");
            $stmt->execute([date('Y-m-d H:i:s'), $adminId, $targetId]);
            jsonResponse(['success' => true]);
        }

        if ($action === 'revoke') {
            $stmt = $pdo->prepare("UPDATE users SET is_approved = 0, approved_at = NULL, approved_by = NULL WHERE id = ?");
            $stmt->execute([$targetId]);
            jsonResponse(['success' => true]);
        }

        if ($action === 'set_role') {
            $role = strtolower(trim((string)($input['role'] ?? 'user')));
            if (!in_array($role, ['admin', 'user'], true)) {
                jsonError('Invalid role', 400);
            }
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$role, $targetId]);
            jsonResponse(['success' => true]);
        }

        jsonError('Invalid action', 400);
    }

    jsonError('Method Not Allowed', 405);
} catch (Throwable $e) {
    appLog('admin_users', 'Admin users API failed', ['error' => $e->getMessage(), 'admin_id' => $adminId ?? null]);
    jsonError($e->getMessage(), 500);
}
