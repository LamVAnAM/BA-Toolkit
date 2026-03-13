<?php
// api/ai_report.php
require_once __DIR__ . '/../config/bootstrap.php';
requireAuth();
$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed', 405);
}

function sanitizeReportHtml(string $html): string
{
    if (class_exists(\HTMLPurifier::class) && class_exists(\HTMLPurifier_Config::class)) {
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'h1,h2,h3,p,ul,ol,li,table,thead,tbody,tr,th,td,strong,em');
        $config->set('Cache.DefinitionImpl', null);
        $purifier = new \HTMLPurifier($config);
        return $purifier->purify($html);
    }

    $clean = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $clean = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', (string)$clean);
    $clean = preg_replace('/on[a-z]+\s*=\s*["\'][^"\']*["\']/i', '', (string)$clean);
    $clean = preg_replace('/javascript:/i', '', (string)$clean);
    return (string)$clean;
}

try {
    $payload = readJsonInput();
    $departmentId = (int)($payload['department_id'] ?? 0);
    $reportType = trim((string)($payload['report_type'] ?? 'ai_full'));

    if ($departmentId <= 0) {
        jsonError('department_id is required', 400);
    }

    $settings = loadAiSettingsForUser($pdo, $userId);

    $deptStmt = $pdo->prepare("SELECT id, name, sponsor, created_at FROM departments WHERE id = ? AND user_id = ?");
    $deptStmt->execute([$departmentId, $userId]);
    $department = $deptStmt->fetch();
    if (!$department) {
        jsonError('Department not found or access denied', 403);
    }

    $surveyStmt = $pdo->prepare("SELECT section_id, section_name, field_key, field_label, COALESCE(normalized_value, raw_value, field_value, '') AS value FROM surveys WHERE user_id = ? AND department_id = ? ORDER BY section_id ASC, id ASC");
    $surveyStmt->execute([$userId, $departmentId]);
    $surveyRows = $surveyStmt->fetchAll();

    $sections = [];
    foreach ($surveyRows as $row) {
        $val = trim((string)$row['value']);
        if ($val === '') {
            continue;
        }
        $sid = (string)$row['section_id'];
        if (!isset($sections[$sid])) {
            $sections[$sid] = [
                'section_name' => (string)$row['section_name'],
                'fields' => []
            ];
        }
        $sections[$sid]['fields'][] = [
            'key' => (string)$row['field_key'],
            'label' => (string)$row['field_label'],
            'value' => $val
        ];
    }

    $modules = $pdo->prepare("SELECT module_name FROM department_modules WHERE user_id = ? AND department_id = ?");
    $modules->execute([$userId, $departmentId]);
    $moduleList = $modules->fetchAll(PDO::FETCH_COLUMN);

    $kpis = $pdo->prepare("SELECT kpi_name FROM department_kpis WHERE user_id = ? AND department_id = ?");
    $kpis->execute([$userId, $departmentId]);
    $kpiList = $kpis->fetchAll(PDO::FETCH_COLUMN);

    $processes = $pdo->prepare("SELECT name, type, steps FROM department_processes WHERE user_id = ? AND department_id = ?");
    $processes->execute([$userId, $departmentId]);
    $processList = $processes->fetchAll();

    $entities = $pdo->prepare("SELECT id, name, entity_type, description, data_source, attributes FROM department_entities WHERE user_id = ? AND department_id = ?");
    $entities->execute([$userId, $departmentId]);
    $entityList = $entities->fetchAll();

    $attrStmt = $pdo->prepare("SELECT entity_id, name, data_type, is_primary_key, is_foreign_key, is_nullable, description FROM entity_attributes WHERE user_id = ? AND department_id = ? ORDER BY entity_id ASC, id ASC");
    $attrStmt->execute([$userId, $departmentId]);
    $attrRows = $attrStmt->fetchAll();
    $attrMap = [];
    foreach ($attrRows as $a) {
        $eid = (int)$a['entity_id'];
        if (!isset($attrMap[$eid])) $attrMap[$eid] = [];
        $attrMap[$eid][] = $a;
    }
    foreach ($entityList as &$e) {
        $eid = (int)($e['id'] ?? 0);
        $e['attributes_detail'] = $attrMap[$eid] ?? [];
    }
    unset($e);

    $relStmt = $pdo->prepare("
        SELECT r.relationship_type, r.foreign_key, r.description, ef.name AS entity_from, et.name AS entity_to
        FROM entity_relationships r
        LEFT JOIN department_entities ef ON ef.id = r.entity_from_id
        LEFT JOIN department_entities et ON et.id = r.entity_to_id
        WHERE r.user_id = ? AND r.department_id = ?
    ");
    $relStmt->execute([$userId, $departmentId]);
    $entityRelationships = $relStmt->fetchAll();

    $integrations = $pdo->prepare("SELECT system_name, interface_type, data_flow FROM department_integrations WHERE user_id = ? AND department_id = ?");
    $integrations->execute([$userId, $departmentId]);
    $integrationList = $integrations->fetchAll();

    $backlog = $pdo->prepare("SELECT requirement, priority, status FROM department_backlog WHERE user_id = ? AND department_id = ?");
    $backlog->execute([$userId, $departmentId]);
    $backlogList = $backlog->fetchAll();

    $sourcePayload = [
        'user_id' => $userId,
        'department' => $department,
        'sections' => array_values($sections),
        'modules' => $moduleList,
        'kpis' => $kpiList,
        'processes' => $processList,
        'entities' => $entityList,
        'entity_relationships' => $entityRelationships,
        'integrations' => $integrationList,
        'backlog' => $backlogList
    ];

    $systemPrompt = <<<PROMPT
Bạn là chuyên gia Business Analyst cấp Enterprise.
Hãy tạo báo cáo yêu cầu đầy đủ bằng tiếng Việt chuyên nghiệp dưới dạng HTML fragment.
Bắt buộc gồm các phần: Tổng quan, Bối cảnh đơn vị, Mục tiêu/KPI, AS-IS vs TO-BE, Yêu cầu chức năng, Yêu cầu phi chức năng, Kiến trúc dữ liệu/tích hợp, Rủi ro & giảm thiểu, Backlog ưu tiên, Lộ trình triển khai.
Quy tắc:
- Chỉ dùng thẻ: h1,h2,h3,p,ul,ol,li,table,thead,tbody,tr,th,td,strong,em
- Không dùng script/style.
- Không bịa dữ liệu không có.
PROMPT;

    $userPrompt = "Tạo báo cáo cho dữ liệu sau (JSON):\n" . json_encode($sourcePayload, JSON_UNESCAPED_UNICODE);

    $aiResponse = callAiChat(
        $pdo,
        $settings,
        [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ],
        [
            'temperature' => 0.25,
            'max_tokens' => 3000,
            'model' => $settings['ai_report_model'] ?? (
                (($settings['ai_provider'] ?? 'groq') === 'ollama')
                    ? ($settings['ai_model'] ?? 'llama3.1:8b')
                    : ($settings['groq_model'] ?? 'llama-3.3-70b-versatile')
            )
        ]
    );

    $html = sanitizeReportHtml($aiResponse['content']);
    $text = trim(strip_tags($html));

    $insert = $pdo->prepare("INSERT INTO report_versions (user_id, department_id, report_type, title, content_html, content_text, source_payload) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insert->execute([
        $userId,
        $departmentId,
        $reportType,
        'AI Requirement Report - ' . date('Y-m-d H:i'),
        $html,
        $text,
        json_encode($sourcePayload, JSON_UNESCAPED_UNICODE)
    ]);

    jsonResponse([
        'success' => true,
        'report_id' => (int)$pdo->lastInsertId(),
        'html' => $html,
        'meta' => [
            'model' => $aiResponse['model'],
            'latency_ms' => $aiResponse['latency_ms']
        ]
    ]);
} catch (Throwable $e) {
    appLog('ai_report', 'AI report failed', ['error' => $e->getMessage()]);
    if (stripos($e->getMessage(), 'Groq API Key is not configured') !== false) {
        jsonError('Bạn chưa cấu hình Groq API key cá nhân. Vào mục AI API Key để nhập key.', 428, [
            'error_code' => 'MISSING_AI_KEY',
            'redirect_view' => 'ai_toolkit'
        ]);
    }
    jsonError($e->getMessage(), 500);
}
