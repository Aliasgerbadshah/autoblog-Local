<?php
/**
 * Hostinger / wget clock for Auto Blog.
 * Set ONCE in hPanel Cron Jobs (every 5 minutes). Do not click Run Auto Cron daily.
 *
 *   */5 * * * * wget -q -O - "https://apps.colorfiind.com/cron/tick.php?key=YOUR_SECRET"
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

$res = processAutoBlogCampaigns(1, 3);
if (function_exists('recordAutoCronRun')) {
    recordAutoCronRun($cli ? 'cli_tick' : 'hostinger_tick', $res);
}
header('Content-Type: text/plain; charset=utf-8');
echo ($res['message'] ?? json_encode($res)) . "\n";
