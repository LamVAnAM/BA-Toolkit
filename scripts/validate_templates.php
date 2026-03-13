<?php
require_once __DIR__ . '/../config/template_helper.php';

$modules = getAllowedTemplateModules();
$root = getTemplateRoot();
$errors = [];
$checked = 0;

foreach ($modules as $module) {
    $moduleDir = $root . DIRECTORY_SEPARATOR . $module;
    if (!is_dir($moduleDir)) {
        continue;
    }

    $dirs = glob($moduleDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
    foreach ($dirs as $dir) {
        $key = basename($dir);
        $checked++;

        try {
            $manifest = loadTemplateManifest($module, $key);
            $payload = $manifest['payload'] ?? null;
            if (!is_array($payload)) {
                throw new RuntimeException("Payload must be an object for {$module}/{$key}.");
            }

            switch ($module) {
                case 'organization':
                    if (!isset($payload['departments']) || !is_array($payload['departments'])) {
                        throw new RuntimeException("organization template {$key} must contain payload.departments[].");
                    }
                    break;
                case 'process_mapping':
                case 'integration':
                case 'backlog':
                    if (!isset($payload['items']) || !is_array($payload['items'])) {
                        throw new RuntimeException("{$module} template {$key} must contain payload.items[].");
                    }
                    break;
                case 'data_architecture':
                    if (!isset($payload['entities']) || !is_array($payload['entities'])) {
                        throw new RuntimeException("data_architecture template {$key} must contain payload.entities[].");
                    }
                    if (isset($payload['relationships']) && !is_array($payload['relationships'])) {
                        throw new RuntimeException("data_architecture template {$key} payload.relationships must be an array.");
                    }
                    break;
                case 'reports':
                    if (!isset($payload['sections']) || !is_array($payload['sections'])) {
                        throw new RuntimeException("reports template {$key} must contain payload.sections[].");
                    }
                    break;
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

if ($errors) {
    fwrite(STDERR, "Template validation failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }
    exit(1);
}

fwrite(STDOUT, "Validated {$checked} template(s) successfully.\n");
