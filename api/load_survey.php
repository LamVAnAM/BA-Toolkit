<?php
// api/load_survey.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth_helper.php';

requireAuth();
$userId = getCurrentUserId();

$deptId = $_GET['department_id'] ?? null;

if (!$deptId) {
    jsonError('department_id is required', 400);
}

try {
    // Verify ownership
    $isAdmin = (($_SESSION['role'] ?? 'user') === 'admin');
    
    $checkQuery = $isAdmin 
        ? "SELECT id FROM departments WHERE id = ?" 
        : "SELECT id FROM departments WHERE id = ? AND user_id = ?";
    
    $chk = $pdo->prepare($checkQuery);
    $chk->execute($isAdmin ? [$deptId] : [$deptId, $userId]);
    
    if (!$chk->fetch()) {
        jsonError('Department not found or access denied', 404);
    }

    // 1. Get survey fields
    if ($isAdmin) {
        $stmt = $pdo->prepare("SELECT * FROM surveys WHERE department_id = ? ORDER BY section_id ASC, id ASC");
        $stmt->execute([$deptId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM surveys WHERE user_id = ? AND department_id = ? ORDER BY section_id ASC, id ASC");
        $stmt->execute([$userId, $deptId]);
    }
    $fields = $stmt->fetchAll();

    // 2. Get modules
    if ($isAdmin) {
        $stmt = $pdo->prepare("SELECT module_name FROM department_modules WHERE department_id = ?");
        $stmt->execute([$deptId]);
    } else {
        $stmt = $pdo->prepare("SELECT module_name FROM department_modules WHERE user_id = ? AND department_id = ?");
        $stmt->execute([$userId, $deptId]);
    }
    $modules = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 3. Get KPIs
    if ($isAdmin) {
        $stmt = $pdo->prepare("SELECT kpi_name FROM department_kpis WHERE department_id = ?");
        $stmt->execute([$deptId]);
    } else {
        $stmt = $pdo->prepare("SELECT kpi_name FROM department_kpis WHERE user_id = ? AND department_id = ?");
        $stmt->execute([$userId, $deptId]);
    }
    $kpis = $stmt->fetchAll(PDO::FETCH_COLUMN);

    jsonResponse([
        'department_id' => $deptId,
        'fields' => $fields,
        'modules' => $modules,
        'kpis' => $kpis
    ]);

} catch (Throwable $e) {
    jsonError($e->getMessage(), 500);
}
?>
