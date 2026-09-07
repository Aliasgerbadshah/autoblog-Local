<?php
/**
 * Hostinger / wget clock for Auto Blog.
 * Set ONCE in hPanel Cron Jobs (every 5 minutes). Do not click Run Auto Cron daily.
 *
 * Cron command (every 5 minutes):
 * wget -q -O - "https://apps.colorfiind.com/cron/tick.php?key=YOUR_SECRET"
 *
 * The secret is in data/cron_secret.txt (created automatically).
 */
date_default_timezone_set('Asia/Kolkata');
@set_time_limit(180);
@ini_set('max_execution_time', '180');

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/autoblog_engine.php';
require_once __DIR__ . '/../includes/ai_provider.php';
require_once __DIR__ . '/../includes/google_keyword_planner.php';
require_once __DIR__ . '/../includes/keyword_flow.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/auto_daily.php';

$cli = (php_sapi_name() === 'cli');
$key = (string)($_GET['key'] ?? $_POST['key'] ?? '');
$secret = getAutoBlogCronSecret();
if (!$cli && !hash_equals($secret, $key)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden. Use the Auto Blog tick URL from the dashboard (includes the secret key).\n";
    exit;
}

// ---- Cron debugging helpers ----
// When the secret key is correct, print any PHP error as readable text instead of
// a blank HTTP 500, so Hostinger cron problems can be seen in the browser /
// the dashboard "Verify cron URLs" check.
if ($cli || hash_equals($secret, $key)) {
    @ini_set('display_errors', '1');
    error_reporting(E_ALL);
    set_exception_handler(function (Throwable $t) {
        if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        echo '[AutoBlog Cron ERROR] ' . $t->getMessage() . ' @ ' . $t->getFile() . ':' . $t->getLine() . "\n";
        exit(1);
    });
    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8');
            echo "\n[AutoBlog Cron FATAL] " . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line'] . "\n";
        }
    });
}

$res = processAutoBlogCampaigns(1, 3);
if (function_exists('recordAutoCronRun')) {
    recordAutoCronRun($cli ? 'cli_tick' : 'hostinger_tick', $res);
}
header('Content-Type: text/plain; charset=utf-8');
echo ($res['message'] ?? json_encode($res)) . "\n";
