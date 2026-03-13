<?php
// api/providers/OpenAICompatibleProvider.php
require_once __DIR__ . '/AIProvider.php';

abstract class OpenAICompatibleProvider implements AIProvider {
    protected $endpoint;
    protected $apiKey;
    protected $timeout;
    protected $sslVerify;

    public function __construct(string $endpoint, string $apiKey, int $timeout = 90, bool $sslVerify = true) {
        $this->endpoint = $endpoint;
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
        $this->sslVerify = $sslVerify;
    }

    public function chat(array $messages, array $options = []): array {
        $payload = [
            'model' => $options['model'] ?? '',
            'messages' => $messages,
            'temperature' => (float)($options['temperature'] ?? 0.2),
            'max_tokens' => (int)($options['max_tokens'] ?? 1800)
        ];
        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];

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
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        $latencyMs = (int)round((microtime(true) - $start) * 1000);
        $decoded = json_decode((string)$rawResponse, true);

        if ($httpCode < 200 || $httpCode >= 300 || $curlError) {
            $msg = $curlError ?: ($decoded['error']['message'] ?? "HTTP $httpCode");
            throw new Exception($msg);
        }

        return [
            'content' => (string)($decoded['choices'][0]['message']['content'] ?? ''),
            'raw' => $decoded,
            'latency_ms' => $latencyMs
        ];
    }
}
