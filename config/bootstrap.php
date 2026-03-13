<?php
// config/bootstrap.php

// Optional composer autoload (if dependencies are installed)
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Optional .env for local/server overrides
if (class_exists(\Dotenv\Dotenv::class)) {
    $envPath = __DIR__ . '/../';
    if (file_exists($envPath . '.env')) {
        $dotenv = \Dotenv\Dotenv::createImmutable($envPath);
        $dotenv->safeLoad();
    }
}

// Ensure errors are logged but not displayed as HTML to break JSON responses
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://accounts.google.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: https: blob:; connect-src 'self' https://oauth2.googleapis.com https://api.groq.com http://127.0.0.1:* http://localhost:*; frame-ancestors 'self'; object-src 'none'; base-uri 'self';");

$translations = require_once __DIR__ . '/translations.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/mail_helper.php';
require_once __DIR__ . '/template_helper.php';
require_once __DIR__ . '/database.php';

// Set language (priority VI as requested, defaulting to bilingual)
if (isset($_GET['lang'])) {
    $requestedLang = $_GET['lang'];
    if (in_array($requestedLang, ['vi', 'en', 'bilingual'])) {
        $_SESSION['lang'] = $requestedLang;
    }
    // Redirect to remove lang from URL for cleaner state
    $cleanUrl = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: $cleanUrl");
    exit;
}
$lang = $_SESSION['lang'] ?? 'bilingual';

function __($key) {
    global $translations, $lang;
    if (!isset($translations[$key])) return $key;
    
    $vi = $translations[$key]['vi'];
    $en = $translations[$key]['en'];
    
    if ($lang === 'vi') return $vi;
    if ($lang === 'en') return $en;
    // Bilingual mode: Vietnamese / English
    return "$vi / $en";
}
