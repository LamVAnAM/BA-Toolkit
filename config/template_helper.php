<?php

function getTemplateRoot(): string
{
    return realpath(__DIR__ . '/../templates') ?: (__DIR__ . '/../templates');
}

function normalizeTemplateModule(string $module): string
{
    return strtolower(trim($module));
}

function getAllowedTemplateModules(): array
{
    return ['organization', 'process_mapping', 'data_architecture', 'integration', 'backlog', 'reports'];
}

function validateTemplateManifest(array $template, string $module, string $key): array
{
    $payload = $template['payload'] ?? null;
    if (!is_array($payload)) {
        throw new RuntimeException("Template {$module}/{$key} is missing payload.");
    }

    return [
        'key' => $key,
        'module' => $module,
        'name' => trim((string)($template['name'] ?? $key)),
        'description' => trim((string)($template['description'] ?? '')),
        'icon' => trim((string)($template['icon'] ?? 'fa-layer-group')),
        'version' => trim((string)($template['version'] ?? '1.0.0')),
        'author' => trim((string)($template['author'] ?? 'Community')),
        'tags' => array_values(array_filter(array_map('strval', (array)($template['tags'] ?? [])))),
        'payload' => $payload,
    ];
}

function loadTemplateManifest(string $module, string $key): array
{
    $module = normalizeTemplateModule($module);
    $key = trim($key);
    if (!in_array($module, getAllowedTemplateModules(), true)) {
        throw new RuntimeException('Unsupported template module.');
    }
    if ($key === '' || !preg_match('/^[a-z0-9_-]+$/', $key)) {
        throw new RuntimeException('Invalid template key.');
    }

    $path = getTemplateRoot() . '/' . $module . '/' . $key . '/template.json';
    if (!is_file($path)) {
        throw new RuntimeException("Template {$module}/{$key} not found.");
    }

    $raw = file_get_contents($path);
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Template {$module}/{$key} contains invalid JSON.");
    }

    return validateTemplateManifest($decoded, $module, $key);
}

function listTemplates(?string $module = null): array
{
    $modules = $module !== null && $module !== ''
        ? [normalizeTemplateModule($module)]
        : getAllowedTemplateModules();

    $result = [];
    foreach ($modules as $moduleKey) {
        if (!in_array($moduleKey, getAllowedTemplateModules(), true)) {
            continue;
        }

        $moduleDir = getTemplateRoot() . '/' . $moduleKey;
        if (!is_dir($moduleDir)) {
            continue;
        }

        foreach (glob($moduleDir . '/*', GLOB_ONLYDIR) ?: [] as $templateDir) {
            $key = basename($templateDir);
            try {
                $manifest = loadTemplateManifest($moduleKey, $key);
                unset($manifest['payload']);
                $result[] = $manifest;
            } catch (Throwable $ignored) {
            }
        }
    }

    usort($result, static function (array $a, array $b): int {
        return strcmp($a['name'], $b['name']);
    });

    return $result;
}
