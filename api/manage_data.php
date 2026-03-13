<?php
// api/manage_data.php
require_once __DIR__ . '/../config/bootstrap.php';
requireAuth();
$userId = getCurrentUserId();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ''; // load or save
$type = $_GET['type'] ?? ''; // processes, entities, integrations, backlog
$deptId = $_GET['department_id'] ?? ($_POST['department_id'] ?? null);

if (!$deptId && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $deptId = $data['department_id'] ?? null;
}

if (!$deptId || !$type) {
    jsonError('Missing department_id or type', 400);
}

// Global Ownership Check
$stmt = $pdo->prepare("SELECT id FROM departments WHERE id = ? AND user_id = ?");
$stmt->execute([$deptId, $userId]);
if (!$stmt->fetch()) {
    jsonError('Department not found or access denied', 403);
}

$tableMap = [
    'processes' => 'department_processes',
    'entities' => 'department_entities',
    'integrations' => 'department_integrations',
    'backlog' => 'department_backlog'
];

$table = $tableMap[$type] ?? null;
if (!$table) {
    jsonError('Invalid type', 400);
}

try {
    if ($method === 'GET') {
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE department_id = ? AND user_id = ?");
        $stmt->execute([$deptId, $userId]);
        jsonResponse($stmt->fetchAll());
    } elseif ($method === 'POST') {
        $data = readJsonInput();
        if ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ? AND user_id = ?");
            $stmt->execute([$data['id'], $userId]);
        } else {
            // Save logic (Insert or Update)
            $fieldsToSkip = ['id', 'department_id', 'user_id'];
            $fields = array_keys($data);
            $fields = array_filter($fields, fn($f) => !in_array($f, $fieldsToSkip));

            // Column whitelist from schema to block unexpected keys
            $columnInfo = $pdo->query("PRAGMA table_info($table)")->fetchAll();
            $validColumns = array_map(static fn($row) => $row['name'], $columnInfo);
            $fields = array_values(array_filter($fields, static fn($field) => in_array($field, $validColumns, true)));
            
            if (isset($data['id'])) {
                // Update
                if (!$fields) {
                    jsonResponse(['status' => 'success', 'message' => 'No fields to update']);
                    return;
                }
                $setPart = implode(' = ?, ', $fields) . ' = ?';
                $stmt = $pdo->prepare("UPDATE $table SET $setPart WHERE id = ? AND user_id = ?");
                $params = array_map(fn($f) => $data[$f], $fields);
                $params[] = $data['id'];
                $params[] = $userId;
                $stmt->execute($params);
            } else {
                // Insert
                $colPart = implode(', ', array_merge(['department_id', 'user_id'], $fields));
                $valPart = implode(', ', array_fill(0, count($fields) + 2, '?'));
                $stmt = $pdo->prepare("INSERT INTO $table ($colPart) VALUES ($valPart)");
                $params = array_merge([$deptId, $userId], array_map(fn($f) => $data[$f], $fields));
                $stmt->execute($params);
            }
        }
        jsonResponse(['status' => 'success']);
    } else {
        jsonError('Method Not Allowed', 405);
    }
}
 catch (Throwable $e) {
    appLog('manage_data', 'manage_data failed', ['error' => $e->getMessage(), 'type' => $type, 'action' => $action]);
    jsonError($e->getMessage(), 500);
}
?>
