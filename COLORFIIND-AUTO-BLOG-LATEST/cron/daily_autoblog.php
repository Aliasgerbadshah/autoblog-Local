<?php
/**
 * AutoBlog daily automation.
 * Prefer cron/tick.php wget URL (set once in Hostinger).
 * CLI: php cron/daily_autoblog.php
 */
date_default_timezone_set('Asia/Kolkata');

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
if (!$cli) {
    $key = (string)($_GET['key'] ?? $_POST['key'] ?? '');
    $secret = function_exists('getAutoBlogCronSecret') ? getAutoBlogCronSecret() : '';
    if ($secret === '' || !hash_equals($secret, $key)) {
        http_response_code(403);
        die('Forbidden. Use the Auto Blog tick URL from the dashboard.');
    }
}

@set_time_limit(180);
$res = processAutoBlogCampaigns(1, 3);
if (function_exists('recordAutoCronRun')) {
    recordAutoCronRun($cli ? 'hostinger_cron' : 'web_cron', $res);
}
echo '[Daily AutoBlog] ' . ($res['message'] ?? '') . "\n";
if (!empty($res['errors'])) {
    echo '[Daily AutoBlog] errors: ' . implode(' | ', $res['errors']) . "\n";
}
