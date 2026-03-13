<?php
// config/helpers.php

if (!function_exists('jsonResponse')) {
    function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('appBasePath')) {
    function appBasePath(): string
    {
        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');
        return $dir === '' ? '' : $dir;
    }
}

if (!function_exists('appUrl')) {
    function appUrl(string $path = ''): string
    {
        $base = appBasePath();
        $path = ltrim($path, '/');
        if ($path === '') {
            return $base !== '' ? $base . '/' : '/';
        }
        return ($base !== '' ? $base . '/' : '/') . $path;
    }
}

if (!function_exists('jsonError')) {
    function jsonError(string $message, int $statusCode = 400, array $extra = []): void
    {
        jsonResponse(array_merge(['error' => $message], $extra), $statusCode);
    }
}

if (!function_exists('getSecretSettingKeys')) {
    function getSecretSettingKeys(): array
    {
        return ['ai_api_key', 'groq_api_key', 'smtp_password'];
    }
}

if (!function_exists('getAppKeyMaterial')) {
    function getAppKeyMaterial(): ?string
    {
        $raw = (string)($_ENV['APP_KEY'] ?? $_SERVER['APP_KEY'] ?? getenv('APP_KEY') ?: '');
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (stripos($raw, 'base64:') === 0) {
            $decoded = base64_decode(substr($raw, 7), true);
            return $decoded === false ? null : $decoded;
        }
        return $raw;
    }
}

if (!function_exists('encryptSecretValue')) {
    function encryptSecretValue(string $plain): string
    {
        $keyMaterial = getAppKeyMaterial();
        if ($keyMaterial === null) {
            throw new RuntimeException('APP_KEY is required to store encrypted secrets.');
        }

        $key = hash('sha256', $keyMaterial, true);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Secret encryption failed.');
        }
        return 'encv1:' . base64_encode($iv . $tag . $cipher);
    }
}

if (!function_exists('decryptSecretValue')) {
    function decryptSecretValue(string $stored): string
    {
        if (strpos($stored, 'encv1:') !== 0) {
            return $stored;
        }

        $keyMaterial = getAppKeyMaterial();
        if ($keyMaterial === null) {
            throw new RuntimeException('APP_KEY is required to read encrypted secrets.');
        }

        $blob = base64_decode(substr($stored, 6), true);
        if ($blob === false || strlen($blob) < 29) {
            throw new RuntimeException('Encrypted secret payload is invalid.');
        }

        $key = hash('sha256', $keyMaterial, true);
        $iv = substr($blob, 0, 12);
        $tag = substr($blob, 12, 16);
        $cipher = substr($blob, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new RuntimeException('Secret decryption failed.');
        }
        return $plain;
    }
}

