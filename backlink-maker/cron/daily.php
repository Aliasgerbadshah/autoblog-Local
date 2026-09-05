<?php
/**
 * AutoBacklink - Daily cron
 * Hostinger: 0 6 * * * php /home/USERNAME/public_html/backlink-maker/cron/daily.php
 * (adjust the hour to your settings.daily_time if you change it)
 *
 * Also callable from the dashboard "Run Now" button (web POST).
 */
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cgi-fcgi' && php_sapi_name() !== 'fpm-fcgi') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        die('Method not allowed. Use POST to trigger the daily run.');
    }
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/content_engine.php';
require_once __DIR__ . '/../includes/maker.php';

echo '[' . date('Y-m-d H:i:s') . "] AutoBacklink daily run started\n";
$summary = BacklinkMaker::runDaily();
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
echo "Done.\n";
