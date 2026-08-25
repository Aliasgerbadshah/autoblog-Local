<?php
/**
 * Google Keyword Planner API Integration
 * Uses Google Ads API v21 to get real keyword search volumes, competition, and ideas.
 * 
 * Requirements:
 * - Google Ads Developer Token (from https://ads.google.com/home/tools/ → API Center)
 * - Google Ads Customer ID (10-digit format: 123-456-7890)
 * - OAuth2 credentials (reuse Blogger's Client ID/Secret + Refresh Token)
 * 
 * OAuth scope needed: https://www.googleapis.com/auth/adwords
 * Generate refresh token with BOTH scopes:
 *   https://www.googleapis.com/auth/blogger
 *   https://www.googleapis.com/auth/adwords
 */

require_once __DIR__ . '/helpers.php';

class GoogleKeywordPlanner {
    
    const API_BASE = 'https://googleads.googleapis.com/v21';
    
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
        
        // Clean customer ID (remove dashes)
        $cleanCustomerId = str_replace('-', '', $customerId);
        $cleanLoginCustomerId = str_replace('-', '', ($loginCustomerId ?: $customerId));
        
        $url = self::API_BASE . "/customers/{$cleanCustomerId}:generateKeywordIdeas";
        
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
                'keywords' => array_slice($seedKeywords, 0, 10)
            ],
            'language' => "languageConstants/{$languageCode}",
            'geoTargetConstants' => ["geoTargetConstants/{$locationId}"],
            'pageSize' => 100,
            'historicalMetricsOptions' => [
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
        
        $result = curlPost($url, $body, $headers, 30);
        
        // Handle non-JSON responses (e.g. Google HTML error pages like 404)
        if (!is_array($result['data'])) {
            $rawPreview = substr($result['raw'] ?? '', 0, 200);
            error_log("[Google Keyword Planner] Non-JSON response HTTP {$result['http_code']}: $rawPreview");
            return ['success' => false, 'error' => "Google Ads API returned non-JSON response (HTTP {$result['http_code']}). This usually means the endpoint URL is wrong or the API version is sunset. URL: $url"];
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
            $metrics = $idea['keywordPlanAdGroupKeywordHistoricalMetrics'] ?? [];
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
                'competition_index' => $compIndex,
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
        
        error_log("[Google Keyword Planner] Got " . count($keywords) . " keyword ideas");
        
        return [
            'success' => true,
            'keywords' => $keywords,
            'count' => count($keywords),
            'source' => 'Google Keyword Planner'
        ];
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
