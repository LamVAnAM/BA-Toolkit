<?php
require_once __DIR__ . '/../config/bootstrap.php';

try {
    $settings = loadSettingsMap($pdo, null);
    jsonResponse([
        'google_client_id' => (string)($settings['google_client_id'] ?? ''),
        'app_name' => 'BA Toolkit',
        'email_auth_enabled' => trim((string)($settings['smtp_host'] ?? '')) !== '' && trim((string)($settings['smtp_from_email'] ?? '')) !== '',
        'footer_copyright_text' => (string)($settings['footer_copyright_text'] ?? 'Powered by vannamdigital'),
        'footer_brand_name' => (string)($settings['footer_brand_name'] ?? 'vannamdigital'),
        'footer_contact_email' => (string)($settings['footer_contact_email'] ?? 'namxp2@gmail.com')
    ]);
} catch (Throwable $e) {
    jsonError($e->getMessage(), 500);
}
