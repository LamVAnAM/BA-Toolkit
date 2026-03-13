<?php
// api/ai_normalize.php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/ai_client.php';
requireAuth();
$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed', 405);
}

function extractJsonPayload(string $text): array
{
    $trimmed = trim($text);
    if ($trimmed === '') {
        return [];
    }

    $decoded = json_decode($trimmed, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    if (preg_match('/```json\s*(\{[\s\S]*\})\s*```/i', $trimmed, $matches)) {
        $decoded = json_decode($matches[1], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    if (preg_match('/(\{[\s\S]*\})/', $trimmed, $matches)) {
        $decoded = json_decode($matches[1], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return [];
}

function buildCatalog(PDO $pdo, int $userId, int $departmentId, array $inputCatalog = []): array
{
    $catalog = [];

    foreach ($inputCatalog as $item) {
        if (!is_array($item)) {
            continue;
        }
        $sectionId = (string)($item['section_id'] ?? '');
        $fieldKey = (string)($item['field_key'] ?? $item['key'] ?? '');
        if ($sectionId === '' || $fieldKey === '') {
            continue;
        }
        $catalogId = 's' . $sectionId . '__' . $fieldKey;
        $catalog[$catalogId] = [
            'catalog_id' => $catalogId,
            'section_id' => $sectionId,
            'section_name' => (string)($item['section_name'] ?? ''),
            'field_key' => $fieldKey,
            'field_label' => (string)($item['field_label'] ?? $item['label'] ?? $fieldKey),
            'current_value' => (string)($item['value'] ?? '')
        ];
    }

    if ($catalog) {
        return array_values($catalog);
    }

    $stmt = $pdo->prepare("SELECT section_id, section_name, field_key, field_label, COALESCE(normalized_value, raw_value, field_value, '') AS current_value FROM surveys WHERE user_id = ? AND department_id = ? ORDER BY section_id ASC, id ASC");
    $stmt->execute([$userId, $departmentId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $sectionId = (string)$row['section_id'];
        $fieldKey = (string)$row['field_key'];
        $catalogId = 's' . $sectionId . '__' . $fieldKey;
        if (isset($catalog[$catalogId])) {
            continue;
        }
        $catalog[$catalogId] = [
            'catalog_id' => $catalogId,
            'section_id' => $sectionId,
            'section_name' => (string)$row['section_name'],
            'field_key' => $fieldKey,
            'field_label' => (string)$row['field_label'],
            'current_value' => (string)$row['current_value']
        ];
    }

    return array_values($catalog);
}

function validateAiNormalizedPayload(array $decoded): void
{
    if (!class_exists(\Opis\JsonSchema\Validator::class)) {
        return;
    }

    $schema = json_decode('{
      "type":"object",
      "additionalProperties":{
        "anyOf":[
          {"type":"string"},
          {"type":"null"}
        ]
      }
    }');

    $validator = new \Opis\JsonSchema\Validator();
    $result = $validator->validate(json_decode(json_encode($decoded)), $schema);
    if (!$result->isValid()) {
        throw new Exception('AI response schema is invalid for normalization payload.');
    }
}

try {
    $payload = readJsonInput();
    $departmentId = (int)($payload['department_id'] ?? 0);
    $scope = strtolower(trim((string)($payload['scope'] ?? 'full')));
    $sectionIdFilter = isset($payload['section_id']) ? (string)$payload['section_id'] : null;
    $fieldKeyFilter = isset($payload['field_key']) ? (string)$payload['field_key'] : null;
    $sourceText = trim((string)($payload['content'] ?? ''));
    $applyImmediately = !empty($payload['apply_immediately']);

    if ($departmentId <= 0) {
        jsonError('department_id is required', 400);
    }

    // Verify dept ownership
    $stmt = $pdo->prepare("SELECT id FROM departments WHERE id = ? AND user_id = ?");
    $stmt->execute([$departmentId, $userId]);
    if (!$stmt->fetch()) {
        jsonError('Department not found or access denied', 403);
    }

    if (!in_array($scope, ['field', 'section', 'full'], true)) {
        jsonError('Invalid scope. Use field|section|full', 400);
    }

    $settings = loadAiSettingsForUser($pdo, $userId);
    $catalog = buildCatalog($pdo, $userId, $departmentId, $payload['field_catalog'] ?? []);
    if (!$catalog) {
        jsonError('No field catalog found. Save survey structure first.', 400);
    }

    if ($scope === 'section') {
        $catalog = array_values(array_filter($catalog, static fn($item) => (string)$item['section_id'] === (string)$sectionIdFilter));
    } elseif ($scope === 'field') {
        $catalog = array_values(array_filter(
            $catalog,
            static fn($item) => (string)$item['section_id'] === (string)$sectionIdFilter && (string)$item['field_key'] === (string)$fieldKeyFilter
        ));
    }

    if (!$catalog) {
        jsonError('No fields matched scope filter.', 400);
    }

    $jobId = generateUuidV4();
    $pdo->prepare("INSERT INTO ai_jobs (id, user_id, department_id, scope, status, field_key, section_id, source_text, source_payload) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?)")
        ->execute([
            $jobId,
            $userId,
            $departmentId,
            $scope,
            $fieldKeyFilter,
            $sectionIdFilter,
            $sourceText,
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        ]);

    $catalogText = [];
    foreach ($catalog as $item) {
        $catalogText[] = "- {$item['catalog_id']} | section={$item['section_id']} | key={$item['field_key']} | label={$item['field_label']} | current={$item['current_value']}";
    }

    $systemPrompt = <<<PROMPT
Bạn là chuyên gia Business Analyst.
Nhiệm vụ: Chuẩn hóa nội dung nhập tự nhiên thành dữ liệu có cấu trúc cho form khảo sát.
Quy tắc bắt buộc:
1) Trả về JSON object thuần, không markdown.
2) Chỉ dùng các key có trong catalog_id.
3) Giá trị là string tiếng Việt chuyên nghiệp, ngắn gọn; nếu không có dữ liệu thì trả null.
4) Không bịa thông tin.
PROMPT;

    $userPrompt = "SCOPE: {$scope}\nDEPARTMENT_ID: {$departmentId}\n\nFIELD CATALOG:\n" . implode("\n", $catalogText);
    if ($sourceText !== '') {
        $userPrompt .= "\n\nNATURAL INPUT:\n{$sourceText}";
    } else {
        $userPrompt .= "\n\nNATURAL INPUT:\n(Hãy chuẩn hóa lại dữ liệu current theo chuẩn BA).";
    }

    $response = callAiChat(
        $pdo,
        $settings,
        [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ],
        [
            'temperature' => 0.2,
            'model' => ($settings['ai_provider'] ?? 'groq') === 'ollama'
                ? ($settings['ai_model'] ?? 'llama3.1:8b')
                : ($settings['groq_model'] ?? 'llama-3.3-70b-versatile'),
            'response_format' => ['type' => 'json_object']
        ]
    );

    $decoded = extractJsonPayload($response['content']);
    if (!$decoded) {
        throw new Exception('AI did not return valid JSON.');
    }
    validateAiNormalizedPayload($decoded);

    $catalogMap = [];
    foreach ($catalog as $item) {
        $catalogMap[$item['catalog_id']] = $item;
    }

    $changes = [];
    $now = date('Y-m-d H:i:s');

    foreach ($decoded as $catalogId => $normalizedValue) {
        if (!isset($catalogMap[$catalogId])) {
            continue;
        }
        if ($normalizedValue === null) {
            continue;
        }
        $newValue = trim((string)$normalizedValue);
        if ($newValue === '') {
            continue;
        }

        $item = $catalogMap[$catalogId];
        $oldValue = (string)$item['current_value'];
        $changes[] = [
            'catalog_id' => $catalogId,
            'section_id' => (string)$item['section_id'],
            'section_name' => (string)$item['section_name'],
            'field_key' => (string)$item['field_key'],
            'field_label' => (string)$item['field_label'],
            'old_value' => $oldValue,
            'new_value' => $newValue
        ];

        if ($applyImmediately) {
            $update = $pdo->prepare("UPDATE surveys SET raw_value = ?, normalized_value = ?, field_value = ?, normalization_state = 'normalized', normalized_at = ? WHERE user_id = ? AND department_id = ? AND section_id = ? AND field_key = ?");
            $update->execute([
                $oldValue,
                $newValue,
                $newValue,
                $now,
                $userId,
                $departmentId,
                $item['section_id'],
                $item['field_key']
            ]);

            if ($update->rowCount() === 0) {
                $insert = $pdo->prepare("INSERT INTO surveys (user_id, department_id, section_id, section_name, field_key, field_label, field_value, raw_value, normalized_value, normalization_state, normalized_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'normalized', ?)");
                $insert->execute([
                    $userId,
                    $departmentId,
                    $item['section_id'],
                    $item['section_name'],
                    $item['field_key'],
                    $item['field_label'],
                    $newValue,
                    $oldValue,
                    $newValue,
                    $now
                ]);
            }
        }
    }

    $pdo->prepare("UPDATE ai_jobs SET status = 'completed', normalized_payload = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?")
        ->execute([json_encode(['changes' => $changes], JSON_UNESCAPED_UNICODE), $jobId, $userId]);

    jsonResponse([
        'success' => true,
        'job_id' => $jobId,
        'scope' => $scope,
        'changes' => $changes,
        'applied' => $applyImmediately,
        'meta' => [
            'model' => $response['model'],
            'latency_ms' => $response['latency_ms']
        ]
    ]);
} catch (Throwable $e) {
    appLog('ai_normalize', 'AI normalize failed', ['error' => $e->getMessage()]);

    if (isset($jobId)) {
        $pdo->prepare("UPDATE ai_jobs SET status = 'failed', error_message = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$e->getMessage(), $jobId]);
    }

    if (stripos($e->getMessage(), 'Groq API Key is not configured') !== false) {
        jsonError('Bạn chưa cấu hình Groq API key cá nhân. Vào mục AI API Key để nhập key.', 428, [
            'error_code' => 'MISSING_AI_KEY',
            'redirect_view' => 'ai_toolkit'
        ]);
    }
    jsonError($e->getMessage(), 500);
}
