<?php
// config/auth_helper.php

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function getCsrfToken(): string
{
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function verifyCsrfToken(): void
{
    startSession();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if ($token === '' && strpos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
        $raw = function_exists('getRawRequestBody') ? getRawRequestBody() : (string)file_get_contents('php://input');
        if ($raw !== '' && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $token = (string)($decoded['csrf_token'] ?? '');
            }
        }
    }

    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || $token === '' || !hash_equals($sessionToken, $token)) {
        jsonError('Invalid CSRF token', 419);
    }
}

function login(array $user): void
{
    startSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function logout(): void
{
    startSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function isAuthenticated(): bool
{
    startSession();
    return isset($_SESSION['user_id']);
}

function getCurrentUserId(): ?int
{
    startSession();
    return $_SESSION['user_id'] ?? null;
}

function requireAuth(): void
{
    if (!isAuthenticated()) {
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            jsonError('Unauthorized', 401);
        } else {
            header('Location: ' . (function_exists('appUrl') ? appUrl('index.php') : 'index.php'));
            exit;
        }
    }
}

function requireAdmin(): void
{
    requireAuth();
    startSession();
    if (($_SESSION['role'] ?? 'user') !== 'admin') {
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            jsonError('Forbidden', 403);
        } else {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }
}
