<?php
require_once __DIR__ . '/../config/bootstrap.php';

$action = $_GET['action'] ?? '';

function normalizeEmail(string $email): string
{
    return strtolower(trim($email));
}

function createTokenPair(): array
{
    $plain = bin2hex(random_bytes(32));
    return [$plain, hash('sha256', $plain)];
}

function persistToken(PDO $pdo, string $table, int $userId, string $email, string $tokenHash, string $expiresAt): void
{
    $pdo->prepare("UPDATE {$table} SET used_at = COALESCE(used_at, CURRENT_TIMESTAMP) WHERE user_id = ? AND used_at IS NULL")
        ->execute([$userId]);

    $stmt = $pdo->prepare("INSERT INTO {$table} (user_id, email, token_hash, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $email, $tokenHash, $expiresAt]);
}

function buildVerifyEmailBody(string $name, string $link, string $code): array
{
    $displayName = $name !== '' ? htmlspecialchars($name, ENT_QUOTES, 'UTF-8') : 'bạn';
    $html = "<p>Xin chào {$displayName},</p>"
        . "<p>Cảm ơn bạn đã đăng ký tài khoản BA Toolkit.</p>"
        . "<p>Mã xác nhận của bạn là: <strong>{$code}</strong></p>"
        . "<p>Hoặc nhấn vào liên kết sau để kích hoạt tài khoản:</p>"
        . "<p><a href=\"{$link}\">Xác nhận email</a></p>";
    $text = "Xin chào {$name},\nMã xác nhận của bạn là: {$code}\nXác nhận email tại: {$link}";
    return [$html, $text];
}

function buildResetPasswordBody(string $name, string $link, string $code): array
{
    $displayName = $name !== '' ? htmlspecialchars($name, ENT_QUOTES, 'UTF-8') : 'bạn';
    $html = "<p>Xin chào {$displayName},</p>"
        . "<p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho BA Toolkit.</p>"
        . "<p>Mã đặt lại mật khẩu của bạn là: <strong>{$code}</strong></p>"
        . "<p>Hoặc nhấn vào liên kết sau để đặt lại mật khẩu:</p>"
        . "<p><a href=\"{$link}\">Đặt lại mật khẩu</a></p>";
    $text = "Xin chào {$name},\nMã đặt lại mật khẩu: {$code}\nĐặt lại mật khẩu tại: {$link}";
    return [$html, $text];
}

function issueVerificationEmail(PDO $pdo, array $user): void
{
    [$plain, $hash] = createTokenPair();
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);
    persistToken($pdo, 'email_verification_tokens', (int)$user['id'], (string)$user['email'], $hash, $expiresAt);

    $code = strtoupper(substr($plain, 0, 6));
    $link = appBaseUrl() . '/index.php?auth=verify&token=' . urlencode($plain);
    [$html, $text] = buildVerifyEmailBody((string)($user['full_name'] ?? ''), $link, $code);
    $mail = sendAppMail($pdo, (string)$user['email'], (string)($user['full_name'] ?? $user['username']), 'Xác nhận email BA Toolkit', $html, $text);
    if (!$mail['success']) {
        throw new RuntimeException($mail['error'] ?? 'Cannot send verification email.');
    }
}

function issuePasswordResetEmail(PDO $pdo, array $user): void
{
    [$plain, $hash] = createTokenPair();
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);
    persistToken($pdo, 'password_reset_tokens', (int)$user['id'], (string)$user['email'], $hash, $expiresAt);

    $code = strtoupper(substr($plain, 0, 6));
    $link = appBaseUrl() . '/index.php?auth=reset&token=' . urlencode($plain);
    [$html, $text] = buildResetPasswordBody((string)($user['full_name'] ?? ''), $link, $code);
    $mail = sendAppMail($pdo, (string)$user['email'], (string)($user['full_name'] ?? $user['username']), 'Đặt lại mật khẩu BA Toolkit', $html, $text);
    if (!$mail['success']) {
        throw new RuntimeException($mail['error'] ?? 'Cannot send reset email.');
    }
}

function findValidToken(PDO $pdo, string $table, string $plainToken): ?array
{
    $hash = hash('sha256', $plainToken);
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE token_hash = ? AND used_at IS NULL AND expires_at >= CURRENT_TIMESTAMP ORDER BY id DESC LIMIT 1");
    $stmt->execute([$hash]);
    return $stmt->fetch() ?: null;
}

