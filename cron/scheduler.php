<?php
/**
 * AutoBlog SaaS - Scheduler Cron Job
 * Processes scheduled_queue and publishes articles that are due.
 * Uses pre-generated HTML from campaign_items when available.
 */

if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cgi-fcgi' && isset($_SERVER['HTTP_HOST']) && !isset($_GET['cron_key'])) {
    die('This script can only be run from the command line or cron.');
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/autoblog_engine.php';
require_once __DIR__ . '/../includes/anti_ai_sanitizer.php';
require_once __DIR__ . '/../includes/ai_provider.php';

$db = getDB();
$now = nowString();

$stmt = $db->prepare("SELECT * FROM scheduled_queue WHERE status = 'Scheduled' AND scheduled_time <= ? ORDER BY scheduled_time ASC");
$stmt->execute([$now]);
$dueItems = $stmt->fetchAll();

foreach ($dueItems as $item) {
    $userId = $item['user_id'];
    $slotNumber = $item['slot_number'];
    $keyword = $item['keyword'];
    $category = $item['category'];
    $targetLink = $item['target_link'];
    $targetAnchor = $item['target_anchor'];

    try {
        // Check for pre-generated HTML from campaign_items
        $stmt = $db->prepare("SELECT ci.* FROM campaign_items ci JOIN campaigns c ON c.id = ci.campaign_id WHERE c.user_id = ? AND ci.title = ? AND ci.article_status = 'Final Article Approved' AND ci.html_path IS NOT NULL AND ci.html_path != '' ORDER BY ci.id DESC LIMIT 1");
        $stmt->execute([$userId, $item['topic_title']]);
        $existingItem = $stmt->fetch();

        if ($existingItem && !empty($existingItem['html_path'])) {
            // Try multiple path patterns to find the HTML file
            $htmlPaths = [
                dirname(__DIR__) . ltrim($existingItem['html_path'], '/'),
                dirname(__DIR__) . '/public_html' . ltrim($existingItem['html_path'], '/'),
                OUTPUT_DIR . '/../' . ltrim($existingItem['html_path'], '/'),
                __DIR__ . '/../' . ltrim($existingItem['html_path'], '/'),
            ];
            $htmlFilePath = null;
            foreach ($htmlPaths as $p) {
                if (file_exists($p)) { $htmlFilePath = $p; break; }
            }

            if ($htmlFilePath) {
                $articleContent = file_get_contents($htmlFilePath);
                // Extract just the <article> content for Blogger (not the full page)
                if (preg_match('#<article[^>]*>(.*?)</article>#is', $articleContent, $m)) {
                    $bloggerContent = $m[1];
                } else {
                    $bloggerContent = $articleContent;
                }
                $art = ['title' => $existingItem['title'], 'slug' => slugify($existingItem['title']), 'content' => $bloggerContent, 'keyword' => $existingItem['primary_keyword'], 'category' => $category, 'featured_image' => ''];
                echo "[Scheduler] Using pre-generated HTML for: {$item['topic_title']}\n";
            } else {
                echo "[Scheduler] HTML file not found at: {$existingItem['html_path']}, regenerating...\n";
                $existingItem = null;
            }
        }

        if (!$existingItem) {
            // Fallback: generate fresh content
            $chatVault = SecurityVault::getApiCredentials($userId, 'chat_api');
            $imageVault = SecurityVault::getApiCredentials($userId, 'image_api');

            if (empty($chatVault['api_key'])) throw new RuntimeException('Chat API credentials are required.');

            $art = ContentGenerator::generateHumanArticle1000Words($keyword, $category, $targetLink, $targetAnchor, $userId, $slotNumber);
            $prompt = "Write only researched HTML for an 1800 to 2200 word human-reviewed blog about $keyword. Use the approved internal link target $targetLink with natural anchor text $targetAnchor. Include correct headings, FAQ, schema only when supported, relevant external citations, and varied image alt text. Do NOT include html/head/body tags.";
            $aiResult = AIProviderClient::chat($chatVault, $prompt);
            if (!$aiResult['success']) throw new RuntimeException($aiResult['error'] ?? 'Chat API failed');
            $art['content'] = AntiAiSanitizer::sanitizeText($aiResult['content']);

            if (!empty($imageVault['api_key'])) {
                $imageResult = AIProviderClient::image($imageVault, "Editorial image for $keyword. No text or logos.");
                if (!empty($imageResult['success']) && !empty($imageResult['url'])) {
                    $art['featured_image'] = $imageResult['url'];
                }
            }
        }

        $platform = $item['target_platform'] ?? 'local';
        if ($platform === 'blogger') {
            $vault = SecurityVault::getApiCredentials($userId, 'blogger_api');
            if (empty($vault['blogger_blog_id']) || empty($vault['access_token'])) {
                throw new RuntimeException('Blogger Blog ID and Access Token are required. Save them in API Vault.');
            }
            $result = Publisher::publishBlogger($userId, $vault['blogger_blog_id'], $vault['access_token'], $art['title'], $art['content'], $vault['client_id'] ?? null, $vault['client_secret'] ?? null, $vault['refresh_token'] ?? null);
            if (!$result['success']) throw new RuntimeException($result['error'] ?? 'Blogger publishing failed');
        } elseif ($platform === 'wordpress') {
            $vault = SecurityVault::getApiCredentials($userId, 'wordpress_api');
            $result = Publisher::publishWordpress($userId, $vault['wp_site_url'] ?? '', $vault['wp_username'] ?? '', $vault['wp_app_password'] ?? '', $art['title'], $art['content']);
            if (!$result['success']) throw new RuntimeException($result['error'] ?? 'WordPress publishing failed');
        } else {
            Publisher::publishLocal($userId, $art['title'], $art['slug'] ?? slugify($art['title']), $art['content'], $category, $keyword, $art['featured_image'] ?? '');
        }

        $stmt2 = $db->prepare("UPDATE scheduled_queue SET status = 'Published' WHERE id = ?");
        $stmt2->execute([$item['id']]);
        echo "[Scheduler] Published: {$item['topic_title']} to $platform\n";

    } catch (Exception $exc) {
        $stmt2 = $db->prepare("UPDATE scheduled_queue SET status = 'Failed', error_message = ? WHERE id = ?");
        $stmt2->execute([strval($exc), $item['id']]);
        echo "[Scheduler Error] Item {$item['id']}: {$exc->getMessage()}\n";
    }
}

echo "[Scheduler] Processed " . count($dueItems) . " items at $now\n";
