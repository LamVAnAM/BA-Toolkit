<?php
// api/export_report_docx.php
require_once __DIR__ . '/../config/bootstrap.php';
requireAuth();
$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method Not Allowed', 405);
}

if (!class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
    jsonError('PHPWord is not installed', 500);
}

try {
    $departmentId = (int)($_GET['department_id'] ?? 0);
    if ($departmentId <= 0) {
        jsonError('department_id is required', 400);
    }

    $deptStmt = $pdo->prepare("SELECT id, name FROM departments WHERE id = ? AND user_id = ?");
    $deptStmt->execute([$departmentId, $userId]);
    $dept = $deptStmt->fetch();
    if (!$dept) {
        jsonError('Department not found', 404);
    }

    $reportStmt = $pdo->prepare("SELECT id, title, content_text, created_at FROM report_versions WHERE department_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1");
    $reportStmt->execute([$departmentId, $userId]);
    $report = $reportStmt->fetch();
    if (!$report) {
        jsonError('No report version found. Generate AI report first.', 404);
    }

    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $phpWord->setDefaultFontName('Calibri');
    $phpWord->setDefaultFontSize(11);
    $section = $phpWord->addSection();
    $section->addTitle($report['title'] ?: 'AI Requirement Report', 1);
    $section->addText('Department: ' . ($dept['name'] ?? 'N/A'));
    $section->addText('Generated at: ' . ($report['created_at'] ?? date('Y-m-d H:i:s')));
    $section->addTextBreak(1);

    $text = trim((string)$report['content_text']);
    foreach (preg_split('/\R+/', $text) as $line) {
        if ($line !== '') {
            $section->addText($line);
        }
    }

    $tmpDir = __DIR__ . '/../storage/tmp';
    if (!is_dir($tmpDir)) {
        @mkdir($tmpDir, 0777, true);
    }
    $safeDept = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', (string)$dept['name']);
    $fileName = 'AI_Report_' . $safeDept . '_' . date('Ymd_His') . '.docx';
    $tmpPath = $tmpDir . '/' . $fileName;

    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save($tmpPath);

    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($tmpPath));
    readfile($tmpPath);
    @unlink($tmpPath);
    exit;
} catch (Throwable $e) {
    appLog('export_docx', 'DOCX export failed', ['error' => $e->getMessage()]);
    jsonError($e->getMessage(), 500);
}
