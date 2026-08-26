<?php
/**
 * AutoBlog SaaS - Scheduler Cron Job
 * 
 * */5 * * * * php /home/USERNAME/public_html/cron/scheduler.php
 *
 * Processes scheduled_queue and publishes articles that are due.
 * Uses pre-generated HTML from campaign_items when available.
 * Uses Blogger API KEY (not OAuth) for publishing.
 */

// SAPI guard removed — Hostinger runs PHP as CGI/fPM, not CLI.
// Allow execution from both cron and web (for "Run Cron Now" button).
// Optionally restrict to POST requests from authenticated users when called via web.
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cgi-fcgi' && php_sapi_name() !== 'fpm-fcgi') {
    // Running via web — verify it's a POST request (from "Run Cron Now" button)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Method not allowed. Use POST to trigger scheduler.');
    }
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/autoblog_engine.php';
require_once __DIR__ . '/../includes/anti_ai_sanitizer.php';
require_once __DIR__ . '/../includes/ai_provider.php';

$db = getDB();
$now = nowString();

$log = function($msg) {
    $line = "[" . date('Y-m-d H:i:s') . "][Scheduler] $msg\n";
    echo $line;
    error_log($line);
};

$log("Starting scheduler run at $now");

$stmt = $db->prepare("SELECT * FROM scheduled_queue WHERE status = 'Scheduled' AND scheduled_time <= ? ORDER BY scheduled_time ASC");
$stmt->execute([$now]);
$dueItems = $stmt->fetchAll();

$log("Found " . count($dueItems) . " items due for publishing");

