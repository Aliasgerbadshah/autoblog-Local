<?php
/**
 * Website Blog — Live sitemap.xml
 * Served at https://colorfiind.com/blog/sitemap.xml via blog/.htaccess.
 * Always generated fresh from the database, so Google sees every published
 * post even if the static sitemap.xml file write ever fails.
 */
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

try {
    require_once __DIR__ . '/includes/publisher.php';
    $publisher = new WebsitePublisher();
    // Publish anything due first so the sitemap never lags behind scheduling.
    try { $publisher->publishScheduled(); } catch (Throwable $e) { /* ignore */ }
    echo $publisher->buildSitemap();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
}
