<?php
/**
 * Google Keyword Planner API Integration
 * Uses current Google Ads API versions (v21 sunset 5 Aug 2026).
 *
 * Requirements:
 * - Google Ads Developer Token (from https://ads.google.com/home/tools/ → API Center)
 * - Google Ads Customer ID (10-digit format: 123-456-7890)
 * - OAuth2 credentials (Google Cloud Web Client ID/Secret + Refresh Token)
 *
 * OAuth scope needed: https://www.googleapis.com/auth/adwords
 */

require_once __DIR__ . '/helpers.php';

class GoogleKeywordPlanner {

    // Newest first. v21 sunset 5 Aug 2026 and now returns HTML 404.
    const API_VERSIONS = ['v25', 'v24', 'v23', 'v22'];
    
    /**
     * Get fresh OAuth access token for Google Ads API.
     * Reuses Blogger OAuth credentials.
     */
    public static function getAccessToken($clientId, $clientSecret, $refreshToken) {
        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            return ['success' => false, 'error' => 'OAuth credentials required for Google Ads API.'];
        }
        
        $payload = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];
        $result = curlPostForm('https://oauth2.googleapis.com/token', $payload, [], 15);
        
        if ($result['success'] && $result['http_code'] === 200 && !empty($result['data']['access_token'])) {
            return ['success' => true, 'access_token' => $result['data']['access_token']];
        }
        
        $errData = $result['data'] ?? [];
        $errMsg = $errData['error'] ?? '';
        $errDesc = $errData['error_description'] ?? '';
        if ($errMsg === 'invalid_client') {
            return ['success' => false, 'error' => 'OAuth invalid_client (HTTP 401): Google rejected the OAuth Client ID / Client Secret pair. Use the Google Cloud Web application credentials that created this refresh token (ends with .apps.googleusercontent.com). Do NOT paste a Google Ads customer/manager ID here. Client ID and Secret must be from the same OAuth app.'];
        }
        if ($errMsg === 'unauthorized_client') {
            return ['success' => false, 'error' => 'OAuth unauthorized_client: this Refresh Token was created with a DIFFERENT Client ID/Secret. Generate a new refresh token in OAuth Playground using the same Client ID + Secret you saved.'];
        }
        return ['success' => false, 'error' => "OAuth failed: $errMsg" . ($errDesc ? " — $errDesc" : '') . " (HTTP {$result['http_code']})"];
    }
    
    /**
     * Generate keyword ideas using Google Ads API Keyword Planner.
     * Returns keyword ideas with monthly search volumes, competition, and CPC.
     */
    public static function generateKeywordIdeas($developerToken, $customerId, $loginCustomerId, $accessToken, $seedKeywords, $languageCode = '1000', $locationId = 2356) {
        if (empty($developerToken) || empty($customerId) || empty($accessToken)) {
            return ['success' => false, 'error' => 'Developer Token, Customer ID, and Access Token are required.'];
        }
        
        if (empty($seedKeywords)) {
            return ['success' => false, 'error' => 'At least one seed keyword is required.'];
        }
        
        $cleanCustomerId = preg_replace('/\D+/', '', $customerId);
        $cleanLoginCustomerId = preg_replace('/\D+/', '', ($loginCustomerId ?: $customerId));
        
        // Month number to Google Ads API enum name
        $monthEnum = [1=>'JANUARY',2=>'FEBRUARY',3=>'MARCH',4=>'APRIL',5=>'MAY',6=>'JUNE',
                      7=>'JULY',8=>'AUGUST',9=>'SEPTEMBER',10=>'OCTOBER',11=>'NOVEMBER',12=>'DECEMBER'];
        $currentMonth = intval(date('n'));
        $currentYear = intval(date('Y'));
        $startMonth = max(1, $currentMonth - 6);
        $startYear = $currentYear;
        if ($currentMonth - 6 < 1) { $startYear--; $startMonth = $currentMonth + 6; }
        
        // Build request body (REST format)
        $body = [
            'keywordSeed' => [
                'keywords' => array_slice(array_values(array_unique($seedKeywords)), 0, 20)
            ],
            'language' => "languageConstants/{$languageCode}",
            'geoTargetConstants' => ["geoTargetConstants/{$locationId}"],
            'keywordPlanNetwork' => 'GOOGLE_SEARCH',
            'pageSize' => 100,
            'historicalMetricsOptions' => [
                'includeAverageCpc' => true,
                'yearMonthRange' => [
                    'start' => ['year' => $startYear, 'month' => $monthEnum[$startMonth]],
                    'end' => ['year' => $currentYear, 'month' => $monthEnum[$currentMonth]]
                ]
            ]
        ];
        
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'developer-token: ' . $developerToken,
            'login-customer-id: ' . $cleanLoginCustomerId,
            'Content-Type: application/json'
        ];
        
        error_log("[Google Keyword Planner] Requesting ideas for: " . implode(', ', $seedKeywords));

        $result = null;
        $url = '';
        $lastVersionError = '';
        foreach (self::API_VERSIONS as $version) {
            $url = "https://googleads.googleapis.com/{$version}/customers/{$cleanCustomerId}:generateKeywordIdeas";
            $result = curlPost($url, $body, $headers, 30);
            $http = intval($result['http_code'] ?? 0);
            $rawPreview = substr($result['raw'] ?? '', 0, 180);
            error_log("[Google Keyword Planner] {$version} HTTP {$http}: {$rawPreview}");

            $msg = is_array($result['data']) ? (string)($result['data']['error']['message'] ?? '') : '';
            $isSunset = ($http === 404) || !is_array($result['data'])
                || ($http === 400 && (stripos($msg, 'UNSUPPORTED_VERSION') !== false || stripos($msg, 'deprecated') !== false || stripos($msg, 'sunset') !== false));
            if ($isSunset) {
                $lastVersionError = "{$version} HTTP {$http}";
                continue;
            }
            break;
        }

        if (!$result || !is_array($result['data'])) {
            return ['success' => false, 'error' => "Google Ads API v21 is sunset (5 Aug 2026). Tried " . implode(', ', self::API_VERSIONS) . " and still got a non-JSON/404 ({$lastVersionError}). Last URL: {$url}. If this continues, put the TEST client ID in Customer ID and the TEST manager ID in Login Customer ID."];
        }
        
        if ($result['http_code'] >= 400) {
            $error = $result['data']['error']['message'] ?? ($result['raw'] ?? 'Unknown error');
            error_log("[Google Keyword Planner] API Error: HTTP {$result['http_code']} - $error");
            
            if (stripos($error, 'PERMISSION_DENIED') !== false) {
                return ['success' => false, 'error' => 'Permission denied. Your OAuth token needs the adwords scope. Generate a new refresh token with scope: https://www.googleapis.com/auth/adwords (plus blogger scope)'];
            }
            if (stripos($error, 'NOT_FOUND') !== false || stripos($error, 'CUSTOMER_NOT_FOUND') !== false) {
                return ['success' => false, 'error' => 'Customer ID not found. Make sure you have a Google Ads account (even a dummy one with no active campaigns). Format: 123-456-7890'];
            }
            if (stripos($error, 'DEVELOPER_TOKEN') !== false || stripos($error, 'developer') !== false) {
                return ['success' => false, 'error' => 'Invalid Developer Token. Get one at: Google Ads → Tools & Settings → API Center → Developer Token'];
            }
            return ['success' => false, 'error' => "Google Ads API Error (HTTP {$result['http_code']}): $error"];
        }
        
        // Parse response — REST API wraps results differently
        $keywords = [];
        $results = $result['data'] ?? [];
        
        // REST API returns results at top level, not nested under 'keywordIdeas'
        $ideas = $results['results'] ?? $results['keywordIdeas'] ?? [];
        
        foreach ($ideas as $idea) {
            // REST format: text is in keywordPlanAdGroupKeyword or directly
            $text = $idea['text'] ?? '';
            if (empty($text)) {
                $text = $idea['keywordPlanAdGroupKeyword']['text'] ?? '';
            }
            if (empty($text)) continue;
            
            // Metrics can be nested under keywordPlanAdGroupKeywordHistoricalMetrics or directly
            $metrics = $idea['keywordIdeaMetrics'] ?? [];
            if (empty($metrics)) {
                $metrics = $idea['keywordPlanAdGroupKeywordHistoricalMetrics'] ?? [];
            }
            if (empty($metrics)) {
                $metrics = $idea['historicalMetrics'] ?? [];
            }
            
            // Monthly search volumes
            $monthlySearchVolumes = $metrics['monthlySearchVolumes'] ?? [];
            $avgMonthlySearches = $metrics['avgMonthlySearches'] ?? null;
            
            // Get average monthly searches
            // REST API wraps Int64 values in {"value": N} objects
            $volume = 0;
            if ($avgMonthlySearches !== null) {
                $volume = is_array($avgMonthlySearches) ? intval($avgMonthlySearches['value'] ?? 0) : intval($avgMonthlySearches);
            } elseif (!empty($monthlySearchVolumes)) {
                $volumes = array_map(function($m) { 
                    $ms = $m['monthlySearches'] ?? 0;
                    return is_array($ms) ? intval($ms['value'] ?? 0) : intval($ms);
                }, $monthlySearchVolumes);
                $volume = intval(array_sum($volumes) / count($volumes));
            }
            
            // Competition level
            $competition = $metrics['competition'] ?? 'UNSPECIFIED';
            $competitionMap = ['LOW' => 'Low', 'MEDIUM' => 'Medium', 'HIGH' => 'High', 'UNSPECIFIED' => 'Unknown'];
            $compLevel = $competitionMap[$competition] ?? $competition;
            
            // Competition index (0-100)
            $compIndex = $metrics['competitionIndex'] ?? null;
            if (is_array($compIndex)) $compIndex = $compIndex['value'] ?? null;
            
            // CPC (in micros → dollars) — REST API wraps Int64 in {"value": N}
            $cpcMicros = $metrics['lowTopOfPageBidMicros'] ?? null;
            if (is_array($cpcMicros)) $cpcMicros = $cpcMicros['value'] ?? null;
            $cpc = $cpcMicros ? round(intval($cpcMicros) / 1000000, 2) : null;
            
            $keywords[] = [
                'keyword' => $text,
                'volume' => $volume,
                'difficulty' => $compLevel,
                'competition_index' => $compIndex !== null ? intval($compIndex) : null,
                'cpc' => $cpc,
                'intent' => self::determineIntent($text),
                'source' => 'Google Keyword Planner',
                'monthly_data' => array_map(function($m) {
                    $ms = $m['monthlySearches'] ?? 0;
                    return [
                        'year' => $m['year'] ?? '',
                        'month' => $m['month'] ?? '',
                        'volume' => is_array($ms) ? intval($ms['value'] ?? 0) : intval($ms)
                    ];
                }, $monthlySearchVolumes)
            ];
        }

        $keywords = self::selectBestTargetKeywords($keywords, $seedKeywords[0] ?? '');
        error_log("[Google Keyword Planner] Ranked " . count($keywords) . " ideas; primary=" . ($keywords[0]['keyword'] ?? ''));
        
        return [
            'success' => true,
            'keywords' => $keywords,
            'count' => count($keywords),
            'primary_keyword' => $keywords[0]['keyword'] ?? ($seedKeywords[0] ?? ''),
            'source' => 'Google Keyword Planner'
        ];
    }

    /**
     * Expand a topic into many Keyword Planner seeds so Google returns
     * high-volume / low-competition ideas, not just the raw title.
     */
    public static function expandSeedKeywords($topic, $primary = '') {
        $topic = trim((string)$topic);
        $primary = trim((string)$primary);
        $seeds = [];
        foreach ([$topic, $primary] as $raw) {
            if ($raw === '') continue;
            $seeds[] = $raw;
            $clean = strtolower($raw);
            $clean = preg_replace('/[^a-z0-9\s]+/i', ' ', $clean);
            $stop = ['the','a','an','and','or','for','of','to','in','on','with','your','you','best','guide','tips','how','what','why'];
            $words = array_values(array_filter(preg_split('/\s+/', $clean), function($w) use ($stop) {
                return strlen($w) > 2 && !in_array($w, $stop, true);
            }));
            if (!empty($words)) {
                $core = implode(' ', array_slice($words, 0, 6));
                $seeds[] = $core;
                $seeds[] = 'best ' . $core;
                $seeds[] = $core . ' guide';
                $seeds[] = $core . ' tips';
                $seeds[] = 'how to ' . $core;
                $seeds[] = $core . ' for beginners';
                $seeds[] = $core . ' vs';
                $seeds[] = $core . ' cost';
                $seeds[] = $core . ' examples';
                if (count($words) >= 2) {
                    $seeds[] = $words[0] . ' ' . $words[1];
                }
            }
        }
        $out = [];
        $seen = [];
        foreach ($seeds as $s) {
            $s = trim(preg_replace('/\s+/', ' ', $s));
            if ($s === '' || strlen($s) < 3) continue;
            $k = strtolower($s);
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $out[] = $s;
            if (count($out) >= 16) break;
        }
        return $out ?: ['digital marketing'];
    }

    /**
     * Rank Keyword Planner ideas: highest volume, lowest competition,
     * still relevant to the topic. Index 0 = primary, rest = secondary.
     */
    public static function selectBestTargetKeywords($rows, $topic = '') {
        $rows = array_values(array_filter((array)$rows, function($r) {
            return !empty($r['keyword']);
        }));
        if (empty($rows)) return [];

        $topicWords = self::topicWords($topic);
        foreach ($rows as &$r) {
            $kw = strtolower(trim($r['keyword'] ?? ''));
            $volume = intval($r['volume'] ?? 0);
            $diff = strtolower((string)($r['difficulty'] ?? ''));
            $idx = $r['competition_index'] ?? null;
            if ($idx === null || $idx === '') $idx = ($diff === 'low' ? 20 : ($diff === 'high' ? 80 : 50));
            $idx = max(0, min(100, intval($idx)));

            $compMult = 1.0;
            if ($diff === 'low') $compMult = 1.6;
            elseif ($diff === 'medium') $compMult = 1.0;
            elseif ($diff === 'high') $compMult = 0.35;
            else $compMult = 0.8;

            $rel = self::relevanceScore($kw, $topicWords);
            $score = ($volume > 0 ? $volume : 1) * $compMult / (1 + ($idx / 70));
            $score *= (0.35 + (0.65 * $rel));
            if ($volume <= 0 && $diff === 'low') $score *= 1.15;
            $r['_score'] = $score;
            $r['_relevance'] = $rel;
            $r['role'] = 'secondary';
        }
        unset($r);

        usort($rows, function($a, $b) {
            $s = ($b['_score'] <=> $a['_score']);
            if ($s !== 0) return $s;
            $v = (intval($b['volume'] ?? 0) <=> intval($a['volume'] ?? 0));
            if ($v !== 0) return $v;
            return (intval($a['competition_index'] ?? 50) <=> intval($b['competition_index'] ?? 50));
        });

        $relevant = array_values(array_filter($rows, function($r) {
            return ($r['_relevance'] ?? 0) >= 0.18;
        }));
        if (count($relevant) >= 3) $rows = $relevant;

        $seen = [];
        $out = [];
        foreach ($rows as $r) {
            $k = strtolower(trim($r['keyword']));
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            unset($r['_score'], $r['_relevance']);
            $out[] = $r;
            if (count($out) >= 5) break;
        }
        if (!empty($out)) $out[0]['role'] = 'primary';
        return $out;
    }

    private static function topicWords($topic) {
        $clean = strtolower(preg_replace('/[^a-z0-9\s]+/i', ' ', (string)$topic));
        $stop = ['the','a','an','and','or','for','of','to','in','on','with','your','you','best','guide','tips'];
        return array_values(array_filter(preg_split('/\s+/', $clean), function($w) use ($stop) {
            return strlen($w) > 2 && !in_array($w, $stop, true);
        }));
    }

    private static function relevanceScore($keyword, $topicWords) {
        if (empty($topicWords)) return 1.0;
        $kwWords = preg_split('/\s+/', strtolower($keyword));
        $hit = 0;
        foreach ($topicWords as $w) {
            foreach ($kwWords as $kw) {
                if ($kw === $w || strpos($kw, $w) !== false || strpos($w, $kw) !== false) {
                    $hit++;
                    break;
                }
            }
        }
        return min(1.0, $hit / max(1, count($topicWords)));
    }
    
    /**
     * Determine search intent from keyword text.
     */
    private static function determineIntent($keyword) {
        $kw = strtolower($keyword);
        $commercialWords = ['buy', 'price', 'cost', 'cheap', 'best', 'top', 'review', 'vs', 'compare', 'deal', 'discount', 'offer', 'shop', 'order', 'hire', 'service', 'company', 'agency', 'package', 'plan', 'subscription'];
        $informationalWords = ['how', 'what', 'why', 'guide', 'tutorial', 'learn', 'tips', 'examples', 'definition', 'meaning', 'explain', 'difference between', 'benefits', 'advantages'];
        $transactionalWords = ['download', 'free', 'trial', 'demo', 'sign up', 'register', 'get', 'start', 'apply'];
        
        foreach ($transactionalWords as $w) { if (strpos($kw, $w) !== false) return 'Transactional'; }
        foreach ($commercialWords as $w) { if (strpos($kw, $w) !== false) return 'Commercial'; }
        foreach ($informationalWords as $w) { if (strpos($kw, $w) !== false) return 'Informational'; }
        return 'Informational';
    }
}
