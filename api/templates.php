<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireAuth();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    jsonError('Method Not Allowed', 405);
}

$module = trim((string)($_GET['module'] ?? ''));
$key = trim((string)($_GET['key'] ?? ''));

try {
    if ($module === '') {
        jsonResponse(['items' => listTemplates(null)]);
    }

    if ($key !== '') {
        jsonResponse(loadTemplateManifest($module, $key));
    }

    jsonResponse(['items' => listTemplates($module)]);
} catch (Throwable $e) {
    appLog('templates', 'Template API failed', [
        'module' => $module,
        'key' => $key,
        'error' => $e->getMessage(),
    ]);
    jsonError($e->getMessage(), 400);
}