try {
    if ($action === 'register') {
        verifyCsrfToken();
        $input = readJsonInput();
        $username = trim((string)($input['username'] ?? ''));
        $email = normalizeEmail((string)($input['email'] ?? ''));
        $password = trim((string)($input['password'] ?? ''));
        $fullName = trim((string)($input['full_name'] ?? ''));

        if ($username === '' || $email === '' || $password === '') {
            jsonError('Username, email and password are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonError('Invalid email address.');
        }
        if (strlen($password) < 8) {
            jsonError('Password must be at least 8 characters.');
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            jsonError('Username or email already exists.');
        }

        $settings = loadSettingsMap($pdo, null);
        $mailEnabled = trim((string)($settings['smtp_host'] ?? '')) !== '' && trim((string)($settings['smtp_from_email'] ?? '')) !== '';

        $userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        $isFirstUser = $userCount === 0 || $adminCount === 0;
        $role = $isFirstUser ? 'admin' : 'user';
        $isApproved = $isFirstUser ? 1 : 0;
        $emailVerifiedAt = ($isFirstUser || !$mailEnabled) ? date('Y-m-d H:i:s') : null;

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, is_approved, approved_at, email, email_verified_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $username,
            $hash,
            $fullName,
            $role,
            $isApproved,
            $isFirstUser ? date('Y-m-d H:i:s') : null,
            $email,
            $emailVerifiedAt
        ]);

        $userId = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$isFirstUser && $mailEnabled) {
            issueVerificationEmail($pdo, $user);
        }

        jsonResponse([
            'success' => true,
            'message' => $isFirstUser
                ? 'Registration successful. Admin account created.'
                : ($mailEnabled ? 'Registration successful. Please verify your email before login.' : 'Registration successful. Please wait for admin approval.')
        ]);
    } elseif ($action === 'verify_email') {
        verifyCsrfToken();
        $input = readJsonInput();
        $token = trim((string)($input['token'] ?? ($_GET['token'] ?? '')));
        if ($token === '') {
            jsonError('Verification token is required.');
        }

        $tokenRow = findValidToken($pdo, 'email_verification_tokens', $token);
        if (!$tokenRow) {
            jsonError('Verification token is invalid or expired.', 400);
        }

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE email_verification_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([(int)$tokenRow['id']]);
        $pdo->prepare("UPDATE users SET email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP) WHERE id = ?")->execute([(int)$tokenRow['user_id']]);
        $pdo->commit();
        jsonResponse(['success' => true, 'message' => 'Email verified successfully.']);
    } elseif ($action === 'resend_verification') {
        verifyCsrfToken();
        $input = readJsonInput();
        $email = normalizeEmail((string)($input['email'] ?? ''));
        if ($email === '') {
            jsonError('Email is required.');
        }
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            jsonResponse(['success' => true, 'message' => 'If the email exists, a verification email has been sent.']);
        }
        if (!empty($user['email_verified_at'])) {
            jsonError('Email is already verified.');
        }
        issueVerificationEmail($pdo, $user);
        jsonResponse(['success' => true, 'message' => 'Verification email sent.']);
    } elseif ($action === 'login') {
        verifyCsrfToken();
        $input = readJsonInput();
        $identity = trim((string)($input['identity'] ?? ($input['username'] ?? '')));
        $password = trim((string)($input['password'] ?? ''));

        if ($identity === '' || $password === '') {
            jsonError('Username/email and password are required.');
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$identity, normalizeEmail($identity)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            jsonError('Invalid username/email or password.');
        }

        if ((int)($user['is_approved'] ?? 0) !== 1) {
            jsonError('Your account is pending admin approval.', 403);
        }
        if (empty($user['email_verified_at']) && ($user['oauth_provider'] ?? '') !== 'google') {
            jsonError('Please verify your email before login.', 403, ['error_code' => 'EMAIL_NOT_VERIFIED']);
        }

        login($user);
        jsonResponse(['success' => true, 'message' => 'Login successful', 'csrf_token' => getCsrfToken(), 'user' => [
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ]]);
    } elseif ($action === 'forgot_password') {
        verifyCsrfToken();
        $input = readJsonInput();
        $email = normalizeEmail((string)($input['email'] ?? ''));
        if ($email === '') {
            jsonError('Email is required.');
        }
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && ($user['oauth_provider'] ?? '') !== 'google') {
            issuePasswordResetEmail($pdo, $user);
        }
        jsonResponse(['success' => true, 'message' => 'If the account exists, a reset email has been sent.']);
    } elseif ($action === 'reset_password') {
        verifyCsrfToken();
        $input = readJsonInput();
        $token = trim((string)($input['token'] ?? ''));
        $password = trim((string)($input['password'] ?? ''));
        if ($token === '' || $password === '') {
            jsonError('Token and new password are required.');
        }
        if (strlen($password) < 8) {
            jsonError('Password must be at least 8 characters.');
        }

        $tokenRow = findValidToken($pdo, 'password_reset_tokens', $token);
        if (!$tokenRow) {
            jsonError('Reset token is invalid or expired.', 400);
        }

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([(int)$tokenRow['id']]);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
            ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$tokenRow['user_id']]);
        $pdo->commit();
        jsonResponse(['success' => true, 'message' => 'Password reset successful.']);
    } elseif ($action === 'logout') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            jsonError('Method Not Allowed', 405);
        }
        verifyCsrfToken();
        logout();
        jsonResponse(['success' => true]);
    } else {
        jsonError('Invalid action');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    appLog('auth', 'Auth API failed', ['action' => $action, 'error' => $e->getMessage()]);
    jsonError($e->getMessage(), 500);
}
