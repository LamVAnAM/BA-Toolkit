<?php
// api/ai_tools.php
require_once __DIR__ . '/../config/bootstrap.php';
requireAuth();

$action = $_GET['action'] ?? '';
$userId = getCurrentUserId();

/**
 * Local helper for logging connection tests and tool actions
 */
function logAiTelemetry(PDO $pdo, int $userId, string $provider, string $model, string $action, int $latency, string $status, ?string $error = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO ai_runs (user_id, provider, model, action_name, latency_ms, status, error_message) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $provider,
            $model,
            $action,
            $latency,
            $status,
            $error
        ]);
    } catch (Throwable $e) {
        appLog('ai_tools', 'Telemetry log failed', ['error' => $e->getMessage()]);
    }
}

try {
    if ($action === 'telemetry') {
        $stmt = $pdo->prepare("SELECT provider, model, action_name, latency_ms, status, created_at FROM ai_runs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$userId]);
        $runs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['runs' => $runs]);
    } elseif ($action === 'test') {
        $data = readJsonInput();
        if (!$data) jsonError('Invalid input');

        // Extract credentials from input for testing (if provided) or fallback to settings
        $providerType = $data['ai_provider'] ?? 'groq';
        $endpoint = $data['ai_endpoint'] ?? '';
        $apiKey = $data['ai_api_key'] ?? '';
        $model = $data['ai_model'] ?? '';

        // If key is masked or empty, try to get from DB
        if (!$apiKey || strpos($apiKey, '***') !== false) {
            $settings = loadSettingsMap($pdo, $userId);
            $apiKey = $settings['ai_api_key'] ?? $settings['groq_api_key'] ?? '';
        }
        if (!$endpoint) {
            $settings = loadSettingsMap($pdo, $userId);
            $endpoint = $settings['ai_endpoint'] ?? $settings['groq_endpoint'] ?? '';
        }

        require_once __DIR__ . '/providers/AIProviderFactory.php';
        
        // Manual instantiation for testing with specific params
        $factory = new AIProviderFactory();
        $provider = $factory->createSpecific($providerType, $endpoint, $apiKey, $model);
        
        $start = microtime(true);
        try {
            $result = $provider->chat([
                ['role' => 'user', 'content' => 'Hello. Reply with strictly "OK".']
            ], [
                'timeout' => 15,
                'model' => $model
            ]);

            $latency = round((microtime(true) - $start) * 1000);
            
            // Log this test run
            logAiTelemetry($pdo, $userId, $providerType, $model, 'test_connection', $latency, 'success');
            
            jsonResponse([
                'success' => true,
                'model' => $result['model'],
                'latency_ms' => $latency,
                'content' => $result['content']
            ]);
        } catch (Throwable $e) {
            $latency = round((microtime(true) - $start) * 1000);
            logAiTelemetry($pdo, $userId, $providerType, $model, 'test_connection', $latency, 'error', $e->getMessage());
            jsonError($e->getMessage());
        }
    } elseif ($action === 'list_models') {
        $providerType = $_GET['provider'] ?? '';
        $endpoint = trim($_GET['endpoint'] ?? '');

        if (!$endpoint || !$providerType) {
            jsonError('Endpoint and provider are required');
        }

        $finalUrl = rtrim($endpoint, '/');
        if (!preg_match('~^https?://~i', $finalUrl)) {
            $finalUrl = 'http://' . $finalUrl;
        }
        
        // Handle different provider endpoint structures
        if ($providerType === 'ollama') {
            // Strip common suffixes to get base URL for tagging
            $strippedUrl = str_ireplace(['/api/chat', '/api/tags', '/v1/chat/completions', '/v1'], '', $finalUrl);
            $strippedUrl = rtrim($strippedUrl, '/');

            // Detect if the original input intended OpenAI compatibility
            if (strpos($endpoint, '/v1') !== false) {
                $finalUrl = $strippedUrl . '/v1/models';
            } else {
                $finalUrl = $strippedUrl . '/api/tags';
            }
        } elseif ($providerType === 'lmstudio') {
            $finalUrl = rtrim($finalUrl, '/v1') . '/v1/models';
        } else {
            jsonError('Listing models only supported for local providers (Ollama/LM Studio)');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $finalUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            jsonError("Connectivity error: $curlErr. (Is the Ollama server reachable at $finalUrl?)");
        }

        if ($httpCode !== 200) {
            jsonError("Ollama returned HTTP $httpCode for $finalUrl. Response: " . substr((string)$response, 0, 200));
        }

        $decoded = json_decode($response, true);
        $models = [];

        if (isset($decoded['models'])) {
            foreach ($decoded['models'] as $m) $models[] = $m['name'];
        } elseif (isset($decoded['data']) && is_array($decoded['data'])) {
            foreach ($decoded['data'] as $m) $models[] = $m['id'];
        }

        // If native Ollama returned nothing, try OpenAI-compatible fallback
        if ($providerType === 'ollama' && empty($models)) {
            $fallbackUrl = $strippedUrl . (strpos($finalUrl, '/api/tags') !== false ? '/v1/models' : '/api/tags');
            
            $ch2 = curl_init($fallbackUrl);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0
            ]);
            $fallbackRes = curl_exec($ch2);
            $dec2 = json_decode((string)$fallbackRes, true);
            curl_close($ch2);

            if (isset($dec2['models'])) {
                foreach ($dec2['models'] as $m) $models[] = $m['name'];
            } elseif (isset($dec2['data']) && is_array($dec2['data'])) {
                foreach ($dec2['data'] as $m) $models[] = $m['id'];
            }
            
            // If fallback worked, update URL for debugging info
            if (!empty($models)) {
                $finalUrl .= " (Fallback to $fallbackUrl)";
            }
        }

        jsonResponse([
            'success' => true,
            'models' => array_values(array_unique($models)),
            'debug_url' => $finalUrl
        ]);
    } else {
        jsonError('Unknown action');
    }
} catch (Throwable $e) {
    appLog('ai_tools', 'AI Tools API failed', ['error' => $e->getMessage()]);
    jsonError($e->getMessage());
}
