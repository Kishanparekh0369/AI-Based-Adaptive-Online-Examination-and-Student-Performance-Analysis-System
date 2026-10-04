<?php

class GroqClient
{
    private $apiKey;
    private $baseUrl = 'https://api.groq.com/openai/v1';

    public function __construct($config)
    {
        $this->apiKey = $config['apiKey'] ?? '';
    }

    public function chat()
    {
        return new GroqChatEndpoint($this->apiKey, $this->baseUrl);
    }
}

class GroqChatEndpoint
{
    private $apiKey;
    private $baseUrl;

    public function __construct($apiKey, $baseUrl)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
    }

    public function completions()
    {
        return new GroqCompletionsEndpoint($this->apiKey, $this->baseUrl);
    }
}

class GroqCompletionsEndpoint
{
    private $apiKey;
    private $baseUrl;

    public function __construct($apiKey, $baseUrl)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
    }

    public function create($params)
    {
        $url = $this->baseUrl . '/chat/completions';

        $model = $params['model'] ?? 'openai/gpt-oss-20b';
        $payload = [
            'model' => $model,
            'messages' => $params['messages'] ?? [],
            'temperature' => $params['temperature'] ?? 0.7,
            'max_tokens' => $params['max_tokens'] ?? 500,
        ];

        if (isset($params['response_format'])) {
            $payload['response_format'] = $params['response_format'];
        }

        // Reasoning models (gpt-oss family) spend tokens thinking BEFORE answering.
        // Without this, long reasoning eats the whole max_tokens budget and the
        // visible `content` comes back EMPTY (finish_reason=length).
        if (!isset($params['reasoning_effort']) && stripos((string)$model, 'gpt-oss') !== false) {
            $payload['reasoning_effort'] = 'low';
        } elseif (isset($params['reasoning_effort'])) {
            $payload['reasoning_effort'] = $params['reasoning_effort'];
        }

        if (trim($this->apiKey) === '') {
            throw new Exception('Groq API key is missing.');
        }

        $lastError = '';
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init();
            $opts = [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                // Bypass stale machine-wide proxy variables for direct Groq API access.
                CURLOPT_PROXY => '',
                CURLOPT_NOPROXY => '*',
            ];
            $caBundle = __DIR__ . '/../config/cacert.pem';
            if (file_exists($caBundle)) {
                $opts[CURLOPT_CAINFO] = $caBundle;
            }
            curl_setopt_array($ch, $opts);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $lastError = 'Groq API cURL error: ' . $error;
            } else {
                $data = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $lastError = 'Groq API returned invalid JSON: ' . json_last_error_msg();
                } elseif ($httpCode >= 200 && $httpCode < 300) {
                    // Safety net: reasoning models sometimes return empty content
                    // when thinking consumed the token budget — retry leaner.
                    $content = trim((string)($data['choices'][0]['message']['content'] ?? ''));
                    if ($content === '' && $attempt < 3) {
                        $lastError = 'Groq API returned empty content (reasoning consumed budget).';
                        $payload['reasoning_effort'] = 'low';
                        $payload['max_tokens'] = min(2000, (int)$payload['max_tokens'] * 2);
                    } else {
                        return $data;
                    }
                } else {
                    $errMsg = $data['error']['message'] ?? 'HTTP ' . $httpCode;
                    $lastError = 'Groq API error: ' . $errMsg;
                    if ($httpCode < 500 && $httpCode != 429) {
                        break;
                    }
                }
            }

            if ($attempt < 3) {
                usleep(250000 * $attempt);
            }
        }

        throw new Exception($lastError ?: 'Groq API request failed.');
    }
}
