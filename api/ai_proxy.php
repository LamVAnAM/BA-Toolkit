<?php
// api/ai_proxy.php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/ai_client.php';

requireAuth();
$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed', 405);
}

try {
    $settings = loadAiSettingsForUser($pdo, $userId);
    $input = readJsonInput();
    $prompt = trim((string)($input['prompt'] ?? ''));
    $content = trim((string)($input['content'] ?? ''));
    $temperature = isset($input['temperature']) ? (float)$input['temperature'] : 0.3;

    if ($prompt === '' || $content === '') {
        jsonError('Missing prompt or content.', 400);
    }

    $result = callAiChat(
        $pdo,
        $settings,
        [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => $content]
        ],
        [
            'temperature' => $temperature,
            'model' => ($settings['ai_provider'] ?? 'groq') === 'ollama'
                ? ($settings['ai_model'] ?? 'llama3.1:8b')
                : ($settings['groq_model'] ?? 'llama-3.3-70b-versatile')
        ]
    );

    jsonResponse([
        'success' => true,
        'content' => $result['content'],
        'meta' => [
            'model' => $result['model'],
            'latency_ms' => $result['latency_ms']
        ]
    ]);
} catch (Throwable $e) {
    appLog('ai_proxy', 'AI proxy failed', ['error' => $e->getMessage()]);
    if (stripos($e->getMessage(), 'Groq API Key is not configured') !== false) {
        jsonError('Bạn chưa cấu hình Groq API key cá nhân. Vào mục AI API Key để nhập key.', 428, [
            'error_code' => 'MISSING_AI_KEY',
            'redirect_view' => 'ai_toolkit'
        ]);
    }
    jsonError($e->getMessage(), 500);
}
