<?php
// AutoBacklink - Per-30-min cron (recommended)
// Hostinger:  */30 * * * *  php /home/USERNAME/public_html/backlink-maker/cron/backlink-30min.php
//
// With this one-line cron, each website's own timer (Site Panels: blog post
// time, community comment time) fires at the exact hour you choose, and
// "2-3 comments/day" pacing stays bot-safe. If you instead keep the old
// daily 06:00 cron (daily.php), pacing still works but timers are approximate.
//
// Also callable from the dashboard "Run Now" button (web POST).
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cgi-fcgi' && php_sapi_name() !== 'fpm-fcgi') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        die('Method not allowed. Use POST to trigger a run.');
    }
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/content_engine.php';
require_once __DIR__ . '/../includes/maker.php';

echo '[' . date('Y-m-d H:i:s') . "] AutoBacklink 30-min run started\n";
$summary = BacklinkMaker::runDaily(false);
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
echo "Done.\n";
