<?php
require_once __DIR__ . '/../config/bootstrap.php';

requireAuth();
$userId = (int)getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $stmt = $pdo->prepare("
            SELECT id, username, full_name, email, role, oauth_provider, created_at
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            jsonError('User not found.', 404);
        }
        jsonResponse(['user' => $user]);
    }

    if ($method === 'POST') {
        verifyCsrfToken();
        $input = readJsonInput();
        $action = strtolower(trim((string)($input['action'] ?? '')));

        if ($action === 'update_profile') {
            $fullName = trim((string)($input['full_name'] ?? ''));
            $email = strtolower(trim((string)($input['email'] ?? '')));

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonError('Valid email is required.', 400);
            }

            $dup = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
            $dup->execute([$email, $userId]);
            if ($dup->fetch()) {
                jsonError('Email already exists.', 400);
            }

            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
            $stmt->execute([$fullName, $email, $userId]);
            $_SESSION['full_name'] = $fullName;
            jsonResponse(['success' => true, 'message' => 'Profile updated successfully.']);
        }

        if ($action === 'change_password') {
            $currentPassword = (string)($input['current_password'] ?? '');
            $newPassword = (string)($input['new_password'] ?? '');
            $confirmPassword = (string)($input['confirm_password'] ?? '');

            if ($newPassword === '' || strlen($newPassword) < 8) {
                jsonError('New password must be at least 8 characters.', 400);
            }
            if ($newPassword !== $confirmPassword) {
                jsonError('Password confirmation does not match.', 400);
            }

            $stmt = $pdo->prepare("SELECT password_hash, oauth_provider FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if (!$user) {
                jsonError('User not found.', 404);
            }

            if (($user['oauth_provider'] ?? '') === 'google') {
                jsonError('Password cannot be changed for Google sign-in accounts.', 400);
            }
            if ($currentPassword === '' || !password_verify($currentPassword, (string)$user['password_hash'])) {
                jsonError('Current password is incorrect.', 400);
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $upd->execute([$hash, $userId]);
            jsonResponse(['success' => true, 'message' => 'Password changed successfully.']);
        }

        jsonError('Invalid action.', 400);
    }

    jsonError('Method Not Allowed', 405);
} catch (Throwable $e) {
    appLog('profile', 'Profile API failed', ['error' => $e->getMessage(), 'user_id' => $userId]);
    jsonError($e->getMessage(), 500);
}
