<?php
/**
 * AutoBlog SaaS - Social Media Engine
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

class SocialCreativeEngine {
    private static $INSTAGRAM_TEMPLATES = [
        "https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=1080&h=1350&q=80",
        "https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=1080&h=1350&q=80",
        "https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=1080&h=1350&q=80"
    ];

    private static $PINTEREST_TEMPLATES = [
        "https://images.unsplash.com/photo-1445205170230-053b83016050?auto=format&fit=crop&w=1000&h=1500&q=80",
        "https://images.unsplash.com/photo-1492707892479-7bc8d5a4ee93?auto=format&fit=crop&w=1000&h=1500&q=80",
        "https://images.unsplash.com/photo-1469334031218-e382a71b716b?auto=format&fit=crop&w=1000&h=1500&q=80"
    ];

    public static function getPlatformOptimizedVisual($platform, $topicKeyword = 'Fashion') {
        $platLower = strtolower($platform);
        if ($platLower === 'instagram') {
            return self::$INSTAGRAM_TEMPLATES[array_rand(self::$INSTAGRAM_TEMPLATES)];
        } elseif ($platLower === 'pinterest') {
            return self::$PINTEREST_TEMPLATES[array_rand(self::$PINTEREST_TEMPLATES)];
        }
        return "https://images.unsplash.com/photo-1499750310107-5fef28a66643?auto=format&fit=crop&w=1200&q=80";
    }

    public static function formatInstagramCaption($title, $articleUrl, $category = 'Style') {
        $categoryTag = str_replace(' ', '', $category);
        return <<<CAP
✨ $title

Looking to refresh your palette this season? In our latest release, we break down:

• Exact color swatch palettes & contrast rules
• How to pair primary tones naturally
• Full guide with recommendations

🔗 Tap the link in our bio or visit $articleUrl to explore the full collection page!

#StyleGuide #$categoryTag #PaletteInspiration #OOTD #ColorTrends
CAP;
    }

    public static function formatPinterestPin($title, $articleUrl) {
        return [
            'pin_title' => "📌 $title",
            'description' => "Complete seasonal palette visual guide and clothing combination breakdown. Explore full swatches on $articleUrl.",
            'link' => $articleUrl
        ];
    }
}

class SocialPublisher {

    public static function publishToFacebookPage($userId, $slotNumber, $pageId, $accessToken, $title, $articleUrl, $category = 'Blog') {
        $url = "https://graph.facebook.com/v18.0/$pageId/feed";
        $categoryTag = str_replace(' ', '', $category);
        $caption = "🚀 New Visual Guide Published: $title\n\n📖 Read full article & explore swatches here: $articleUrl\n\n#$categoryTag";
        $payload = [
            'message' => $caption,
            'link' => $articleUrl,
            'access_token' => $accessToken
        ];

        try {
            $result = curlPostForm($url, $payload, [], 12);
            $data = $result['data'] ?? [];
            if (in_array($result['http_code'], [200, 201]) && isset($data['id'])) {
                self::logSocialPost($userId, $slotNumber, $title, $articleUrl, 'Facebook', $data['id'], 'Success');
                return ['success' => true, 'platform' => 'Facebook', 'post_id' => $data['id']];
            }
            $errorMsg = $data['error']['message'] ?? ($result['raw'] ?? 'Unknown error');
            self::logSocialPost($userId, $slotNumber, $title, $articleUrl, 'Facebook', null, 'Error', $errorMsg);
            return ['success' => false, 'platform' => 'Facebook', 'error' => $errorMsg];
        } catch (Exception $e) {
            self::logSocialPost($userId, $slotNumber, $title, $articleUrl, 'Facebook', null, 'Error', $e->getMessage());
            return ['success' => false, 'platform' => 'Facebook', 'error' => $e->getMessage()];
        }
    }

    public static function publishToPinterest($userId, $slotNumber, $boardId, $accessToken, $title, $articleUrl) {
        $url = "https://api.pinterest.com/v5/pins";
        $pinImage = SocialCreativeEngine::getPlatformOptimizedVisual('pinterest', $title);
        $pinDetails = SocialCreativeEngine::formatPinterestPin($title, $articleUrl);

        $payload = [
            'board_id' => $boardId,
            'title' => $pinDetails['pin_title'],
            'description' => $pinDetails['description'],
            'link' => $articleUrl,
            'media_source' => [
                'source_type' => 'image_url',
                'url' => $pinImage
            ]
        ];

        try {
            $result = curlPost($url, $payload, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ], 12);
            $data = $result['data'] ?? [];
            if (in_array($result['http_code'], [200, 201])) {
                $pinId = $data['id'] ?? 'pin_created';
                self::logSocialPost($userId, $slotNumber, $title, $articleUrl, 'Pinterest', $pinId, 'Success');
                return ['success' => true, 'platform' => 'Pinterest', 'pin_id' => $pinId];
            }
            $errorMsg = $data['message'] ?? ($result['raw'] ?? 'Unknown error');
            self::logSocialPost($userId, $slotNumber, $title, $articleUrl, 'Pinterest', null, 'Error', $errorMsg);
            return ['success' => false, 'platform' => 'Pinterest', 'error' => $errorMsg];
        } catch (Exception $e) {
            self::logSocialPost($userId, $slotNumber, $title, $articleUrl, 'Pinterest', null, 'Error', $e->getMessage());
            return ['success' => false, 'platform' => 'Pinterest', 'error' => $e->getMessage()];
        }
    }

    public static function publishToInstagram($userId, $slotNumber, $igUserId, $accessToken, $title, $articleUrl, $category = 'Blog') {
        $igImage = SocialCreativeEngine::getPlatformOptimizedVisual('instagram', $title);
        $caption = SocialCreativeEngine::formatInstagramCaption($title, $articleUrl, $category);

        // Step 1: Create container
        $containerUrl = "https://graph.facebook.com/v18.0/$igUserId/media";
        $containerPayload = [
            'image_url' => $igImage,
            'caption' => $caption,
            'access_token' => $accessToken
        ];

        try {
            $cResult = curlPostForm($containerUrl, $containerPayload, [], 12);
            $cData = $cResult['data'] ?? [];
            if (!isset($cData['id'])) {
                $err = $cData['error']['message'] ?? ($cResult['raw'] ?? 'Container creation failed');
                self::logSocialPost($userId, $slotNumber, $title, $articleUrl, 'Instagram', null, 'Error', $err);
                return ['success' => false, 'platform' => 'Instagram', 'error' => $err];
            }

            $creationId = $cData['id'];

            // Step 2: Publish container
            $publishUrl = "https://graph.facebook.com/v18.0/$igUserId/media_publish";
            $publishPayload = [
                'creation_id' => $creationId,
                'access_token' => $accessToken
            ];

            $pResult = curlPostForm($publishUrl, $publishPayload, [], 12);
            $pData = $pResult['data'] ?? [];
            if (isset($pData['id'])) {
                self::logSocialPost($userId, $slotNumber, $title, $articleUrl, 'Instagram', $pData['id'], 'Success');
                return ['success' => true, 'platform' => 'Instagram', 'post_id' => $pData['id']];
            }

            $err = $pData['error']['message'] ?? ($pResult['raw'] ?? 'Publish failed');
            self::logSocialPost($userId, $slotNumber, $title, $articleUrl, 'Instagram', null, 'Error', $err);
            return ['success' => false, 'platform' => 'Instagram', 'error' => $err];
        } catch (Exception $e) {
            self::logSocialPost($userId, $slotNumber, $title, $articleUrl, 'Instagram', null, 'Error', $e->getMessage());
            return ['success' => false, 'platform' => 'Instagram', 'error' => $e->getMessage()];
        }
    }

    public static function broadcastAllSocials($title, $articleUrl, $category = 'Blog', $defaultImage = '', $userId = 1, $slotNumber = 1) {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM social_accounts WHERE user_id = ? AND slot_number = ? AND is_active = 1');
        $stmt->execute([$userId, $slotNumber]);
        $accounts = $stmt->fetchAll();

        $results = [];
        foreach ($accounts as $acc) {
            $platform = strtolower($acc['platform']);
            if ($platform === 'facebook') {
                $results[] = self::publishToFacebookPage($userId, $slotNumber, $acc['page_id_or_board_id'], $acc['access_token'], $title, $articleUrl, $category);
            } elseif ($platform === 'pinterest') {
                $results[] = self::publishToPinterest($userId, $slotNumber, $acc['page_id_or_board_id'], $acc['access_token'], $title, $articleUrl);
            } elseif ($platform === 'instagram') {
                $results[] = self::publishToInstagram($userId, $slotNumber, $acc['page_id_or_board_id'], $acc['access_token'], $title, $articleUrl, $category);
            }
        }
        return $results;
    }

    private static function logSocialPost($userId, $slotNumber, $title, $articleUrl, $platform, $postId, $status, $errorMessage = null) {
        $db = getDB();
        $now = nowString();
        $stmt = $db->prepare('INSERT INTO social_posts_log (user_id, slot_number, article_title, article_url, platform, post_id, status, error_message, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $slotNumber, $title, $articleUrl, $platform, $postId, $status, $errorMessage, $now]);
    }
}
