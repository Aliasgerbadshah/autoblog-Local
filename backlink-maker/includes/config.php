<?php
/**
 * AutoBacklink - Configuration
 * Standalone backlink software. Auto-detects base URL (works on any subdomain).
 */

// Base URL — auto-detect from the domain being used (subdomain-ready)
if (!defined('APP_BASE_URL')) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Strip port for preview hosts (keep it for localhost testing)
    $appBase = $proto . '://' . $host;
    define('APP_BASE_URL', rtrim($appBase, '/'));
}

// Paths (relative to this file so the app works in any folder/subdomain)
define('APP_ROOT', dirname(__DIR__));
define('DB_PATH', APP_ROOT . '/backlink_maker.db');
define('PACKAGES_DIR', APP_ROOT . '/packages');
define('LOG_FILE', APP_ROOT . '/data_maker.log');

// Sandbox detection: php-wasm preview (no curl ext) vs real server (Hostinger)
define('SANDBOX_MODE', !function_exists('curl_init'));

// Limits
define('MAX_DAILY_JOBS', 10);          // hard cap per run (Hostinger cron friendly)
define('DEFAULT_DAILY_COUNT', 5);      // targets per day
define('DEFAULT_DAILY_TIME', '06:00'); // Asia/Kolkata
define('DEFAULT_MIN_INTERVAL_DAYS', 7);
define('LINK_RECHECK_DAYS', 7);        // weekly link health re-check

date_default_timezone_set('Asia/Kolkata');
