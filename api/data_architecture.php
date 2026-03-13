<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/ai_client.php';

requireAuth();
$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];
$action = strtolower(trim((string)($_GET['action'] ?? ($_POST['action'] ?? 'list'))));
$departmentId = (int)($_GET['department_id'] ?? ($_POST['department_id'] ?? 0));

if ($departmentId <= 0) {
    $payload = readJsonInput();
    $departmentId = (int)($payload['department_id'] ?? 0);
}
if ($departmentId <= 0) {
    jsonError('department_id is required', 400);
}

$deptStmt = $pdo->prepare("SELECT id FROM departments WHERE id = ? AND user_id = ?");
$deptStmt->execute([$departmentId, $userId]);
if (!$deptStmt->fetch()) {
    jsonError('Department not found or access denied', 403);
}

function daFetchEntities(PDO $pdo, int $userId, int $departmentId): array
{
    $entitiesStmt = $pdo->prepare("SELECT * FROM department_entities WHERE user_id = ? AND department_id = ? ORDER BY name ASC");
    $entitiesStmt->execute([$userId, $departmentId]);
    $entities = $entitiesStmt->fetchAll();

    $attrsStmt = $pdo->prepare("SELECT * FROM entity_attributes WHERE user_id = ? AND department_id = ? ORDER BY entity_id ASC, is_primary_key DESC, name ASC");
    $attrsStmt->execute([$userId, $departmentId]);
    $attrs = $attrsStmt->fetchAll();
    $attrMap = [];
    foreach ($attrs as $a) {
        $eid = (int)$a['entity_id'];
        if (!isset($attrMap[$eid])) $attrMap[$eid] = [];
        $attrMap[$eid][] = $a;
    }

    foreach ($entities as &$e) {
        $eid = (int)$e['id'];
        $entityAttrs = $attrMap[$eid] ?? [];
        $e['attributes_detail'] = $entityAttrs;
        if (!empty($entityAttrs)) {
            $e['attributes'] = implode(', ', array_map(static fn($x) => (string)$x['name'], $entityAttrs));
        }
    }
    unset($e);
    return $entities;
}

