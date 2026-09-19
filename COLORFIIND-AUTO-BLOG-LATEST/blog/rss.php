<?php
/**
 * Website Blog — Live rss.xml
 * Served at https://colorfiind.com/blog/rss.xml via blog/.htaccess.
 * Always generated fresh from the database (latest 50 published posts).
 */
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/rss+xml; charset=utf-8');

try {
    require_once __DIR__ . '/includes/publisher.php';
    $publisher = new WebsitePublisher();
    echo $publisher->buildRss();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>ColorFiind Blog</title></channel></rss>';
}
