<?php
/**
 * AutoBacklink - AI Client (Chat & Image)
 * Same providers & call patterns as AutoBlog (Gemini, OpenAI, HuggingFace,
 * OpenRouter, Anthropic, Pollinations, custom endpoint).
 */
require_once __DIR__ . '/helpers.php';

class AIProviderClient {

    public static function chat($credentials, $prompt) {
        $provider = $credentials['provider'] ?? 'custom';
        $pool = $credentials['model_pool'] ?? [];
        $key = $credentials['api_key'] ?? '';
        $model = $credentials['model'] ?? ($provider === 'gemini' ? 'gemini-2.5-flash-lite' : 'gpt-4o-mini');

        if (empty($key)) {
            return ['success' => false, 'error' => 'Chat API key is missing.'];
        }

        // Gemini auto model pool
        if ($provider === 'gemini' && !empty($credentials['auto_model']) && count($pool) > 1) {
            $ordered = strlen($prompt) > 7000 ? $pool : array_reverse($pool);
            $last = ['success' => false, 'error' => 'No Gemini model succeeded.'];
            foreach ($ordered as $candidate) {
                $oneCreds = $credentials;
                $oneCreds['model'] = $candidate;
                $oneCreds['model_pool'] = [];
                $oneCreds['auto_model'] = false;
                $last = self::chat($oneCreds, $prompt);
                if (!empty($last['success'])) {
                    $last['used_model'] = $candidate;
                    return $last;
                }
            }
            return $last;
        }

        try {
            if ($provider === 'gemini') {
                $endpoint = $credentials['endpoint'] ?: "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
                $payload = ['contents' => [['parts' => [['text' => $prompt]]]]];
                $result = curlPost($endpoint . '?key=' . $key, $payload, ['Content-Type: application/json'], 90);
                $data = $result['data'] ?? [];
                $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if ($result['http_code'] >= 400) {
                    return ['success' => false, 'error' => $data['error']['message'] ?? $result['raw'] ?? 'Gemini error'];
                }
                if (empty($content)) {
                    return ['success' => false, 'error' => 'Chat API returned no content.'];
                }
                return ['success' => true, 'content' => $content, 'error' => ''];
            }

            $defaultEndpoints = [
                'huggingface' => 'https://router.huggingface.co/v1/chat/completions',
                'openrouter' => 'https://openrouter.ai/api/v1/chat/completions',
                'zenmux' => 'https://zenmux.ai/api/v1/chat/completions',
                'anthropic' => 'https://api.anthropic.com/v1/messages',
                'openai' => 'https://api.openai.com/v1/chat/completions',
                'pollinations' => 'https://gen.pollinations.ai/v1/chat/completions',
            ];
            $endpoint = $credentials['endpoint'] ?: ($defaultEndpoints[$provider] ?? 'https://api.openai.com/v1/chat/completions');

            if ($provider === 'pollinations' && (empty($model) || $model === 'gpt-4o-mini' || $model === 'gpt-4o')) {
                $model = 'openai';
            }

            if ($provider === 'anthropic') {
                $headers = ['x-api-key: ' . $key, 'anthropic-version: 2023-06-01', 'Content-Type: application/json'];
                $payload = ['model' => $model, 'max_tokens' => 4000, 'messages' => [['role' => 'user', 'content' => $prompt]]];
            } else {
                $headers = ['Authorization: Bearer ' . $key, 'Content-Type: application/json'];
                $payload = ['model' => $model, 'messages' => [['role' => 'user', 'content' => $prompt]], 'temperature' => 0.75];
            }

            $result = curlPost($endpoint, $payload, $headers, 90);
            $data = $result['data'] ?? [];

            if ($result['http_code'] >= 400) {
                $errMsg = $data['error']['message'] ?? ($data['error']['status'] ?? $result['raw'] ?? 'Unknown error');
                return ['success' => false, 'error' => $errMsg];
            }

            if ($provider === 'anthropic') {
                $content = $data['content'][0]['text'] ?? '';
            } else {
                $content = $data['choices'][0]['message']['content'] ?? '';
            }

            if (empty($content)) {
                return ['success' => false, 'error' => 'Chat API returned no content.'];
            }
            return ['success' => true, 'content' => $content, 'error' => ''];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function image($credentials, $prompt) {
        $provider = $credentials['provider'] ?? 'custom';
        $key = $credentials['api_key'] ?? '';
        $model = $credentials['model'] ?? '';
        if (empty($model)) {
            if ($provider === 'pollinations') $model = 'flux';
            elseif ($provider === 'huggingface') $model = 'black-forest-labs/FLUX.1-schnell';
            else $model = 'gpt-image-1';
        }

        if (empty($key)) {
            return ['success' => false, 'error' => 'Image API key is missing.'];
        }

        try {
            if ($provider === 'huggingface') {
                $endpoint = "https://api-inference.huggingface.co/models/$model";
                $headers = ['Authorization: Bearer ' . $key, 'Content-Type: application/json'];
                $payload = ['inputs' => $prompt];
                $result = bkHttp('POST', $endpoint, $headers, json_encode($payload), 180);
                if ($result['http_code'] >= 400) {
                    return ['success' => false, 'error' => $result['data']['error'] ?? 'HuggingFace image error'];
                }
                if (strpos($result['content_type'], 'image') !== false) {
                    return ['success' => true, 'url' => 'data:image/png;base64,' . base64_encode($result['raw']), 'error' => ''];
                }
                return ['success' => false, 'error' => 'HuggingFace did not return an image.'];
            }

            if ($provider === 'gemini') {
                $endpoint = $credentials['endpoint'] ?: "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
                $payload = [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['responseModalities' => ['TEXT', 'IMAGE']]
                ];
                $result = curlPost($endpoint . '?key=' . $key, $payload, ['Content-Type: application/json'], 180);
                $data = $result['data'] ?? [];
                if ($result['http_code'] >= 400) {
                    return ['success' => false, 'error' => json_encode($data)];
                }
                $parts = $data['candidates'][0]['content']['parts'] ?? [];
                $inline = null;
                foreach ($parts as $part) {
                    if (isset($part['inlineData'])) { $inline = $part['inlineData']; break; }
                }
                if ($inline) {
                    $mimeType = $inline['mimeType'] ?? 'image/png';
                    return ['success' => true, 'url' => "data:$mimeType;base64,{$inline['data']}", 'error' => ''];
                }
                return ['success' => false, 'error' => 'Gemini returned no image. Select a Gemini image model with image generation enabled.'];
            }

            if ($provider === 'pollinations') {
                $imgModel = $model ?: 'flux';
                $width = 1024; $height = 675;
                $seed = rand(1000, 9999);
                $imageUrl = "https://gen.pollinations.ai/image/" . urlencode($prompt) . "?model={$imgModel}&width={$width}&height={$height}&seed={$seed}&nologo=true";
                if (!empty($key)) $imageUrl .= '&key=' . urlencode($key);
                $result = bkHttp('GET', $imageUrl, [], null, 30);
                if ($result['http_code'] === 200 && strlen((string)$result['raw']) > 1000) {
                    return ['success' => true, 'url' => $imageUrl, 'error' => ''];
                }
                if ($imgModel !== 'flux') {
                    $fallbackUrl = "https://gen.pollinations.ai/image/" . urlencode($prompt) . "?model=flux&width={$width}&height={$height}&seed=" . rand(1000, 9999) . "&nologo=true";
                    if (!empty($key)) $fallbackUrl .= '&key=' . urlencode($key);
                    $result2 = bkHttp('GET', $fallbackUrl, [], null, 30);
                    if ($result2['http_code'] === 200 && strlen((string)$result2['raw']) > 1000) {
                        return ['success' => true, 'url' => $fallbackUrl, 'error' => ''];
                    }
                }
                return ['success' => false, 'error' => "Pollinations image generation failed with model '$imgModel'. Try another model (flux, turbo, gptimage)."];
            }

            // OpenAI-compatible
            $endpoint = $credentials['endpoint'] ?: 'https://api.openai.com/v1/images/generations';
            $headers = ['Authorization: Bearer ' . $key, 'Content-Type: application/json'];
            $payload = ['model' => $model, 'prompt' => $prompt, 'size' => $credentials['size'] ?? '1536x1024', 'n' => 1];
            $result = curlPost($endpoint, $payload, $headers, 120);
            $data = $result['data'] ?? [];
            if ($result['http_code'] >= 400) {
                return ['success' => false, 'error' => $data['error']['message'] ?? $result['raw'] ?? 'Unknown error'];
            }
            $url = $data['data'][0]['url'] ?? ($data['data'][0]['b64_json'] ?? '');
            if (!empty($url) && !str_starts_with($url, 'http') && !str_starts_with($url, 'data:')) {
                $url = 'data:image/png;base64,' . $url;
            }
            if (empty($url)) {
                return ['success' => false, 'error' => 'Image API returned no image.'];
            }
            return ['success' => true, 'url' => $url, 'error' => ''];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * Anti-AI text sanitizer (same approach as AutoBlog) — removes classic
 * AI-tell words/phrases so content reads human.
 */
class AntiAiSanitizer {

    private static $replacements = [
        'delve into' => 'dig into', 'delve' => 'dig in',
        'in today\'s digital landscape' => 'these days',
        'in today\'s world' => 'these days',
        'it is important to note that' => 'note that',
        'it\'s important to note that' => 'note that',
        'in conclusion' => 'bottom line',
        'furthermore' => 'also',
        'moreover' => 'plus',
        'additionally' => 'also',
        'utilize' => 'use', 'utilizes' => 'uses', 'utilizing' => 'using',
        'leverage' => 'use', 'leverages' => 'uses', 'leveraging' => 'using',
        'seamless' => 'smooth', 'seamlessly' => 'smoothly',
        'cutting-edge' => 'modern',
        'game-changer' => 'big change', 'game changer' => 'big change',
        'robust' => 'strong',
        'comprehensive' => 'complete',
        'in the ever-evolving world of' => 'in',
        'navigating the complexities of' => 'handling',
        'unlock the power of' => 'make the most of',
        'empower' => 'help', 'empowers' => 'helps', 'empowering' => 'helping',
        'elevate' => 'improve',
        'harness' => 'use', 'harnessing' => 'using',
        'tapestry' => 'mix',
        'testament to' => 'proof of',
        'whether you are a' => 'if you are a',
        'a wide range of' => 'many',
        'a variety of' => 'many',
        'plethora of' => 'lots of',
        'myriad of' => 'lots of',
        'at the end of the day' => 'in the end',
        'when it comes to' => 'for',
        'need to say' => 'say',
        'rest assured' => 'trust me',
        'in this fast-paced world' => 'these days',
        'whether you\'re' => 'if you\'re',
    ];

    public static function sanitizeText($text) {
        $text = (string)$text;
        foreach (self::$replacements as $from => $to) {
            $text = preg_replace('/\b' . preg_quote($from, '/') . '\b/i', $to, $text);
        }
        // collapse triple+ spaces
        $text = preg_replace('/[ \t]{2,}/', ' ', $text);
        return $text;
    }
}
