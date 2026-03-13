<?php
require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed', 405);
}

verifyCsrfToken();

function generateUniqueUsername(PDO $pdo, string $email): string
{
    $base = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9_]/', '_', strstr($email, '@', true) ?: 'google_user')));
    $base = trim($base, '_');
    if ($base === '') {
        $base = 'google_user';
    }

    $candidate = $base;
    $i = 1;
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$candidate]);
        if (!$stmt->fetch()) {
            return $candidate;
        }
        $candidate = $base . '_' . $i;
        $i++;
    }
}

try {
    $payload = readJsonInput();
    $idToken = trim((string)($payload['id_token'] ?? ''));
    if ($idToken === '') {
        jsonError('id_token is required', 400);
    }

    $settings = loadSettingsMap($pdo, null);
    $googleClientId = trim((string)($settings['google_client_id'] ?? ''));
    if ($googleClientId === '') {
        jsonError('GOOGLE_CLIENT_ID is not configured on server.', 500);
    }

    $verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    $responseRaw = null;
    $sslOptions = buildAiSslOptions($settings);

    if (function_exists('curl_init')) {
        $ch = curl_init($verifyUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => (bool)$sslOptions['verify_peer'],
            CURLOPT_SSL_VERIFYHOST => !empty($sslOptions['verify_host']) ? 2 : 0
        ]);
        $responseRaw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);
        if ($curlErr) {
            throw new Exception('Google verify failed: ' . $curlErr);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception('Google token verification failed.');
        }
    } else {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => (bool)$sslOptions['verify_peer'],
                'verify_peer_name' => (bool)$sslOptions['verify_host'],
                'allow_self_signed' => !((bool)$sslOptions['verify_peer']),
            ]
        ]);
        $responseRaw = @file_get_contents($verifyUrl, false, $context);
        if ($responseRaw === false) {
            throw new Exception('Google token verification failed.');
        }
    }

    $googleData = json_decode((string)$responseRaw, true);
    if (!is_array($googleData)) {
        throw new Exception('Invalid Google verification response.');
    }

    $aud = (string)($googleData['aud'] ?? '');
    $sub = (string)($googleData['sub'] ?? '');
    $email = strtolower(trim((string)($googleData['email'] ?? '')));
    $name = trim((string)($googleData['name'] ?? ''));
    $emailVerified = (string)($googleData['email_verified'] ?? 'false');

    if ($aud !== $googleClientId) {
        jsonError('Invalid Google client audience.', 401);
    }
    if ($sub === '' || $email === '' || $emailVerified !== 'true') {
        jsonError('Google account is not verified.', 401);
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE oauth_provider = 'google' AND oauth_sub = ? LIMIT 1");
    $stmt->execute([$sub]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    }

    if ($user) {
        $update = $pdo->prepare("UPDATE users SET email = ?, full_name = COALESCE(NULLIF(?, ''), full_name), oauth_provider = 'google', oauth_sub = ?, is_approved = 1, approved_at = COALESCE(approved_at, ?), email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?");
        $update->execute([$email, $name, $sub, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), (int)$user['id']]);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([(int)$user['id']]);
        $user = $stmt->fetch();
    } else {
        $username = generateUniqueUsername($pdo, $email);
        $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $role = 'user';
        $insert = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, is_approved, approved_at, email, email_verified_at, oauth_provider, oauth_sub) VALUES (?, ?, ?, ?, 1, ?, ?, ?, 'google', ?)");
        $insert->execute([
            $username,
            $passwordHash,
            $name !== '' ? $name : $username,
            $role,
            date('Y-m-d H:i:s'),
            $email,
            date('Y-m-d H:i:s'),
            $sub
        ]);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([(int)$pdo->lastInsertId()]);
        $user = $stmt->fetch();
    }

    login($user);
    jsonResponse([
        'success' => true,
        'message' => 'Google login successful',
        'user' => [
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ]
    ]);
} catch (Throwable $e) {
    appLog('auth_google', 'Google auth failed', ['error' => $e->getMessage()]);
    jsonError($e->getMessage(), 500);
}