function daFetchRelationships(PDO $pdo, int $userId, int $departmentId): array
{
    $stmt = $pdo->prepare("
        SELECT r.*, ef.name AS from_name, et.name AS to_name
        FROM entity_relationships r
        LEFT JOIN department_entities ef ON ef.id = r.entity_from_id
        LEFT JOIN department_entities et ON et.id = r.entity_to_id
        WHERE r.user_id = ? AND r.department_id = ?
        ORDER BY r.id DESC
    ");
    $stmt->execute([$userId, $departmentId]);
    return $stmt->fetchAll();
}

function daBuildQualityMetrics(array $entities): array
{
    $metrics = ['entities' => count($entities), 'attributes_total' => 0, 'primary_keys' => 0, 'foreign_keys' => 0, 'nullable_pct' => 0];
    $nullable = 0;
    foreach ($entities as $e) {
        foreach (($e['attributes_detail'] ?? []) as $a) {
            $metrics['attributes_total']++;
            if ((int)($a['is_primary_key'] ?? 0) === 1) $metrics['primary_keys']++;
            if ((int)($a['is_foreign_key'] ?? 0) === 1) $metrics['foreign_keys']++;
            if ((int)($a['is_nullable'] ?? 1) === 1) $nullable++;
        }
    }
    if ($metrics['attributes_total'] > 0) {
        $metrics['nullable_pct'] = round(($nullable / $metrics['attributes_total']) * 100, 1);
    }
    return $metrics;
}

function daFindEntityIdByName(PDO $pdo, int $userId, int $departmentId, string $name): int
{
    $stmt = $pdo->prepare("SELECT id FROM department_entities WHERE user_id = ? AND department_id = ? AND lower(name) = lower(?) LIMIT 1");
    $stmt->execute([$userId, $departmentId, trim($name)]);
    return (int)($stmt->fetchColumn() ?: 0);
}

function daGetEntitySnapshot(PDO $pdo, int $userId, int $departmentId, int $entityId): array
{
    $eStmt = $pdo->prepare("SELECT id, name, entity_type, description, data_source, attributes FROM department_entities WHERE id = ? AND user_id = ? AND department_id = ?");
    $eStmt->execute([$entityId, $userId, $departmentId]);
    $entity = $eStmt->fetch() ?: [];
    $aStmt = $pdo->prepare("SELECT name, data_type, is_primary_key, is_foreign_key, is_nullable, reference_entity, description FROM entity_attributes WHERE user_id = ? AND department_id = ? AND entity_id = ? ORDER BY id ASC");
    $aStmt->execute([$userId, $departmentId, $entityId]);
    $attrs = $aStmt->fetchAll();
    return ['entity' => $entity, 'attributes' => $attrs];
}

function daBuildConflictFromSnapshots(array $before, array $after): array
{
    $changed = [];
    $keys = ['entity_type', 'description', 'data_source', 'attributes'];
    foreach ($keys as $k) {
        $oldVal = (string)($before['entity'][$k] ?? '');
        $newVal = (string)($after['entity'][$k] ?? '');
        if ($oldVal !== $newVal) {
            $changed[] = ['field' => $k, 'old' => $oldVal, 'new' => $newVal];
        }
    }

    $beforeMap = [];
    foreach (($before['attributes'] ?? []) as $a) {
        $beforeMap[strtolower((string)$a['name'])] = $a;
    }
    $afterMap = [];
    foreach (($after['attributes'] ?? []) as $a) {
        $afterMap[strtolower((string)$a['name'])] = $a;
    }
    $added = [];
    $removed = [];
    $modified = [];

    foreach ($afterMap as $k => $a) {
        if (!isset($beforeMap[$k])) {
            $added[] = $a['name'];
            continue;
        }
        $b = $beforeMap[$k];
        $sub = [];
        foreach (['data_type', 'is_primary_key', 'is_foreign_key', 'is_nullable', 'reference_entity', 'description'] as $f) {
            if ((string)($b[$f] ?? '') !== (string)($a[$f] ?? '')) {
                $sub[] = $f;
            }
        }
        if ($sub) {
            $modified[] = ['name' => $a['name'], 'fields' => $sub];
        }
    }
    foreach ($beforeMap as $k => $a) {
        if (!isset($afterMap[$k])) {
            $removed[] = $a['name'];
        }
    }

    return [
        'entity_name' => (string)($after['entity']['name'] ?? ($before['entity']['name'] ?? '')),
        'changed_fields' => $changed,
        'attributes_added' => $added,
        'attributes_removed' => $removed,
        'attributes_modified' => $modified
    ];
}

function daValidateImportJsonSchema(array $payload): void
{
    if (!class_exists(\Opis\JsonSchema\Validator::class)) {
        return;
    }
    $schema = json_decode('{
      "type":"object",
      "required":["entities"],
      "properties":{
        "entities":{
          "type":"array",
          "items":{
            "type":"object",
            "required":["name"],
            "properties":{
              "name":{"type":"string"},
              "entity_type":{"type":"string"},
              "description":{"type":"string"},
              "data_source":{"type":"string"},
              "attributes_detail":{
                "type":"array",
                "items":{
                  "type":"object",
                  "required":["name"],
                  "properties":{
                    "name":{"type":"string"},
                    "data_type":{"type":"string"},
                    "is_primary_key":{"type":["integer","boolean"]},
                    "is_foreign_key":{"type":["integer","boolean"]},
                    "is_nullable":{"type":["integer","boolean"]},
                    "reference_entity":{"type":"string"},
                    "description":{"type":"string"}
                  }
                }
              }
            }
          }
        },
        "relationships":{
          "type":"array",
          "items":{
            "type":"object",
            "properties":{
              "from_name":{"type":"string"},
              "to_name":{"type":"string"},
              "entity_from":{"type":"string"},
              "entity_to":{"type":"string"},
              "relationship_type":{"type":"string"},
              "foreign_key":{"type":"string"},
              "description":{"type":"string"}
            }
          }
        }
      }
    }');
    $validator = new \Opis\JsonSchema\Validator();
    $result = $validator->validate(json_decode(json_encode($payload)), $schema);
    if (!$result->isValid()) {
        throw new Exception('JSON schema validation failed for import payload.');
    }
}

try {
    if ($method === 'GET' && $action === 'list') {
        $entities = daFetchEntities($pdo, $userId, $departmentId);
        $relationships = daFetchRelationships($pdo, $userId, $departmentId);
        $quality = daBuildQualityMetrics($entities);
        jsonResponse(['entities' => $entities, 'relationships' => $relationships, 'quality' => $quality]);
    }

    if ($method === 'GET' && $action === 'dictionary') {
        $entities = daFetchEntities($pdo, $userId, $departmentId);
        jsonResponse(['dictionary' => $entities]);
    }

    if ($method === 'GET' && $action === 'versions') {
        $entityId = (int)($_GET['entity_id'] ?? 0);
        if ($entityId <= 0) jsonError('entity_id is required', 400);
        $stmt = $pdo->prepare("SELECT * FROM entity_versions WHERE user_id = ? AND department_id = ? AND entity_id = ? ORDER BY id DESC LIMIT 50");
        $stmt->execute([$userId, $departmentId, $entityId]);
        jsonResponse(['versions' => $stmt->fetchAll()]);
    }

    if ($method === 'POST' && $action === 'save_entity') {
        $payload = readJsonInput();
        $entity = $payload['entity'] ?? [];
        $entityId = isset($entity['id']) ? (int)$entity['id'] : 0;
        $name = trim((string)($entity['name'] ?? ''));
        if ($name === '') jsonError('Entity name is required', 400);

        $entityType = trim((string)($entity['entity_type'] ?? 'master'));
        $description = trim((string)($entity['description'] ?? ''));
        $dataSource = trim((string)($entity['data_source'] ?? ''));
        $attributes = is_array($entity['attributes'] ?? null) ? $entity['attributes'] : [];

        $pdo->beginTransaction();
        if ($entityId > 0) {
            $stmt = $pdo->prepare("UPDATE department_entities SET name = ?, entity_type = ?, description = ?, data_source = ?, attributes = ? WHERE id = ? AND user_id = ? AND department_id = ?");
            $stmt->execute([$name, $entityType, $description, $dataSource, '', $entityId, $userId, $departmentId]);
            if ($stmt->rowCount() === 0) throw new Exception('Entity not found or access denied');
            $pdo->prepare("DELETE FROM entity_attributes WHERE user_id = ? AND department_id = ? AND entity_id = ?")->execute([$userId, $departmentId, $entityId]);
            $actionType = 'update';
        } else {
            $stmt = $pdo->prepare("INSERT INTO department_entities (user_id, department_id, name, attributes, relationships, entity_type, description, data_source) VALUES (?, ?, ?, '', '', ?, ?, ?)");
            $stmt->execute([$userId, $departmentId, $name, $entityType, $description, $dataSource]);
            $entityId = (int)$pdo->lastInsertId();
            $actionType = 'create';
        }

        $attrInsert = $pdo->prepare("INSERT INTO entity_attributes (user_id, department_id, entity_id, name, data_type, is_primary_key, is_foreign_key, is_nullable, reference_entity, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $legacyNames = [];
        foreach ($attributes as $a) {
            $attrName = trim((string)($a['name'] ?? ''));
            if ($attrName === '') continue;
            $legacyNames[] = $attrName;
            $attrInsert->execute([
                $userId,
                $departmentId,
                $entityId,
                $attrName,
                trim((string)($a['data_type'] ?? 'string')),
                !empty($a['is_primary_key']) ? 1 : 0,
                !empty($a['is_foreign_key']) ? 1 : 0,
                !isset($a['is_nullable']) || !empty($a['is_nullable']) ? 1 : 0,
                trim((string)($a['reference_entity'] ?? '')),
                trim((string)($a['description'] ?? ''))
            ]);
        }

        $pdo->prepare("UPDATE department_entities SET attributes = ? WHERE id = ? AND user_id = ? AND department_id = ?")
            ->execute([implode(', ', $legacyNames), $entityId, $userId, $departmentId]);

        $snapshot = json_encode(['entity' => $entity, 'saved_attributes' => $attributes], JSON_UNESCAPED_UNICODE);
        $pdo->prepare("INSERT INTO entity_versions (user_id, department_id, entity_id, action_type, snapshot) VALUES (?, ?, ?, ?, ?)")
            ->execute([$userId, $departmentId, $entityId, $actionType, $snapshot]);

        $pdo->commit();
        jsonResponse(['success' => true, 'entity_id' => $entityId]);
    }

    if ($method === 'POST' && $action === 'delete_entity') {
        $payload = readJsonInput();
        $entityId = (int)($payload['entity_id'] ?? 0);
        if ($entityId <= 0) jsonError('entity_id is required', 400);
        $stmt = $pdo->prepare("DELETE FROM department_entities WHERE id = ? AND user_id = ? AND department_id = ?");
        $stmt->execute([$entityId, $userId, $departmentId]);
        jsonResponse(['success' => true]);
    }

    if ($method === 'POST' && $action === 'save_relationship') {
        $payload = readJsonInput();
        $rel = $payload['relationship'] ?? [];
        $rid = isset($rel['id']) ? (int)$rel['id'] : 0;
        $from = (int)($rel['entity_from_id'] ?? 0);
        $to = (int)($rel['entity_to_id'] ?? 0);
        $type = trim((string)($rel['relationship_type'] ?? 'one-to-many'));
        if ($from <= 0 || $to <= 0) jsonError('Both entities are required', 400);

        if ($rid > 0) {
            $stmt = $pdo->prepare("UPDATE entity_relationships SET entity_from_id = ?, entity_to_id = ?, relationship_type = ?, foreign_key = ?, description = ? WHERE id = ? AND user_id = ? AND department_id = ?");
            $stmt->execute([$from, $to, $type, trim((string)($rel['foreign_key'] ?? '')), trim((string)($rel['description'] ?? '')), $rid, $userId, $departmentId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO entity_relationships (user_id, department_id, entity_from_id, entity_to_id, relationship_type, foreign_key, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $departmentId, $from, $to, $type, trim((string)($rel['foreign_key'] ?? '')), trim((string)($rel['description'] ?? ''))]);
        }
        jsonResponse(['success' => true]);
    }

    if ($method === 'POST' && $action === 'delete_relationship') {
        $payload = readJsonInput();
        $rid = (int)($payload['relationship_id'] ?? 0);
        if ($rid <= 0) jsonError('relationship_id is required', 400);
        $stmt = $pdo->prepare("DELETE FROM entity_relationships WHERE id = ? AND user_id = ? AND department_id = ?");
        $stmt->execute([$rid, $userId, $departmentId]);
        jsonResponse(['success' => true]);
    }

    if ($method === 'POST' && $action === 'import_csv') {
        $payload = readJsonInput();
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
        if (!$rows) jsonError('No rows to import', 400);
        $inserted = 0;
        $updated = 0;
        $attrInsert = $pdo->prepare("INSERT INTO entity_attributes (user_id, department_id, entity_id, name, data_type, is_primary_key, is_foreign_key, is_nullable, reference_entity, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $conflicts = [];
        $pdo->beginTransaction();
        foreach ($rows as $row) {
            $wasMerged = false;
            $before = ['entity' => [], 'attributes' => []];
            $name = trim((string)($row['entity_name'] ?? $row['name'] ?? ''));
            if ($name === '') continue;
            $attrs = trim((string)($row['attributes'] ?? ''));
            $entityType = trim((string)($row['entity_type'] ?? 'master'));
            $desc = trim((string)($row['description'] ?? ''));
            $source = trim((string)($row['data_source'] ?? ''));

            $entityId = daFindEntityIdByName($pdo, $userId, $departmentId, $name);
            if ($entityId > 0) {
                $before = daGetEntitySnapshot($pdo, $userId, $departmentId, $entityId);
                $stmt = $pdo->prepare("UPDATE department_entities SET entity_type = ?, description = ?, data_source = ?, attributes = ? WHERE id = ? AND user_id = ? AND department_id = ?");
                $stmt->execute([$entityType, $desc, $source, $attrs, $entityId, $userId, $departmentId]);
                $pdo->prepare("DELETE FROM entity_attributes WHERE user_id = ? AND department_id = ? AND entity_id = ?")->execute([$userId, $departmentId, $entityId]);
                $updated++;
                $wasMerged = true;
            } else {
                $stmt = $pdo->prepare("INSERT INTO department_entities (user_id, department_id, name, attributes, relationships, entity_type, description, data_source) VALUES (?, ?, ?, ?, '', ?, ?, ?)");
                $stmt->execute([$userId, $departmentId, $name, $attrs, $entityType, $desc, $source]);
                $entityId = (int)$pdo->lastInsertId();
                $inserted++;
            }

            // Parse compact attribute syntax: id:int:pk, user_id:int:fk:ref=User, amount:decimal:notnull
            $parts = array_filter(array_map('trim', preg_split('/[,\n]+/', $attrs)));
            foreach ($parts as $p) {
                $tokens = array_map('trim', explode(':', $p));
                $attrName = (string)($tokens[0] ?? '');
                if ($attrName === '') continue;
                $dataType = (string)($tokens[1] ?? 'string');
                $flagBlob = strtolower(implode(':', array_slice($tokens, 2)));
                $isPk = strpos($flagBlob, 'pk') !== false ? 1 : 0;
                $isFk = strpos($flagBlob, 'fk') !== false ? 1 : 0;
                $isNullable = (strpos($flagBlob, 'notnull') !== false || strpos($flagBlob, 'required') !== false) ? 0 : 1;
                $ref = '';
                if (preg_match('/ref=([a-zA-Z0-9_]+)/i', $flagBlob, $m)) $ref = $m[1];
                $attrInsert->execute([$userId, $departmentId, $entityId, $attrName, $dataType, $isPk, $isFk, $isNullable, $ref, '']);
            }
            if ($wasMerged) {
                $after = daGetEntitySnapshot($pdo, $userId, $departmentId, $entityId);
                $conflicts[] = daBuildConflictFromSnapshots($before, $after);
            }
        }
        $pdo->commit();
        jsonResponse(['success' => true, 'inserted' => $inserted, 'updated' => $updated, 'conflicts' => $conflicts]);
    }

    if ($method === 'POST' && $action === 'import_json') {
        $payload = readJsonInput();
        daValidateImportJsonSchema($payload);
        $entities = is_array($payload['entities'] ?? null) ? $payload['entities'] : [];
        $relationships = is_array($payload['relationships'] ?? null) ? $payload['relationships'] : [];
        if (!$entities) jsonError('No entities in JSON', 400);

        $nameToId = [];
        $inserted = 0;
        $updated = 0;
        $attrInsert = $pdo->prepare("INSERT INTO entity_attributes (user_id, department_id, entity_id, name, data_type, is_primary_key, is_foreign_key, is_nullable, reference_entity, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $conflicts = [];
        $pdo->beginTransaction();
        foreach ($entities as $e) {
            $wasMerged = false;
            $before = ['entity' => [], 'attributes' => []];
            $name = trim((string)($e['name'] ?? ''));
            if ($name === '') continue;
            $entityType = trim((string)($e['entity_type'] ?? 'master'));
            $desc = trim((string)($e['description'] ?? ''));
            $source = trim((string)($e['data_source'] ?? ''));
            $attrs = is_array($e['attributes_detail'] ?? null) ? $e['attributes_detail'] : (is_array($e['attributes'] ?? null) ? $e['attributes'] : []);

            $entityId = daFindEntityIdByName($pdo, $userId, $departmentId, $name);
            if ($entityId > 0) {
                $before = daGetEntitySnapshot($pdo, $userId, $departmentId, $entityId);
                $pdo->prepare("UPDATE department_entities SET entity_type = ?, description = ?, data_source = ?, attributes = '' WHERE id = ? AND user_id = ? AND department_id = ?")
                    ->execute([$entityType, $desc, $source, $entityId, $userId, $departmentId]);
                $pdo->prepare("DELETE FROM entity_attributes WHERE user_id = ? AND department_id = ? AND entity_id = ?")
                    ->execute([$userId, $departmentId, $entityId]);
                $updated++;
                $wasMerged = true;
            } else {
                $pdo->prepare("INSERT INTO department_entities (user_id, department_id, name, attributes, relationships, entity_type, description, data_source) VALUES (?, ?, ?, '', '', ?, ?, ?)")
                    ->execute([$userId, $departmentId, $name, $entityType, $desc, $source]);
                $entityId = (int)$pdo->lastInsertId();
                $inserted++;
            }
            $nameToId[strtolower($name)] = $entityId;

            $legacyNames = [];
            foreach ($attrs as $a) {
                $attrName = trim((string)($a['name'] ?? ''));
                if ($attrName === '') continue;
                $legacyNames[] = $attrName;
                $attrInsert->execute([
                    $userId, $departmentId, $entityId, $attrName,
                    trim((string)($a['data_type'] ?? 'string')),
                    !empty($a['is_primary_key']) ? 1 : 0,
                    !empty($a['is_foreign_key']) ? 1 : 0,
                    !isset($a['is_nullable']) || !empty($a['is_nullable']) ? 1 : 0,
                    trim((string)($a['reference_entity'] ?? '')),
                    trim((string)($a['description'] ?? ''))
                ]);
            }
            $pdo->prepare("UPDATE department_entities SET attributes = ? WHERE id = ?")->execute([implode(', ', $legacyNames), $entityId]);
            if ($wasMerged) {
                $after = daGetEntitySnapshot($pdo, $userId, $departmentId, $entityId);
                $conflicts[] = daBuildConflictFromSnapshots($before, $after);
            }
        }

        // Rebuild relationships from JSON (merge by from/to/type/fk)
        foreach ($relationships as $r) {
            $fromName = trim((string)($r['from_name'] ?? $r['entity_from'] ?? ''));
            $toName = trim((string)($r['to_name'] ?? $r['entity_to'] ?? ''));
            $fromId = $nameToId[strtolower($fromName)] ?? daFindEntityIdByName($pdo, $userId, $departmentId, $fromName);
            $toId = $nameToId[strtolower($toName)] ?? daFindEntityIdByName($pdo, $userId, $departmentId, $toName);
            if ($fromId <= 0 || $toId <= 0) continue;
            $rtype = trim((string)($r['relationship_type'] ?? 'one-to-many'));
            $fk = trim((string)($r['foreign_key'] ?? ''));
            $desc = trim((string)($r['description'] ?? ''));

            $exists = $pdo->prepare("SELECT id FROM entity_relationships WHERE user_id = ? AND department_id = ? AND entity_from_id = ? AND entity_to_id = ? AND relationship_type = ? AND COALESCE(foreign_key,'') = COALESCE(?, '') LIMIT 1");
            $exists->execute([$userId, $departmentId, $fromId, $toId, $rtype, $fk]);
            if ($exists->fetch()) continue;

            $pdo->prepare("INSERT INTO entity_relationships (user_id, department_id, entity_from_id, entity_to_id, relationship_type, foreign_key, description) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$userId, $departmentId, $fromId, $toId, $rtype, $fk, $desc]);
        }
        $pdo->commit();
        jsonResponse(['success' => true, 'inserted' => $inserted, 'updated' => $updated, 'conflicts' => $conflicts]);
    }

    if ($method === 'POST' && $action === 'ai_generate') {
        $payload = readJsonInput();
        $content = trim((string)($payload['content'] ?? ''));
        if ($content === '') jsonError('content is required', 400);
        $settings = loadAiSettingsForUser($pdo, $userId);
        $prompt = "Extract entities, detailed attributes, and relationships from the text. Return strict JSON object with schema: {\"entities\":[{\"name\":\"...\",\"entity_type\":\"master|transaction|lookup\",\"description\":\"...\",\"attributes\":[{\"name\":\"...\",\"data_type\":\"string|int|date|boolean|decimal|datetime\",\"is_primary_key\":0,\"is_foreign_key\":0,\"is_nullable\":1,\"reference_entity\":\"\",\"description\":\"\"}]}],\"relationships\":[{\"from\":\"EntityA\",\"to\":\"EntityB\",\"relationship_type\":\"one-to-one|one-to-many|many-to-many\",\"foreign_key\":\"...\",\"description\":\"...\"}]}";
        try {
            $res = callAiChat($pdo, $settings, [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => $content]
            ], [
                'temperature' => 0.1,
                'model' => ($settings['ai_provider'] ?? 'groq') === 'ollama' ? ($settings['ai_model'] ?? 'llama3.1:8b') : ($settings['groq_model'] ?? 'llama-3.3-70b-versatile'),
                'response_format' => ['type' => 'json_object']
            ]);
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'API Key is not configured') !== false) {
                jsonError('Bạn chưa cấu hình Groq API key cá nhân. Vào mục AI API Key để nhập key.', 428, [
                    'error_code' => 'MISSING_AI_KEY',
                    'redirect_view' => 'ai_toolkit'
                ]);
            }
            throw $e;
        }

        $decoded = json_decode((string)$res['content'], true);
        if (!is_array($decoded)) jsonError('AI response is invalid JSON', 500);
        $entities = $decoded['entities'] ?? $decoded;
        $relationships = $decoded['relationships'] ?? [];
        if (!is_array($entities)) $entities = [];
        if (!is_array($relationships)) $relationships = [];
        jsonResponse(['success' => true, 'entities' => $entities, 'relationships' => $relationships, 'meta' => ['model' => $res['model']]]);
    }

    jsonError('Invalid action', 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    appLog('data_architecture', 'Data architecture API failed', ['action' => $action, 'error' => $e->getMessage()]);
    jsonError($e->getMessage(), 500);
}
