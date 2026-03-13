<?php
// api/save_survey.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed', 405);
}

requireAuth();
verifyCsrfToken();
$userId = getCurrentUserId();

$data = readJsonInput();
$deptId = $data['department_id'] ?? null;

if (!$deptId) {
    jsonError('department_id is required', 400);
}

try {
    $pdo->beginTransaction();

    // Verify dept ownership
    $isAdmin = (($_SESSION['role'] ?? 'user') === 'admin');
    
    $checkQuery = $isAdmin 
        ? "SELECT id FROM departments WHERE id = ?" 
        : "SELECT id FROM departments WHERE id = ? AND user_id = ?";
    
    $chk = $pdo->prepare($checkQuery);
    $chk->execute($isAdmin ? [$deptId] : [$deptId, $userId]);
    
    if (!$chk->fetch()) {
        throw new Exception('Department not found or access denied');
    }

    // 1. Save general survey fields
    if (isset($data['sections'])) {
        foreach ($data['sections'] as $section) {
            $sectionId = $section['id'];
            $sectionName = $section['name'];
            
            foreach ($section['fields'] as $field) {
                // Delete existing field value
                if ($isAdmin) {
                    $stmt = $pdo->prepare("DELETE FROM surveys WHERE department_id = ? AND section_id = ? AND field_key = ?");
                    $stmt->execute([$deptId, $sectionId, $field['key']]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM surveys WHERE user_id = ? AND department_id = ? AND section_id = ? AND field_key = ?");
                    $stmt->execute([$userId, $deptId, $sectionId, $field['key']]);
                }

                // Insert new value
                $rawValue = (string)($field['raw_value'] ?? ($field['value'] ?? ''));
                $normalizedValue = array_key_exists('normalized_value', $field)
                    ? (string)$field['normalized_value']
                    : null;
                $normalizationState = (string)($field['normalization_state'] ?? ($normalizedValue !== null && trim($normalizedValue) !== '' ? 'normalized' : 'raw'));
                $fieldValue = ($normalizedValue !== null && trim($normalizedValue) !== '') ? $normalizedValue : $rawValue;
                $normalizedAt = $normalizationState === 'normalized' ? date('Y-m-d H:i:s') : null;

                $finalUserId = $isAdmin ? null : $userId; // Admins save as system or keep original if we had it, but for simplicity let's use null or current if we want to track who edited. Let's use the department's user_id if we want total isolation or just null.
                // Better: find the original user_id of the department if admin is editing.
                if ($isAdmin) {
                    $uStmt = $pdo->prepare("SELECT user_id FROM departments WHERE id = ?");
                    $uStmt->execute([$deptId]);
                    $deptOwner = $uStmt->fetchColumn();
                    $finalUserId = $deptOwner;
                }

                $stmt = $pdo->prepare("INSERT INTO surveys (user_id, department_id, section_id, section_name, field_key, field_label, field_value, raw_value, normalized_value, normalization_state, normalized_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $finalUserId,
                    $deptId,
                    $sectionId,
                    $sectionName,
                    $field['key'],
                    $field['label'],
                    $fieldValue,
                    $rawValue,
                    $normalizedValue,
                    $normalizationState,
                    $normalizedAt
                ]);
            }
        }
    }

    // 2. Save modules
    if (isset($data['modules'])) {
        $finalUserId = $userId;
        if ($isAdmin) {
            $uStmt = $pdo->prepare("SELECT user_id FROM departments WHERE id = ?");
            $uStmt->execute([$deptId]);
            $finalUserId = $uStmt->fetchColumn();
            
            $stmt = $pdo->prepare("DELETE FROM department_modules WHERE department_id = ?");
            $stmt->execute([$deptId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM department_modules WHERE user_id = ? AND department_id = ?");
            $stmt->execute([$userId, $deptId]);
        }
        
        foreach ($data['modules'] as $moduleName) {
            $stmt = $pdo->prepare("INSERT INTO department_modules (user_id, department_id, module_name) VALUES (?, ?, ?)");
            $stmt->execute([$finalUserId, $deptId, $moduleName]);
        }
    }

    // 3. Save KPIs
    if (isset($data['kpis'])) {
        $finalUserId = $userId;
        if ($isAdmin) {
            $uStmt = $pdo->prepare("SELECT user_id FROM departments WHERE id = ?");
            $uStmt->execute([$deptId]);
            $finalUserId = $uStmt->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM department_kpis WHERE department_id = ?");
            $stmt->execute([$deptId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM department_kpis WHERE user_id = ? AND department_id = ?");
            $stmt->execute([$userId, $deptId]);
        }
        
        foreach ($data['kpis'] as $kpiName) {
            $stmt = $pdo->prepare("INSERT INTO department_kpis (user_id, department_id, kpi_name) VALUES (?, ?, ?)");
            $stmt->execute([$finalUserId, $deptId, $kpiName]);
        }
    }

    $pdo->commit();
    jsonResponse(['status' => 'success']);

} catch (Throwable $e) {
    $pdo->rollBack();
    jsonError($e->getMessage(), 500);
}
?>