if (!function_exists('readJsonInput')) {
    function getRawRequestBody(): string
    {
        static $raw = null;
        if ($raw !== null) {
            return $raw;
        }
        $content = file_get_contents('php://input');
        $raw = is_string($content) ? $content : '';
        return $raw;
    }

    function readJsonInput(): array
    {
        $raw = getRawRequestBody();
        if ($raw === '' || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('generateUuidV4')) {
    function generateUuidV4(): string
    {
        if (class_exists(\Ramsey\Uuid\Uuid::class)) {
            return \Ramsey\Uuid\Uuid::uuid4()->toString();
        }

        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('isLocalEnvironment')) {
    function isLocalEnvironment(): bool
    {
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        return in_array($serverName, ['localhost', '127.0.0.1', '::1'], true)
            || in_array($remoteAddr, ['127.0.0.1', '::1'], true);
    }
}

if (!function_exists('buildAiSslOptions')) {
    function buildAiSslOptions(array $settings): array
    {
        $env = strtolower(trim((string)($settings['app_env'] ?? (isLocalEnvironment() ? 'local' : 'production'))));
        $verifyPeer = filter_var($settings['ai_ssl_verify'] ?? ($env === 'local' ? '0' : '1'), FILTER_VALIDATE_BOOLEAN);
        $verifyHost = filter_var($settings['ai_ssl_verify_host'] ?? ($env === 'local' ? '0' : '1'), FILTER_VALIDATE_BOOLEAN);

        return [
            'verify_peer' => $verifyPeer,
            'verify_host' => $verifyHost,
            'env' => $env
        ];
    }
}

if (!function_exists('loadSettingsMap')) {
    function loadSettingsMap(PDO $pdo, ?int $userId = null): array
    {
        $settings = [];

        // 1. Load from Environment (Lowest priority baseline)
        $envMap = [
            'GROQ_ENDPOINT' => 'groq_endpoint',
            'GROQ_API_KEY' => 'groq_api_key',
            'GROQ_MODEL' => 'groq_model',
            'AI_PROVIDER' => 'ai_provider',
            'AI_ENDPOINT' => 'ai_endpoint',
            'AI_MODEL' => 'ai_model',
            'AI_REPORT_MODEL' => 'ai_report_model',
            'AI_TIMEOUT_SEC' => 'ai_timeout_sec',
            'AI_SSL_VERIFY' => 'ai_ssl_verify',
            'AI_SSL_VERIFY_HOST' => 'ai_ssl_verify_host',
            'APP_ENV' => 'app_env',
            'GOOGLE_CLIENT_ID' => 'google_client_id',
            'STORAGE_DRIVER' => 'storage_driver',
            'STORAGE_LOCAL_ROOT' => 'storage_local_root',
            'S3_BUCKET' => 's3_bucket',
            'S3_REGION' => 's3_region',
            'S3_ENDPOINT' => 's3_endpoint',
            'S3_PREFIX' => 's3_prefix',
            'UPLOAD_MAX_MB' => 'upload_max_mb',
            'UPLOAD_MAX_WIDTH' => 'upload_max_width',
            'UPLOAD_MAX_HEIGHT' => 'upload_max_height',
            'UPLOAD_JPEG_QUALITY' => 'upload_jpeg_quality',
            'UPLOAD_REQUIRE_AV' => 'upload_require_av',
            'SMTP_HOST' => 'smtp_host',
            'SMTP_PORT' => 'smtp_port',
            'SMTP_USERNAME' => 'smtp_username',
            'SMTP_PASSWORD' => 'smtp_password',
            'SMTP_ENCRYPTION' => 'smtp_encryption',
            'SMTP_FROM_EMAIL' => 'smtp_from_email',
            'SMTP_FROM_NAME' => 'smtp_from_name'
        ];

        foreach ($envMap as $envKey => $settingKey) {
            $envVal = $_ENV[$envKey] ?? $_SERVER[$envKey] ?? getenv($envKey);
            if ($envVal !== false && $envVal !== null && $envVal !== '') {
                $settings[$settingKey] = (string)$envVal;
            }
        }

        // 2. Load Global Settings (ID = 0), backward compatible with legacy NULL rows
        $globalStmt = $pdo->query("SELECT key_name, value FROM settings WHERE user_id = 0 OR user_id IS NULL");
        $globalSettings = $globalStmt ? $globalStmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];
        foreach ($globalSettings as $k => $v) {
            if (in_array($k, getSecretSettingKeys(), true) && is_string($v) && $v !== '') {
                $globalSettings[$k] = decryptSecretValue($v);
            }
        }
        $settings = array_merge($settings, $globalSettings);

        // 3. Load User-specific Settings (Highest priority)
        if ($userId !== null) {
            $stmt = $pdo->prepare("SELECT key_name, value FROM settings WHERE user_id = ?");
            $stmt->execute([$userId]);
            $userSettings = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];
            foreach ($userSettings as $k => $v) {
                if (in_array($k, getSecretSettingKeys(), true) && is_string($v) && $v !== '') {
                    $userSettings[$k] = decryptSecretValue($v);
                }
            }
            foreach ($userSettings as $k => $v) {
                $settings[$k] = $v;
            }
        }

        return $settings;
    }
}

if (!function_exists('appLog')) {
    function appLog(string $channel, string $message, array $context = []): void
    {
        $logDir = __DIR__ . '/../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        if (class_exists(\Monolog\Logger::class) && class_exists(\Monolog\Handler\StreamHandler::class)) {
            try {
                $logger = new \Monolog\Logger('ba_toolkit');
                $logger->pushHandler(new \Monolog\Handler\StreamHandler($logDir . '/app.log'));
                $logger->info($message, array_merge(['channel' => $channel], $context));
                return;
            } catch (Throwable $ignored) {
                // fallback below
            }
        }

        $payload = [
            'ts' => date('c'),
            'channel' => $channel,
            'message' => $message,
            'context' => $context
        ];

        @file_put_contents(
            $logDir . '/app.log',
            json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND
        );
    }
}

if (!function_exists('loadAiSettingsForUser')) {
    function loadAiSettingsForUser(PDO $pdo, int $userId): array
    {
        $settings = loadSettingsMap($pdo, $userId);
        
        // Ensure standard keys have at least a fallback if DB and Env are empty
        $settings['ai_provider'] = strtolower((string)($settings['ai_provider'] ?? 'groq'));
        $settings['ai_endpoint'] = (string)($settings['ai_endpoint'] ?? '');
        $settings['ai_model'] = (string)($settings['ai_model'] ?? '');
        $settings['ai_timeout_sec'] = (int)($settings['ai_timeout_sec'] ?? 90);

        return $settings;
    }
}
