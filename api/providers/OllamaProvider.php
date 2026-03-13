<?php
// api/providers/OllamaProvider.php
require_once __DIR__ . '/AIProvider.php';

class OllamaProvider implements AIProvider {
    protected $endpoint;
    protected $timeout;
    protected $sslVerify;
    protected $model;

    public function __construct(string $endpoint = 'http://127.0.0.1:11434/api/chat', int $timeout = 90, bool $sslVerify = true, string $model = 'llama3') {
        $this->endpoint = $endpoint;
        $this->timeout = $timeout;
        $this->sslVerify = $sslVerify;
        $this->model = $model;
    }

    public function chat(array $messages, array $options = []): array {
        $isV1 = stripos($this->endpoint, '/v1/chat/completions') !== false;

        if ($isV1) {
            $payload = [
                'model' => $options['model'] ?? $this->model,
                'messages' => $messages,
                'temperature' => (float)($options['temperature'] ?? 0.2),
                'stream' => false
            ];
        } else {
            $payload = [
                'model' => $options['model'] ?? $this->model,
                'messages' => $messages,
                'stream' => false,
                'options' => [
                    'temperature' => (float)($options['temperature'] ?? 0.2)
                ]
            ];
        }

        $headers = ['Content-Type: application/json'];
        $start = microtime(true);
        $ch = curl_init($this->endpoint);
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
            throw new Exception("Ollama HTTP $httpCode: " . ($decoded['error'] ?? 'Unknown error'));
        }

        if ($isV1) {
            $content = (string)($decoded['choices'][0]['message']['content'] ?? '');
        } else {
            $content = (string)($decoded['message']['content'] ?? '');
        }

        return [
            'content' => $content,
            'raw' => $decoded,
            'latency_ms' => $latencyMs
        ];
    }
}
