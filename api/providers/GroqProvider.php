<?php
// api/providers/GroqProvider.php
require_once __DIR__ . '/OpenAICompatibleProvider.php';

class GroqProvider extends OpenAICompatibleProvider {
    public function __construct(string $apiKey, string $endpoint = 'https://api.groq.com/openai/v1/chat/completions', int $timeout = 90, bool $sslVerify = true) {
        parent::__construct($endpoint, $apiKey, $timeout, $sslVerify);
    }
}
