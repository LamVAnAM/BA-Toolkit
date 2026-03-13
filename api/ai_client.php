<?php
// api/ai_client.php

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/providers/AIProviderFactory.php';

function callAiChat(PDO $pdo, array $settings, array $messages, array $options = []): array
{
    try {
        $factory = new AIProviderFactory();
        $providerInstance = $factory::create($settings);
        
        $start = microtime(true);
        $result = $providerInstance->chat($messages, $options);
        $latencyMs = $result['latency_ms'] ?? (int)round((microtime(true) - $start) * 1000);
        
        $aiText = $result['content'] ?? '';
        $status = 'success';
        $errorMessage = null;

        // telemetry
        logAiTelemetry($pdo, $settings, $messages, $aiText, $latencyMs, $status, $errorMessage);

        return [
            'content' => (string)$aiText,
            'raw' => $result['raw'] ?? [],
            'model' => ($settings['ai_provider'] ?? 'groq') . ':' . ($options['model'] ?? 'default'),
            'latency_ms' => $latencyMs
        ];

    } catch (Throwable $e) {
        $latencyMs = isset($start) ? (int)round((microtime(true) - $start) * 1000) : 0;
        logAiTelemetry($pdo, $settings, $messages, '', $latencyMs, 'failed', $e->getMessage());
        throw $e;
    }
}

function logAiTelemetry(PDO $pdo, array $settings, array $messages, string $aiText, int $latencyMs, string $status, ?string $errorMessage)
{
    try {
        $userId = getCurrentUserId();
        $provider = $settings['ai_provider'] ?? 'groq';
        $endpoint = $settings['ai_endpoint'] ?? $settings['groq_endpoint'] ?? 'unknown';
        
        $stmt = $pdo->prepare("INSERT INTO ai_runs (user_id, endpoint, model, request_chars, response_chars, latency_ms, status, error_message, provider, action_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'chat_completion')");
        $stmt->execute([
            $userId,
            parse_url((string)$endpoint, PHP_URL_HOST) ?: $endpoint,
            $settings['ai_model'] ?? 'unknown',
            strlen(json_encode($messages, JSON_UNESCAPED_UNICODE)),
            strlen($aiText),
            $latencyMs,
            $status,
            $errorMessage,
            $provider
        ]);
    } catch (Throwable $t) {
        appLog('ai_runs', 'Unable to write ai_runs telemetry', ['error' => $t->getMessage()]);
    }
}
