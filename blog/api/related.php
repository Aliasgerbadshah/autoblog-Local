<?php
/**
 * API: Related Posts
 * Returns JSON list of related posts for the article page.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$category = $_GET['category'] ?? '';
$exclude = $_GET['exclude'] ?? '';

$cfg = require __DIR__ . '/../config.php';
$dbFile = $cfg['autoblog_root'] . '/includes/database.php';

if (!file_exists($dbFile)) {
    echo json_encode(['posts' => []]);
    exit;
}

try {
    require_once $dbFile;
    $db = Database::getInstance();

    $limit = $cfg['related_posts_count'] ?? 3;

    if (!empty($category)) {
        $posts = $db->fetchAll(
            "SELECT title, url, published_date, reading_time, thumbnail_url FROM website_blog_posts 
             WHERE status = 'published' AND category = ? AND slug != ? 
             ORDER BY published_date DESC LIMIT ?",
            [$category, $exclude, $limit]
        );
    } else {
        $posts = $db->fetchAll(
            "SELECT title, url, published_date, reading_time, thumbnail_url FROM website_blog_posts 
             WHERE status = 'published' AND slug != ? 
             ORDER BY published_date DESC LIMIT ?",
            [$exclude, $limit]
        );
    }

    foreach ($posts as &$p) {
        $p['published_date'] = date('F j, Y', strtotime($p['published_date']));
    }

    echo json_encode(['posts' => $posts ?: []]);
} catch (Throwable $e) {
    echo json_encode(['posts' => []]);
}