foreach ($dueItems as $item) {
    $userId = $item['user_id'];
    $slotNumber = $item['slot_number'];
    $keyword = $item['keyword'];
    $category = $item['category'];
    $targetLink = $item['target_link'];
    $targetAnchor = $item['target_anchor'];
    $topicTitle = $item['topic_title'];
    $platform = $item['target_platform'] ?? 'local';

    $log("Processing item ID {$item['id']}: \"$topicTitle\" → $platform");

    try {
        // Check for pre-generated HTML
        $stmt = $db->prepare("SELECT ci.* FROM campaign_items ci JOIN campaigns c ON c.id = ci.campaign_id WHERE c.user_id = ? AND ci.title = ? AND ci.article_status = 'Final Article Approved' AND ci.html_path IS NOT NULL AND ci.html_path != '' ORDER BY ci.id DESC LIMIT 1");
        $stmt->execute([$userId, $topicTitle]);
        $existingItem = $stmt->fetch();

        if ($existingItem && !empty($existingItem['html_path'])) {
            $log("Found pre-generated HTML path: {$existingItem['html_path']}");
            
            // Try multiple path patterns to find the HTML file
            $htmlFilePath = null;
            $pathPatterns = [
                dirname(__DIR__) . ltrim($existingItem['html_path'], '/'),
                dirname(__DIR__) . '/../' . ltrim($existingItem['html_path'], '/'),
                OUTPUT_DIR . '/../' . ltrim($existingItem['html_path'], '/'),
                OUTPUT_DIR . '/demo/' . basename($existingItem['html_path']),
                dirname(__DIR__) . '/public_html' . ltrim($existingItem['html_path'], '/'),
                dirname(__DIR__) . '/published_posts/demo/' . basename($existingItem['html_path']),
            ];
            
            foreach ($pathPatterns as $p) {
                $log("Checking path: $p");
                if (file_exists($p)) {
                    $htmlFilePath = $p;
                    $log("Found HTML file at: $p");
                    break;
                }
            }

            if ($htmlFilePath) {
                $fullHtml = file_get_contents($htmlFilePath);
                
                // Extract only the <article> content for Blogger (not full HTML page)
                $articleContent = $fullHtml;
                if (preg_match('#<article[^>]*>(.*?)</article>#is', $fullHtml, $artMatch)) {
                    $articleContent = trim($artMatch[1]);
                    $log("Extracted <article> content (" . strlen($articleContent) . " chars)");
                } else {
                    $log("No <article> tag found, sending full HTML (" . strlen($fullHtml) . " chars)");
                }
                
                $art = ['title' => $existingItem['title'], 'slug' => slugify($existingItem['title']), 'content' => $articleContent, 'keyword' => $existingItem['primary_keyword'], 'category' => $category, 'featured_image' => ''];
                $log("Using pre-generated HTML for: $topicTitle");
            } else {
                $log("HTML file not found at any path, will generate fresh content");
                $existingItem = null;
            }
        }

        if (!$existingItem) {
            $chatVault = SecurityVault::getApiCredentials($userId, 'chat_api');
            $imageVault = SecurityVault::getApiCredentials($userId, 'image_api');

            if (empty($chatVault['api_key'])) throw new RuntimeException('Chat API credentials are required.');

            $art = ContentGenerator::generateHumanArticle1000Words($keyword, $category, $targetLink, $targetAnchor, $userId, $slotNumber);
            $prompt = "Write only researched HTML for an 1800 to 2200 word human-reviewed blog about $keyword. Use the approved internal link target $targetLink with natural anchor text $targetAnchor. Include correct headings, FAQ, schema only when supported, relevant external citations, and varied image alt text.";
            $aiResult = AIProviderClient::chat($chatVault, $prompt);
            if (!$aiResult['success']) throw new RuntimeException($aiResult['error'] ?? 'Chat API failed');
            $art['content'] = AntiAiSanitizer::sanitizeText($aiResult['content']);

            if (!empty($imageVault['api_key'])) {
                $imageResult = AIProviderClient::image($imageVault, "Relevant editorial image for $keyword; no text or logos.");
                if (!empty($imageResult['success']) && !empty($imageResult['url'])) {
                    $art['featured_image'] = $imageResult['url'];
                    $art['content'] = '<figure><img src="' . $imageResult['url'] . '" alt="Relevant image for ' . escapeHtml($keyword) . '" loading="eager"></figure>' . $art['content'];
                }
            }
        }

        if ($platform === 'blogger') {
            $vault = SecurityVault::getApiCredentials($userId, 'blogger_api');
            $blogId = $vault['blogger_blog_id'] ?? '';
            $clientId = $vault['client_id'] ?? '';
            $clientSecret = $vault['client_secret'] ?? '';
            $refreshToken = $vault['refresh_token'] ?? '';
            $log("Publishing to Blogger - Blog ID: $blogId, OAuth: " . (empty($refreshToken) ? 'NONE' : 'configured'));
            
            if (empty($blogId)) {
                throw new RuntimeException('Blogger Blog ID is required in the vault.');
            }
            if (empty($refreshToken)) {
                throw new RuntimeException('Blogger OAuth Refresh Token is required in the vault for publishing.');
            }
            
            $result = Publisher::publishBlogger($userId, $blogId, $art['title'], $art['content'], $clientId, $clientSecret, $refreshToken);
            if (!$result['success']) throw new RuntimeException($result['error'] ?? 'Blogger publishing failed');
            $log("Blogger publish success: " . ($result['url'] ?? 'no URL returned'));
        } elseif ($platform === 'wordpress') {
            $vault = SecurityVault::getApiCredentials($userId, 'wordpress_api');
            $result = Publisher::publishWordpress($userId, $vault['wp_site_url'] ?? '', $vault['wp_username'] ?? '', $vault['wp_app_password'] ?? '', $art['title'], $art['content']);
            if (!$result['success']) throw new RuntimeException($result['error'] ?? 'WordPress publishing failed');
            $log("WordPress publish success");
        } elseif ($platform === 'website') {
            if (!function_exists('publishToWebsiteBlog')) {
                $idx = dirname(__DIR__) . '/index.php';
            }
            $pubFile = dirname(__DIR__) . '/blog/includes/publisher.php';
            $alt = dirname(__DIR__, 2) . '/blog/includes/publisher.php';
            if (file_exists($alt)) require_once $alt;
            elseif (file_exists($pubFile)) require_once $pubFile;
            else throw new RuntimeException('Website publisher not found. Move blog/ to public_html/blog/.');
            $wpub = new WebsitePublisher();
            $thumbUrl = '';
            if (preg_match('#<img[^>]+src=["\']([^"\']+)["\']#i', $art['content'], $mImg)) $thumbUrl = $mImg[1];
            $result = $wpub->publish([
                'title' => $art['title'],
                'slug' => slugify($art['title']),
                'content_html' => $art['content'],
                'category' => $category,
                'tags' => [$keyword],
                'thumbnail_url' => $thumbUrl,
                'author' => 'ColorFiind Team',
                'meta_description' => substr(strip_tags($art['content']), 0, 160),
                'meta_keywords' => $keyword,
            ]);
            if (empty($result['success'])) throw new RuntimeException($result['error'] ?? 'Website blog publishing failed');
            $log("Website blog publish success: " . ($result['url'] ?? ''));
        } else {
            Publisher::publishLocal($userId, $art['title'], $art['slug'] ?? slugify($art['title']), $art['content'], $category, $keyword, $art['featured_image'] ?? '');
            $log("Local publish success");
        }

        $stmt2 = $db->prepare("UPDATE scheduled_queue SET status = 'Published' WHERE id = ?");
        $stmt2->execute([$item['id']]);
        $log("Published: $topicTitle to $platform");

    } catch (Exception $exc) {
        $retries = intval($item['retry_count'] ?? 0) + 1;
        if ($retries < 8) {
            $stmt2 = $db->prepare("UPDATE scheduled_queue SET status = 'Scheduled', retry_count = ?, error_message = ? WHERE id = ?");
            $stmt2->execute([$retries, strval($exc), $item['id']]);
            $log("RETRY {$retries}/8 item {$item['id']}: " . $exc->getMessage());
        } else {
            $stmt2 = $db->prepare("UPDATE scheduled_queue SET status = 'Failed', retry_count = ?, error_message = ? WHERE id = ?");
            $stmt2->execute([$retries, strval($exc), $item['id']]);
            $log("ERROR item {$item['id']}: " . $exc->getMessage());
        }
    }
}

$log("Scheduler run complete. Processed " . count($dueItems) . " items");
