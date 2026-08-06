<?php
/**
 * AutoBlog SaaS - Scheduler Cron Job
 * Processes scheduled_queue and publishes articles that are due.
 * Uses pre-generated HTML from campaign_items when available.
 * 
 * Hostinger cron command:
 * /usr/local/bin/php /home/u783910899/domains/colorfiind.com/public_html/sub_apps/cron/scheduler.php
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

echo "[Scheduler] Starting at " . date('Y-m-d H:i:s') . "\n";
echo "[Scheduler] Script: " . __FILE__ . "\n";
echo "[Scheduler] App root: " . dirname(__DIR__) . "\n";
echo "[Scheduler] Output dir: " . OUTPUT_DIR . "\n";

$db = getDB();
$now = nowString();

$stmt = $db->prepare("SELECT * FROM scheduled_queue WHERE status = 'Scheduled' AND scheduled_time <= ? ORDER BY scheduled_time ASC");
$stmt->execute([$now]);
$dueItems = $stmt->fetchAll();

echo "[Scheduler] Found " . count($dueItems) . " items due for publishing.\n";

foreach ($dueItems as $item) {
    $userId = $item['user_id'];
    $slotNumber = $item['slot_number'];
    $keyword = $item['keyword'];
    $category = $item['category'];
    $targetLink = $item['target_link'];
    $targetAnchor = $item['target_anchor'];
    $campaignItemId = $item['campaign_item_id'] ?? null;

    echo "[Scheduler] Processing item #{$item['id']}: \"{$item['topic_title']}\" (platform: {$item['target_platform']})\n";

    try {
        // Check for pre-generated HTML from campaign_items
        // Priority 1: Match by campaign_item_id (most reliable)
        // Priority 2: Match by user_id + title (fallback)
        $existingItem = null;

        if (!empty($campaignItemId)) {
            $stmt = $db->prepare("SELECT * FROM campaign_items WHERE id = ? AND article_status IN ('Final Article Approved', 'HTML Ready') AND html_path IS NOT NULL AND html_path != ''");
            $stmt->execute([$campaignItemId]);
            $existingItem = $stmt->fetch();
            if ($existingItem) {
                echo "[Scheduler] Found HTML by campaign_item_id = {$campaignItemId}\n";
            }
        }

        if (!$existingItem) {
            // Fallback: match by user_id + title
            $stmt = $db->prepare("SELECT ci.* FROM campaign_items ci JOIN campaigns c ON c.id = ci.campaign_id WHERE c.user_id = ? AND ci.title = ? AND ci.article_status IN ('Final Article Approved', 'HTML Ready') AND ci.html_path IS NOT NULL AND ci.html_path != '' ORDER BY ci.id DESC LIMIT 1");
            $stmt->execute([$userId, $item['topic_title']]);
            $existingItem = $stmt->fetch();
            if ($existingItem) {
                echo "[Scheduler] Found HTML by title match: \"{$item['topic_title']}\"\n";
            }
        }

        if ($existingItem && !empty($existingItem['html_path'])) {
            // Build all possible file paths to find the HTML
            $htmlRelativePath = ltrim($existingItem['html_path'], '/');
            $appRoot = dirname(__DIR__);
            
            $htmlPaths = [
                $appRoot . '/' . $htmlRelativePath,                    // Standard: app_root/published_posts/demo/slug.html
                OUTPUT_DIR . '/demo/' . basename($existingItem['html_path']),  // Direct output dir
                $appRoot . '/public_html/' . $htmlRelativePath,         // Hostinger subdomain mapping
                $appRoot . '/sub_apps/' . $htmlRelativePath,            // Alternative Hostinger path
                realpath($appRoot) . '/' . $htmlRelativePath,           // Resolved path
            ];
            
            // Also check if html_path stored is an absolute file path already
            if (file_exists($existingItem['html_path'])) {
                $htmlPaths[] = $existingItem['html_path'];
            }
            
            echo "[Scheduler] Looking for HTML: {$existingItem['html_path']}\n";
            
            $htmlFilePath = null;
            foreach ($htmlPaths as $p) {
                echo "[Scheduler]   Checking: $p ... ";
                if (file_exists($p)) {
                    $htmlFilePath = $p;
                    echo "FOUND\n";
                    break;
                }
                echo "not found\n";
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
                echo "[Scheduler] HTML file not found at any path. Regenerating content...\n";
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
            echo "[Scheduler] Publishing to Blogger. Blog ID: {$vault['blogger_blog_id']}\n";
            
            // Always try refresh token if available
            if (!empty($vault['refresh_token']) && !empty($vault['client_id']) && !empty($vault['client_secret'])) {
                echo "[Scheduler] Attempting OAuth token refresh...\n";
                $rfRes = BloggerOAuthHelper::refreshAccessToken($vault['client_id'], $vault['client_secret'], $vault['refresh_token']);
                if ($rfRes['success'] && !empty($rfRes['access_token'])) {
                    $vault['access_token'] = $rfRes['access_token'];
                    echo "[Scheduler] Token refreshed successfully.\n";
                    // Update stored access token in vault for future use
                    $stmt = $db->prepare('SELECT id, credential_data FROM user_credentials_vault WHERE user_id = ? AND service_name = ? ORDER BY updated_at DESC LIMIT 1');
                    $stmt->execute([$userId, 'blogger_api']);
                    $vaultRow = $stmt->fetch();
                    if ($vaultRow) {
                        $existingData = json_decode($vaultRow['credential_data'], true) ?: [];
                        $existingData['access_token'] = $rfRes['access_token'];
                        $stmt = $db->prepare('UPDATE user_credentials_vault SET credential_data = ?, updated_at = ? WHERE id = ?');
                        $stmt->execute([json_encode($existingData), nowString(), $vaultRow['id']]);
                        echo "[Scheduler] Updated stored access token in vault.\n";
                    }
                } else {
                    echo "[Scheduler] Token refresh failed: " . ($rfRes['error'] ?? 'Unknown') . ". Using existing token.\n";
                }
            } else {
                echo "[Scheduler] No refresh credentials saved. Using existing access token.\n";
            }
            
            $result = Publisher::publishBlogger($userId, $vault['blogger_blog_id'], $vault['access_token'], $art['title'], $art['content'], $vault['client_id'] ?? null, $vault['client_secret'] ?? null, $vault['refresh_token'] ?? null);
            if (!$result['success']) throw new RuntimeException($result['error'] ?? 'Blogger publishing failed');
            echo "[Scheduler] Blogger publish successful! URL: " . ($result['url'] ?? '') . "\n";
        } elseif ($platform === 'wordpress') {
            $vault = SecurityVault::getApiCredentials($userId, 'wordpress_api');
            $result = Publisher::publishWordpress($userId, $vault['wp_site_url'] ?? '', $vault['wp_username'] ?? '', $vault['wp_app_password'] ?? '', $art['title'], $art['content']);
            if (!$result['success']) throw new RuntimeException($result['error'] ?? 'WordPress publishing failed');
            echo "[Scheduler] WordPress publish successful!\n";
        } else {
            Publisher::publishLocal($userId, $art['title'], $art['slug'] ?? slugify($art['title']), $art['content'], $category, $keyword, $art['featured_image'] ?? '');
            echo "[Scheduler] Local publish successful!\n";
        }

        $stmt2 = $db->prepare("UPDATE scheduled_queue SET status = 'Published' WHERE id = ?");
        $stmt2->execute([$item['id']]);
        echo "[Scheduler] ✅ Published: {$item['topic_title']} to $platform\n";

    } catch (Exception $exc) {
        $stmt2 = $db->prepare("UPDATE scheduled_queue SET status = 'Failed', error_message = ? WHERE id = ?");
        $stmt2->execute([strval($exc), $item['id']]);
        echo "[Scheduler] ❌ Error for item {$item['id']}: {$exc->getMessage()}\n";
    }
}

echo "[Scheduler] Completed at " . date('Y-m-d H:i:s') . ". Processed " . count($dueItems) . " items.\n";
