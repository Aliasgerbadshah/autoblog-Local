<?php
/**
 * AutoBlog daily automation cron
 * */5 * * * * php /home/USERNAME/public_html/sub_apps/cron/daily_autoblog.php
 */
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cgi-fcgi' && php_sapi_name() !== 'fpm-fcgi') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        die('Method not allowed. Use POST to trigger daily autoblog.');
    }
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/autoblog_engine.php';
require_once __DIR__ . '/../includes/ai_provider.php';
require_once __DIR__ . '/../includes/google_keyword_planner.php';
require_once __DIR__ . '/../includes/auto_daily.php';

@set_time_limit(180);
$res = processAutoBlogCampaigns(3);
echo '[Daily AutoBlog] published=' . $res['published'] . ' html=' . $res['html'] . ' processed=' . $res['processed'] . "\n";
if (!empty($res['errors'])) {
    echo '[Daily AutoBlog] errors: ' . implode(' | ', $res['errors']) . "\n";
}
