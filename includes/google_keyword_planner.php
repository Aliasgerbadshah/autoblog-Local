<?php
/**
 * Google Keyword Planner API Integration
 * Uses Google Ads API v18 to get real keyword search volumes, competition, and ideas.
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
    
    const API_BASE = 'https://googleads.googleapis.com/v18';
    
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
        return ['success' => false, 'error' => "OAuth failed: $errMsg (HTTP {$result['http_code']})"];
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
        
        $url = self::API_BASE . "/customers/{$cleanCustomerId}/keywordPlanIdeas:generateKeywordIdeas";
        
        // Build request body
        $body = [
            'keywordSeed' => [
                'keywords' => array_slice($seedKeywords, 0, 10)
            ],
            'languageConstants' => ["languageConstants/{$languageCode}"],
            'geoTargetConstants' => ["geoTargetConstants/{$locationId}"],
            'pageSize' => 100,
            'historicalMetricsOptions' => [
                'yearMonthRange' => [
                    'start' => ['year' => intval(date('Y')), 'month' => max(1, intval(date('n')) - 6)],
                    'end' => ['year' => intval(date('Y')), 'month' => intval(date('n'))]
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
        
        // Parse response
        $keywords = [];
        $results = $result['data'] ?? [];
        
        $ideas = $results['keywordIdeas'] ?? [];
        if (empty($ideas)) {
            $ideas = $results['results'] ?? [];
        }
        
        foreach ($ideas as $idea) {
            $text = $idea['text'] ?? '';
            if (empty($text)) continue;
            
            $metrics = $idea['keywordPlanAdGroupKeywordHistoricalMetrics'] ?? [];
            
            // Monthly search volumes
            $monthlySearchVolumes = $metrics['monthlySearchVolumes'] ?? [];
            $avgMonthlySearches = $metrics['avgMonthlySearches'] ?? null;
            
            // Get average monthly searches
            $volume = 0;
            if ($avgMonthlySearches !== null) {
                $volume = intval($avgMonthlySearches);
            } elseif (!empty($monthlySearchVolumes)) {
                $volumes = array_map(function($m) { return intval($m['monthlySearches'] ?? 0); }, $monthlySearchVolumes);
                $volume = intval(array_sum($volumes) / count($volumes));
            }
            
            // Competition level
            $competition = $metrics['competition'] ?? 'UNSPECIFIED';
            $competitionMap = ['LOW' => 'Low', 'MEDIUM' => 'Medium', 'HIGH' => 'High', 'UNSPECIFIED' => 'Unknown'];
            $compLevel = $competitionMap[$competition] ?? $competition;
            
            // Competition index (0-100)
            $compIndex = $metrics['competitionIndex'] ?? null;
            
            // CPC (in micros → dollars)
            $cpcMicros = $metrics['lowTopOfPageBidMicros'] ?? null;
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
                    return [
                        'year' => $m['year'] ?? '',
                        'month' => $m['month'] ?? '',
                        'volume' => intval($m['monthlySearches'] ?? 0)
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
