<?php
// api/providers/AIProviderFactory.php

require_once __DIR__ . '/GroqProvider.php';
require_once __DIR__ . '/OllamaProvider.php';
require_once __DIR__ . '/GeminiProvider.php';
require_once __DIR__ . '/OpenAICompatibleProvider.php';

class OpenAIProvider extends OpenAICompatibleProvider {}
class LMStudioProvider extends OpenAICompatibleProvider {}

class AIProviderFactory {
    private static function normalizeOpenAiStyleEndpoint(string $endpoint, string $fallback): string
    {
        $ep = trim($endpoint);
        if ($ep === '') {
            return $fallback;
        }
        if (!preg_match('~^https?://~i', $ep)) {
            $ep = 'http://' . $ep;
        }
        $ep = rtrim($ep, '/');
        if (!preg_match('~/chat/completions$~i', $ep)) {
            $ep .= '/chat/completions';
        }
        return $ep;
    }

    private static function normalizeOllamaEndpoint(string $endpoint): string
    {
        $ep = trim($endpoint);
        if ($ep === '') {
            return 'http://127.0.0.1:11434/api/chat';
        }
        if (!preg_match('~^https?://~i', $ep)) {
            $ep = 'http://' . $ep;
        }
        $ep = rtrim($ep, '/');
        if (preg_match('~/api/chat$~i', $ep) || preg_match('~/v1/chat/completions$~i', $ep)) {
            return $ep;
        }
        if (strpos($ep, ':11434') !== false) {
            return $ep . '/api/chat';
        }
        return $ep;
    }

    /**
     * Create a provider from a settings array
     */
    public static function create(array $settings): AIProvider {
        $provider = strtolower(trim((string)($settings['ai_provider'] ?? 'groq')));
        $timeout = max(15, (int)($settings['ai_timeout_sec'] ?? 90));
        $sslVerify = (bool)($settings['ai_ssl_verify'] ?? false);
        
        $endpoint = (string)($settings['ai_endpoint'] ?? '');
        $apiKey = (string)($settings['ai_api_key'] ?? '');
        $model = (string)($settings['ai_model'] ?? '');

        switch ($provider) {
            case 'openai':
                return new OpenAIProvider(
                    self::normalizeOpenAiStyleEndpoint($endpoint, 'https://api.openai.com/v1/chat/completions'),
                    $apiKey,
                    $timeout,
                    $sslVerify
                );
            case 'gemini':
                return new GeminiProvider(
                    $apiKey,
                    $model ?: 'gemini-1.5-flash',
                    $timeout,
                    $sslVerify
                );
            case 'ollama':
                $finalEndpoint = self::normalizeOllamaEndpoint($endpoint);
                return new OllamaProvider($finalEndpoint, $timeout, $sslVerify, $model);
            case 'lmstudio':
                return new LMStudioProvider(
                    self::normalizeOpenAiStyleEndpoint($endpoint, 'http://localhost:1234/v1/chat/completions'),
                    'not-needed',
                    $timeout,
                    false
                );
            case 'groq':
            default:
                // Fallback to old groq keys if new ones aren't set
                $groqKey = $apiKey ?: (string)($settings['groq_api_key'] ?? '');
                $groqEndpoint = self::normalizeOpenAiStyleEndpoint(
                    $endpoint ?: (string)($settings['groq_endpoint'] ?? ''),
                    'https://api.groq.com/openai/v1/chat/completions'
                );
                
                return new GroqProvider(
                    $groqKey,
                    $groqEndpoint,
                    $timeout,
                    $sslVerify
                );
        }
    }

    /**
     * Create a specific provider instance for ad-hoc testing or operations
     */
    public static function createSpecific(string $provider, string $endpoint, string $apiKey, string $model = '', int $timeout = 90, bool $sslVerify = true): AIProvider {
        return self::create([
            'ai_provider' => $provider,
            'ai_endpoint' => $endpoint,
            'ai_api_key' => $apiKey,
            'ai_model' => $model,
            'ai_timeout_sec' => $timeout,
            'ai_ssl_verify' => $sslVerify
        ]);
    }
}
