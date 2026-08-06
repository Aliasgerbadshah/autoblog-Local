<?php
/**
 * AutoBlog SaaS - Research Agent & DataForSEO Client
 */

require_once __DIR__ . '/helpers.php';

class ResearchAgent {

    public static function analyzeCustomerWebsite($domainUrl) {
        if (!str_starts_with($domainUrl, 'http')) {
            $domainUrl = 'https://' . $domainUrl;
        }

        $info = [
            'domain' => $domainUrl,
            'title' => '',
            'description' => '',
            'headings' => [],
            'error' => null
        ];

        $result = curlGet($domainUrl, ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/115.0.0.0 Safari/537.36'], 8);

        if (!$result['success'] || $result['http_code'] !== 200) {
            $info['error'] = $result['error'] ?? 'HTTP ' . ($result['http_code'] ?? 0);
            return $info;
        }

        $html = $result['data'];
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR);

        // Title
        $titleEl = $dom->getElementsByTagName('title')->item(0);
        if ($titleEl) {
            $info['title'] = trim($titleEl->textContent);
        }

        // Meta description
        $metas = $dom->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            if ($meta->getAttribute('name') === 'description' || $meta->getAttribute('property') === 'og:description') {
                $info['description'] = trim($meta->getAttribute('content'));
                break;
            }
        }

        // Headings
        $headings = [];
        foreach (['h1', 'h2'] as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            $count = 0;
            foreach ($elements as $el) {
                $headings[] = trim($el->textContent);
                $count++;
                if ($count >= 5) break;
            }
        }
        $info['headings'] = $headings;

        return $info;
    }

    public static function searchKeywordsSerpapi($searchQuery, $serpapiKey = null) {
        $results = ['query' => $searchQuery, 'keywords' => [], 'questions' => []];

        if ($serpapiKey) {
            $url = "https://serpapi.com/search.json?q=" . urlencode($searchQuery) . "&api_key=$serpapiKey&engine=google";
            $result = curlGet($url, [], 10);
            if ($result['success'] && $result['http_code'] === 200) {
                $data = $result['data'] ?? [];
                $related = [];
                if (is_array($data)) {
                    foreach ($data['related_searches'] ?? [] as $r) {
                        if (!empty($r['query'])) $related[] = $r['query'];
                    }
                    $paa = [];
                    foreach ($data['related_questions'] ?? [] as $q) {
                        if (!empty($q['question'])) $paa[] = $q['question'];
                    }
                    $results['keywords'] = array_slice($related, 0, 5);
                    $results['questions'] = array_slice($paa, 0, 5);
                    return $results;
                }
            }
        }

        // Fallback without SerpAPI
        $results['keywords'] = [
            "$searchQuery strategy",
            "$searchQuery tools and workflows",
            "how to improve $searchQuery",
            "$searchQuery real world examples"
        ];
        $results['questions'] = [
            "What is the best approach to $searchQuery?",
            "How much does $searchQuery cost?",
            "What are common mistakes in $searchQuery?"
        ];
        return $results;
    }

    public static function crawlAndExtractSitePages($domainUrl, $userId = 1) {
        if (!str_starts_with($domainUrl, 'http')) {
            $domainUrl = 'https://' . $domainUrl;
        }

        $parsedDomain = parse_url($domainUrl, PHP_URL_HOST);
        $discoveredPages = [];

        $result = curlGet($domainUrl, ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/115.0.0.0 Safari/537.36'], 8);

        if ($result['success'] && $result['http_code'] === 200) {
            $dom = new DOMDocument();
            @$dom->loadHTML($result['data'], LIBXML_NOERROR);
            $links = $dom->getElementsByTagName('a');
            $seenUrls = [];

            foreach ($links as $a) {
                $href = $a->getAttribute('href');
                if (empty($href)) continue;

                $fullUrl = self::resolveUrl($domainUrl, $href);
                if (!$fullUrl) continue;

                $linkHost = parse_url($fullUrl, PHP_URL_HOST);
                if ($linkHost !== $parsedDomain) continue;
                if (isset($seenUrls[$fullUrl])) continue;

                $anchorText = trim($a->textContent);
                $ext = strtolower(pathinfo(parse_url($fullUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'png', 'css', 'js'])) continue;
                if (strlen($anchorText) <= 3) continue;

                $seenUrls[$fullUrl] = true;
                $discoveredPages[] = [
                    'page_url' => $fullUrl,
                    'page_title' => $anchorText
                ];

                if (count($discoveredPages) >= 100) break;
            }
        }

        if (empty($discoveredPages)) {
            $discoveredPages[] = ['page_url' => $domainUrl, 'page_title' => 'Official Portal'];
        }

        // Save to database
        $db = getDB();
        $now = nowString();
        foreach ($discoveredPages as $p) {
            $stmt = $db->prepare('INSERT INTO site_crawled_pages (user_id, domain_url, page_url, page_title, created_at) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $domainUrl, $p['page_url'], $p['page_title'], $now]);
        }

        return $discoveredPages;
    }

    private static function resolveUrl($baseUrl, $href) {
        if (preg_match('/^(https?:\/\/)/', $href)) return $href;
        if (str_starts_with($href, '//')) return 'https:' . $href;
        if (str_starts_with($href, '/')) {
            $parts = parse_url($baseUrl);
            return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $href;
        }
        return rtrim($baseUrl, '/') . '/' . $href;
    }
}

class DataForSEOClient {
    private static $BASE = 'https://api.dataforseo.com/v3';

    private static function request($credentials, $endpoint, $payload) {
        $login = $credentials['login'] ?? '';
        $password = $credentials['password'] ?? '';
        if (empty($login) || empty($password)) {
            return ['success' => false, 'error' => 'DataForSEO login and password are required.'];
        }

        $ch = curl_init(self::$BASE . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERPWD => "$login:$password",
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($httpCode >= 400 || ($data['status_code'] ?? 0) >= 40000) {
            return ['success' => false, 'error' => $data['status_message'] ?? $response];
        }
        return ['success' => true, 'data' => $data];
    }

    public static function keywordVolume($credentials, $keywords, $locationCode = 2356, $languageCode = 'en') {
        $payload = [['keywords' => $keywords, 'location_code' => $locationCode, 'language_code' => $languageCode]];
        return self::request($credentials, '/keywords_data/google/search_volume/task_post', $payload);
    }

    public static function serp($credentials, $keyword, $locationCode = 2356, $languageCode = 'en') {
        $payload = [['keyword' => $keyword, 'location_code' => $locationCode, 'language_code' => $languageCode, 'device' => 'desktop', 'os' => 'windows']];
        return self::request($credentials, '/serp/google/organic/live/advanced', $payload);
    }

    public static function labsKeywordOverview($credentials, $keywords, $locationCode = 2356, $languageCode = 'en') {
        $payload = [['keywords' => $keywords, 'location_code' => $locationCode, 'language_code' => $languageCode]];
        return self::request($credentials, '/dataforseo_labs/google/keyword_overview/live', $payload);
    }
}
