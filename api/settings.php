<?php
// api/settings.php
require_once __DIR__ . '/../config/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

$allowedSettings = [
    'groq_endpoint',
    'groq_api_key',
    'groq_model',
    'ai_provider',
    'ai_endpoint',
    'ai_api_key',
    'ai_model',
    'ai_report_model',
    'ai_timeout_sec',
    'ai_ssl_verify',
    'ai_ssl_verify_host',
    'app_env',
    'google_client_id',
    'storage_driver',
    'storage_local_root',
    's3_bucket',
    's3_region',
    's3_endpoint',
    's3_prefix',
    'upload_max_mb',
    'upload_max_width',
    'upload_max_height',
    'upload_jpeg_quality',
    'upload_require_av',
    'smtp_host',
    'smtp_port',
    'smtp_username',
    'smtp_password',
    'smtp_encryption',
    'smtp_from_email',
    'smtp_from_name',
    'footer_copyright_text',
    'footer_brand_name',
    'footer_contact_email'
];

requireAuth();
$userId = getCurrentUserId();
$isAdmin = (($_SESSION['role'] ?? 'user') === 'admin');

// Global infrastructure keys (only Admin can view/edit)
$globalKeys = [
    'google_client_id',
    'app_env',
    'storage_driver',
    'storage_local_root',
    's3_bucket',
    's3_region',
    's3_endpoint',
    's3_prefix',
    'upload_max_mb',
    'upload_max_width',
    'upload_max_height',
    'upload_jpeg_quality',
    'upload_require_av',
    'smtp_host',
    'smtp_port',
    'smtp_username',
    'smtp_password',
    'smtp_encryption',
    'smtp_from_email',
    'smtp_from_name',
    'footer_copyright_text',
    'footer_brand_name',
    'footer_contact_email'
];

try {
    if ($method === 'GET') {
        $settings = loadSettingsMap($pdo, $userId);

        // Ensure AI settings always have deterministic defaults for UI binding.
        if (!isset($settings['ai_provider']) || trim((string)$settings['ai_provider']) === '') {
            $settings['ai_provider'] = 'groq';
        }
        if (!isset($settings['ai_endpoint']) || trim((string)$settings['ai_endpoint']) === '') {
            $settings['ai_endpoint'] = (string)($settings['groq_endpoint'] ?? 'https://api.groq.com/openai/v1/chat/completions');
        }
        if (!isset($settings['ai_model']) || trim((string)$settings['ai_model']) === '') {
            $settings['ai_model'] = (string)($settings['groq_model'] ?? 'llama-3.3-70b-versatile');
        }
        if (!isset($settings['storage_local_root']) || trim((string)$settings['storage_local_root']) === '' || trim((string)$settings['storage_local_root']) === 'storage/uploads') {
            $settings['storage_local_root'] = '../private_uploads';
        }

        // Security: Filter out global infrastructure for non-admin users
        if (!$isAdmin) {
            foreach ($globalKeys as $gk) {
                if (isset($settings[$gk])) {
                    unset($settings[$gk]);
                }
            }
        }

        // Do not expose raw API keys back to browser
        $apiKey = (string)($settings['ai_api_key'] ?? '');
        $groqApiKey = (string)($settings['groq_api_key'] ?? '');
        unset($settings['ai_api_key'], $settings['groq_api_key']);
        $settings['ai_api_key_set'] = $apiKey !== '';
        $settings['ai_api_key_masked'] = $apiKey !== '' ? substr($apiKey, 0, 6) . '***' . substr($apiKey, -3) : '';
        $settings['groq_api_key_set'] = $groqApiKey !== '';
        $settings['groq_api_key_masked'] = $groqApiKey !== '' ? substr($groqApiKey, 0, 6) . '***' . substr($groqApiKey, -3) : '';
        
        $smtpPassword = (string)($settings['smtp_password'] ?? '');
        unset($settings['smtp_password']);
        $settings['smtp_password_set'] = $smtpPassword !== '';
        $settings['smtp_password_masked'] = $smtpPassword !== '' ? '********' : '';

        jsonResponse($settings);
    } elseif ($method === 'POST') {
        verifyCsrfToken();
        $data = readJsonInput();
        if (!$data) {
            jsonError('Invalid input data', 400);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (user_id, key_name, value) VALUES (?, ?, ?)");

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowedSettings, true)) {
                continue;
            }
            
            if (in_array($key, $globalKeys, true)) {
                if (!$isAdmin) {
                    continue; // Skip global settings if not admin
                }
                // Save global setting (user_id = 0)
                $storedValue = (string)$value;
                if (in_array($key, getSecretSettingKeys(), true) && $storedValue !== '') {
                    $storedValue = encryptSecretValue($storedValue);
                }
                $stmt->execute([0, (string)$key, $storedValue]);
                continue;
            }
            
            // Save user-specific setting
            $storedValue = (string)$value;
            if (in_array($key, getSecretSettingKeys(), true) && $storedValue !== '') {
                $storedValue = encryptSecretValue($storedValue);
            }
            $stmt->execute([$userId, (string)$key, $storedValue]);
        }

        $pdo->commit();
        jsonResponse(['success' => true]);
    } else {
        jsonError('Method Not Allowed', 405);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    appLog('settings', 'Settings API failed', ['error' => $e->getMessage()]);
    jsonError($e->getMessage(), 500);
}
