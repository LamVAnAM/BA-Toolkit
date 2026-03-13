<?php
// api/providers/GeminiProvider.php
require_once __DIR__ . '/AIProvider.php';

class GeminiProvider implements AIProvider {
    protected $apiKey;
    protected $model;
    protected $timeout;
    protected $sslVerify;

    public function __construct(string $apiKey, string $model = 'gemini-1.5-flash', int $timeout = 90, bool $sslVerify = true) {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->timeout = $timeout;
        $this->sslVerify = $sslVerify;
    }

    public function chat(array $messages, array $options = []): array {
        $model = $options['model'] ?? $this->model;
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->apiKey;

        // Convert OpenAI-style messages to Gemini-style contents
        $contents = [];
        foreach ($messages as $m) {
            $role = ($m['role'] === 'assistant') ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $m['content']]]
            ];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => (float)($options['temperature'] ?? 0.2),
                'maxOutputTokens' => (int)($options['max_tokens'] ?? 1800)
            ]
        ];

        $headers = ['Content-Type: application/json'];
        $start = microtime(true);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $latencyMs = (int)round((microtime(true) - $start) * 1000);
        $decoded = json_decode((string)$rawResponse, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = $decoded['error']['message'] ?? "Gemini HTTP $httpCode";
            throw new Exception($msg);
        }

        $content = (string)($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');

        return [
            'content' => $content,
            'raw' => $decoded,
            'latency_ms' => $latencyMs
        ];
    }
}
