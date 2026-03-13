<?php
// api/providers/AIProvider.php

interface AIProvider {
    /**
     * @param array $messages [{role: string, content: string}]
     * @param array $options [model, temperature, max_tokens, response_format]
     * @return array [content: string, raw: mixed, latency_ms: int]
     */
    public function chat(array $messages, array $options = []): array;
}
