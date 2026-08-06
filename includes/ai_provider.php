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
            ];
            $endpoint = $credentials['endpoint'] ?: ($defaultEndpoints[$provider] ?? 'https://api.openai.com/v1/chat/completions');

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
        $provider = $credentials['provider'] ?? 'openai';
        $key = $credentials['api_key'] ?? '';
        // Auto-select cheapest OpenAI model for cost savings
        // gpt-image-1-mini = cheapest (~$0.01/image), gpt-image-1 = standard (~$0.04/image)
        $model = $credentials['model'] ?? ($provider === 'huggingface' ? 'black-forest-labs/FLUX.1-schnell' : ($provider === 'gemini' ? 'gemini-2.5-flash-preview-image-generation' : 'gpt-image-1-mini'));

        if (empty($key)) {
            return ['success' => false, 'error' => 'Image API key is missing.'];
        }

        try {
            if ($provider === 'huggingface') {
                // Hugging Face Inference API (FREE)
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
                if (empty($credentials['endpoint'])) {
                    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
                } else {
                    $endpoint = $credentials['endpoint'];
                }
                $payload = [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['responseModalities' => ['TEXT', 'IMAGE']]
                ];
                $result = curlPost($endpoint . '?key=' . $key, $payload, ['Content-Type: application/json'], 180);
                $data = $result['data'] ?? [];

                if ($result['http_code'] >= 400) {
                    $errMsg = $data['error']['message'] ?? json_encode($data);
                    return ['success' => false, 'error' => "Gemini API Error ({$result['http_code']}): $errMsg"];
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
                return ['success' => false, 'error' => 'Gemini returned no image. Model "' . $model . '" may not support image output.'];
            }

            // OpenAI-compatible (gpt-image-1-mini, gpt-image-1, gpt-image-1.5)
            $endpoint = $credentials['endpoint'] ?: 'https://api.openai.com/v1/images/generations';
            $headers = ['Authorization: Bearer ' . $key, 'Content-Type: application/json'];
            // Use smaller size for cost savings: 1024x1024 is cheapest
            // gpt-image-1-mini + 1024x1024 = ~$0.01/image
            $size = $credentials['size'] ?? '1024x1024';
            $payload = ['model' => $model, 'prompt' => $prompt, 'size' => $size, 'n' => 1, 'quality' => 'low'];

            $result = curlPost($endpoint, $payload, $headers, 120);
            $data = $result['data'] ?? [];

            if ($result['http_code'] >= 400) {
                $errMsg = 'Unknown error';
                if (is_array($data) && isset($data['error']['message'])) {
                    $errMsg = $data['error']['message'];
                } elseif (is_string($result['raw'])) {
                    $errDecoded = json_decode($result['raw'], true);
                    $errMsg = $errDecoded['error']['message'] ?? $result['raw'];
                }
                return ['success' => false, 'error' => "OpenAI Image Error ({$result['http_code']}): $errMsg"];
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
