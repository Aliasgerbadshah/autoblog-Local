<?php
/**
 * AutoBlog SaaS - AI Provider Client (Chat & Image APIs)
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
                if ($last['success'] ?? false) {
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

            // OpenAI-compatible providers
            $defaultEndpoints = [
                'huggingface' => 'https://router.huggingface.co/v1/chat/completions',
                'openrouter' => 'https://openrouter.ai/api/v1/chat/completions',
                'zenmux' => 'https://zenmux.ai/api/v1/chat/completions',
                'anthropic' => 'https://api.anthropic.com/v1/messages',
                'openai' => 'https://api.openai.com/v1/chat/completions',
                'pollinations' => 'https://gen.pollinations.ai/v1/chat/completions',
            ];
            $endpoint = $credentials['endpoint'] ?: ($defaultEndpoints[$provider] ?? 'https://api.openai.com/v1/chat/completions');
            
            // For Pollinations, ensure the model is properly set
            // Pollinations accepts: openai, openai-fast, openai-large, gemini, gemini-fast, claude, deepseek, grok, mistral, qwen, llama
            if ($provider === 'pollinations' && (empty($model) || $model === 'gpt-4o-mini' || $model === 'gpt-4o')) {
                $model = 'openai'; // Default Pollinations model
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
        // Set provider-specific defaults if model is empty
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
                // Hugging Face Inference API
                $endpoint = "https://api-inference.huggingface.co/models/$model";
                $headers = ['Authorization: Bearer ' . $key, 'Content-Type: application/json'];
                $payload = ['inputs' => $prompt];

                $ch = curl_init($endpoint);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 180,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);

                if ($httpCode >= 400) {
                    $errData = json_decode($response, true);
                    return ['success' => false, 'error' => $errData['error'] ?? 'HuggingFace image error'];
                }

                if (strpos($contentType, 'image') !== false) {
                    $b64 = base64_encode($response);
                    return ['success' => true, 'url' => 'data:image/png;base64,' . $b64, 'error' => ''];
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
                    if (isset($part['inlineData'])) {
                        $inline = $part['inlineData'];
                        break;
                    }
                }
                if ($inline) {
                    $mimeType = $inline['mimeType'] ?? 'image/png';
                    return ['success' => true, 'url' => "data:$mimeType;base64,{$inline['data']}", 'error' => ''];
                }
                return ['success' => false, 'error' => 'Gemini returned no image. Select a Gemini image model with image generation enabled.'];
            }

            if ($provider === 'pollinations') {
                // Pollinations.ai image — GET request with prompt in URL
                // User types the model name directly — NO pre-made models, NO mapping
                // The model name goes directly to the API as ?model=VALUE
                $imgModel = $model;
                if (empty($imgModel)) $imgModel = 'flux';
                error_log("[Pollinations Image] Model: $imgModel | Provider: $provider | Full credentials model: $model");
                $width = 1024;
                $height = 1024;
                $seed = rand(1000, 9999);
                $imageUrl = "https://gen.pollinations.ai/image/" . urlencode($prompt) . "?model={$imgModel}&width={$width}&height={$height}&seed={$seed}&nologo=true";
                if (!empty($key)) {
                    $imageUrl .= "&key=" . urlencode($key);
                }
                // Pollinations generates image on-the-fly, so we need to actually fetch it
                // to verify it works. Use a GET request with 30s timeout.
                $ch = curl_init($imageUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/115.0.0.0',
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                $curlErr = curl_error($ch);
                curl_close($ch);
                
                if ($httpCode === 200 && $response && strlen($response) > 1000) {
                    // Image was generated successfully — return the URL (not the data)
                    return ['success' => true, 'url' => $imageUrl, 'error' => ''];
                }
                
                // Image generation failed with user's model — try fallback to 'flux'
                if ($imgModel !== 'flux') {
                    error_log("[Pollinations Image] Model '$imgModel' failed (HTTP $httpCode, size " . strlen($response ?? '') . "). Retrying with 'flux'...");
                    $fallbackUrl = "https://gen.pollinations.ai/image/" . urlencode($prompt) . "?model=flux&width={$width}&height={$height}&seed=" . rand(1000, 9999) . "&nologo=true";
                    if (!empty($key)) {
                        $fallbackUrl .= "&key=" . urlencode($key);
                    }
                    $ch2 = curl_init($fallbackUrl);
                    curl_setopt_array($ch2, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/115.0.0.0',
                    ]);
                    $response2 = curl_exec($ch2);
                    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                    curl_close($ch2);
                    
                    if ($httpCode2 === 200 && $response2 && strlen($response2) > 1000) {
                        return ['success' => true, 'url' => $fallbackUrl, 'error' => ''];
                    }
                }
                
                return ['success' => false, 'error' => "Pollinations image generation failed with model '$imgModel' (HTTP $httpCode). Response size: " . strlen($response ?? '') . " bytes. Try a different model name (flux, turbo, gptimage, flux-pro)."];
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
