<?php

namespace App\Services\GrowthDoctor\Agents;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AiAgentClient
{
    public function call(string $agentName, string $systemPrompt, array $expectedSchema, array $agentContext): array
    {
        $preparedRequest = $this->buildRequestPayload($agentName, $systemPrompt, $expectedSchema, $agentContext);

        if ($preparedRequest['status'] === 'disabled') {
            return $preparedRequest['result'];
        }

        if ($preparedRequest['status'] === 'cached') {
            return $preparedRequest['result'];
        }

        return $this->callFromPreparedRequest($preparedRequest);
    }

    public function callMany(array $requests, ?callable $onResult = null): array
    {
        $preparedRequests = [];
        $results = [];
        $requestOrder = array_keys($requests);

        foreach ($requests as $key => $request) {
            $prepared = $this->buildRequestPayload(
                (string)($request['agent_name'] ?? $request['agentName'] ?? $key),
                (string)($request['system_prompt'] ?? $request['systemPrompt'] ?? ''),
                (array)($request['expected_schema'] ?? $request['expectedSchema'] ?? []),
                (array)($request['agent_context'] ?? $request['agentContext'] ?? [])
            );

            $prepared['request_key'] = $key;

            if (in_array($prepared['status'], ['disabled', 'cached'], true)) {
                $results[$key] = $this->attachExecutionMetadata($prepared['result'], [
                    'mode' => $prepared['status'],
                    'request_key' => $key,
                    'parallel_pool' => false,
                ]);

                if ($onResult) {
                    $onResult($key, $results[$key]);
                }

                continue;
            }

            $preparedRequests[$key] = $prepared;
        }

        if (empty($preparedRequests)) {
            return $results;
        }

        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 120,
                'http_errors' => false,
            ]);

            $promises = [];

            foreach ($preparedRequests as $key => $prepared) {
                $preparedRequests[$key]['started_at_epoch'] = microtime(true);
                $preparedRequests[$key]['started_at'] = date('Y-m-d H:i:s');

                $promises[$key] = $client->postAsync($prepared['chat_completions_url'], [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $prepared['api_key'],
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $prepared['http_payload'],
                ])->then(
                    function ($response) use (&$preparedRequests, $key, $onResult) {
                        $preparedRequests[$key]['finished_at_epoch'] = microtime(true);
                        $preparedRequests[$key]['finished_at'] = date('Y-m-d H:i:s');

                        $result = $this->handleGuzzleResponse($preparedRequests[$key], $response);

                        if ($onResult) {
                            $onResult($key, $result);
                        }

                        return $result;
                    },
                    function ($reason) use (&$preparedRequests, $key, $onResult) {
                        $preparedRequests[$key]['finished_at_epoch'] = microtime(true);
                        $preparedRequests[$key]['finished_at'] = date('Y-m-d H:i:s');

                        $result = [
                            'agent' => $preparedRequests[$key]['agent_name'],
                            'status' => 'exception',
                            'execution' => [
                                'mode' => 'parallel_http_pool',
                                'request_key' => $preparedRequests[$key]['request_key'] ?? $key,
                                'parallel_pool' => true,
                                'request_started_at' => $preparedRequests[$key]['started_at'] ?? null,
                                'request_finished_at' => $preparedRequests[$key]['finished_at'] ?? null,
                                'request_duration_ms' => $this->durationMs($preparedRequests[$key]),
                            ],
                            'error' => $reason instanceof \Throwable ? $reason->getMessage() : (string) $reason,
                            'cache' => [
                                'hit' => false,
                                'key' => $preparedRequests[$key]['cache_key'],
                                'ttl_seconds' => $preparedRequests[$key]['cache_ttl_seconds'],
                            ],
                        ];

                        if ($onResult) {
                            $onResult($key, $result);
                        }

                        return $result;
                    }
                );
            }

            $settledResponses = \GuzzleHttp\Promise\Utils::settle($promises)->wait();

            foreach ($preparedRequests as $key => $prepared) {
                $settled = $settledResponses[$key] ?? null;

                if (($settled['state'] ?? null) === 'fulfilled' && is_array($settled['value'] ?? null)) {
                    $results[$key] = $settled['value'];
                    continue;
                }

                $reason = $settled['reason'] ?? null;
                $results[$key] = [
                    'agent' => $prepared['agent_name'],
                    'status' => 'exception',
                    'execution' => [
                        'mode' => 'parallel_http_pool',
                        'request_key' => $prepared['request_key'] ?? $key,
                        'parallel_pool' => true,
                        'request_started_at' => $prepared['started_at'] ?? null,
                        'request_finished_at' => $prepared['finished_at'] ?? null,
                        'request_duration_ms' => $this->durationMs($prepared),
                    ],
                    'error' => $reason instanceof \Throwable ? $reason->getMessage() : (string) $reason,
                    'cache' => [
                        'hit' => false,
                        'key' => $prepared['cache_key'],
                        'ttl_seconds' => $prepared['cache_ttl_seconds'],
                    ],
                ];
            }
        } catch (\Throwable $e) {
            foreach ($preparedRequests as $key => $prepared) {
                $results[$key] = [
                    'agent' => $prepared['agent_name'],
                    'status' => 'exception',
                    'execution' => [
                        'mode' => 'parallel_http_pool_setup_failed',
                        'request_key' => $prepared['request_key'] ?? $key,
                        'parallel_pool' => true,
                        'request_started_at' => $prepared['started_at'] ?? null,
                        'request_finished_at' => $prepared['finished_at'] ?? null,
                        'request_duration_ms' => $this->durationMs($prepared),
                    ],
                    'error' => $e->getMessage(),
                    'cache' => [
                        'hit' => false,
                        'key' => $prepared['cache_key'],
                        'ttl_seconds' => $prepared['cache_ttl_seconds'],
                    ],
                ];
                if ($onResult) {
                    $onResult($key, $results[$key]);
                }
            }
        }
        return $this->orderedResults($results, $requestOrder);
    }

    private function handleGuzzleResponse(array $preparedRequest, $response): array
    {
        if (!$response || !method_exists($response, 'getStatusCode')) {
            return [
                'agent' => $preparedRequest['agent_name'],
                'status' => 'error',
                'execution' => [
                    'mode' => 'parallel_http_pool',
                    'request_key' => $preparedRequest['request_key'] ?? null,
                    'parallel_pool' => true,
                    'request_started_at' => $preparedRequest['started_at'] ?? null,
                    'request_finished_at' => $preparedRequest['finished_at'] ?? null,
                    'request_duration_ms' => $this->durationMs($preparedRequest),
                ],
                'error' => 'No HTTP response returned by Guzzle AI client.',
                'cache' => [
                    'hit' => false,
                    'key' => $preparedRequest['cache_key'],
                    'ttl_seconds' => $preparedRequest['cache_ttl_seconds'],
                ],
            ];
        }

        $statusCode = (int) $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            return [
                'agent' => $preparedRequest['agent_name'],
                'status' => 'error',
                'execution' => [
                    'mode' => 'parallel_http_pool',
                    'request_key' => $preparedRequest['request_key'] ?? null,
                    'parallel_pool' => true,
                    'request_started_at' => $preparedRequest['started_at'] ?? null,
                    'request_finished_at' => $preparedRequest['finished_at'] ?? null,
                    'request_duration_ms' => $this->durationMs($preparedRequest),
                ],
                'error' => $body,
                'cache' => [
                    'hit' => false,
                    'key' => $preparedRequest['cache_key'],
                    'ttl_seconds' => $preparedRequest['cache_ttl_seconds'],
                ],
            ];
        }

        $json = json_decode($body, true);
        $content = $this->extractAiContent(is_array($json) ? $json : []);
        $decoded = $this->decodeAiJsonContent($content);

        if (!$decoded) {
            return [
                'agent' => $preparedRequest['agent_name'],
                'status' => 'invalid_json',
                'execution' => [
                    'mode' => 'parallel_http_pool',
                    'request_key' => $preparedRequest['request_key'] ?? null,
                    'parallel_pool' => true,
                    'request_started_at' => $preparedRequest['started_at'] ?? null,
                    'request_finished_at' => $preparedRequest['finished_at'] ?? null,
                    'request_duration_ms' => $this->durationMs($preparedRequest),
                ],
                'raw_response' => $content,
                'raw_response_type' => gettype($content),
                'cache' => [
                    'hit' => false,
                    'key' => $preparedRequest['cache_key'],
                    'ttl_seconds' => $preparedRequest['cache_ttl_seconds'],
                ],
            ];
        }

        $result = [
            'agent' => $preparedRequest['agent_name'],
            'status' => 'active',
            'execution' => [
                'mode' => 'parallel_http_pool',
                'request_key' => $preparedRequest['request_key'] ?? null,
                'parallel_pool' => true,
                'request_started_at' => $preparedRequest['started_at'] ?? null,
                'request_finished_at' => $preparedRequest['finished_at'] ?? null,
                'request_duration_ms' => $this->durationMs($preparedRequest),
            ],
            'model' => $preparedRequest['model'],
            'api_base_url' => $preparedRequest['api_base_url'] ?? null,
            'result' => $decoded,
            'cache' => [
                'hit' => false,
                'key' => $preparedRequest['cache_key'],
                'ttl_seconds' => $preparedRequest['cache_ttl_seconds'],
            ],
        ];

        if (($preparedRequest['cache_ttl_seconds'] ?? 0) > 0) {
            Cache::put($preparedRequest['cache_key'], $result, $preparedRequest['cache_ttl_seconds']);
        }

        return $result;
    }

    public function prepareRequest(string $agentName, string $systemPrompt, array $expectedSchema, array $agentContext): array
    {
        return [
            'agent_name' => $agentName,
            'system_prompt' => $systemPrompt,
            'expected_schema' => $expectedSchema,
            'agent_context' => $agentContext,
        ];
    }

    private function buildRequestPayload(string $agentName, string $systemPrompt, array $expectedSchema, array $agentContext): array
    {
        $apiKey = $this->apiKey();
        $model = $this->model();
        $apiBaseUrl = $this->apiBaseUrl();
        $chatCompletionsUrl = $this->chatCompletionsUrl($apiBaseUrl);

        if (!$apiKey) {
            return [
                'status' => 'disabled',
                'result' => [
                    'agent' => $agentName,
                    'status' => 'disabled',
                    'diagnosis' => 'AI API key belum diset. Isi SUMOPOD_API_KEY atau OPENAI_API_KEY agar Agent AI aktif.',
                ],
            ];
        }

        $cachePayload = [
            'agent_name' => $agentName,
            'model' => $model,
            'api_base_url' => $apiBaseUrl,
            'system_prompt' => $systemPrompt,
            'expected_schema' => $this->normalizeForCacheKey($expectedSchema),
            'agent_context' => $this->normalizeForCacheKey($agentContext),
        ];

        $cacheKey = 'ai_growth_doctor:agent:' . sha1(json_encode($cachePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $cacheTtlSeconds = (int) env('AI_AGENT_CACHE_TTL_SECONDS', 1800);

        if ($cacheTtlSeconds > 0 && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                $cached['cache'] = [
                    'hit' => true,
                    'key' => $cacheKey,
                    'ttl_seconds' => $cacheTtlSeconds,
                ];

                return [
                    'status' => 'cached',
                    'result' => $cached,
                ];
            }
        }

        $prompt = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'task' => 'Analyze this mobile app growth checkpoint according to your specialist role.',
                    'expected_output_schema' => $expectedSchema,
                    'agent_context' => $agentContext,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
        ];

        return [
            'status' => 'ready',
            'agent_name' => $agentName,
            'request_key' => null,
            'api_key' => $apiKey,
            'model' => $model,
            'api_base_url' => $apiBaseUrl,
            'chat_completions_url' => $chatCompletionsUrl,
            'cache_key' => $cacheKey,
            'cache_ttl_seconds' => $cacheTtlSeconds,
            'http_payload' => [
                'model' => $model,
                'messages' => $prompt,
                'temperature' => 0.2,
                'response_format' => [
                    'type' => 'json_object',
                ],
            ],
        ];
    }

    private function callFromPreparedRequest(array $preparedRequest): array
    {
        try {
            $preparedRequest['started_at_epoch'] = microtime(true);
            $preparedRequest['started_at'] = date('Y-m-d H:i:s');

            $response = Http::withToken($preparedRequest['api_key'])
                ->timeout(120)
                ->post($preparedRequest['chat_completions_url'], $preparedRequest['http_payload']);

            $preparedRequest['finished_at_epoch'] = microtime(true);
            $preparedRequest['finished_at'] = date('Y-m-d H:i:s');

            return $this->handleHttpResponse($preparedRequest, $response);
        } catch (\Throwable $e) {
            return [
                'agent' => $preparedRequest['agent_name'],
                'status' => 'exception',
                'execution' => [
                    'mode' => 'single_http_call',
                    'request_key' => $preparedRequest['request_key'] ?? null,
                    'parallel_pool' => false,
                    'request_started_at' => $preparedRequest['started_at'] ?? null,
                    'request_finished_at' => $preparedRequest['finished_at'] ?? date('Y-m-d H:i:s'),
                    'request_duration_ms' => $this->durationMs($preparedRequest),
                ],
                'error' => $e->getMessage(),
                'cache' => [
                    'hit' => false,
                    'key' => $preparedRequest['cache_key'],
                    'ttl_seconds' => $preparedRequest['cache_ttl_seconds'],
                ],
            ];
        }
    }

    private function handleHttpResponse(array $preparedRequest, $response): array
    {
        if (!$response || !method_exists($response, 'successful')) {
            return [
                'agent' => $preparedRequest['agent_name'],
                'status' => 'error',
                'execution' => [
                    'mode' => $preparedRequest['request_key'] !== null ? 'parallel_http_pool' : 'single_http_call',
                    'request_key' => $preparedRequest['request_key'] ?? null,
                    'parallel_pool' => $preparedRequest['request_key'] !== null,
                    'request_started_at' => $preparedRequest['started_at'] ?? null,
                    'request_finished_at' => $preparedRequest['finished_at'] ?? null,
                    'request_duration_ms' => $this->durationMs($preparedRequest),
                ],
                'error' => 'No HTTP response returned by AI client.',
                'cache' => [
                    'hit' => false,
                    'key' => $preparedRequest['cache_key'],
                    'ttl_seconds' => $preparedRequest['cache_ttl_seconds'],
                ],
            ];
        }

        if (!$response->successful()) {
            return [
                'agent' => $preparedRequest['agent_name'],
                'status' => 'error',
                'execution' => [
                    'mode' => $preparedRequest['request_key'] !== null ? 'parallel_http_pool' : 'single_http_call',
                    'request_key' => $preparedRequest['request_key'] ?? null,
                    'parallel_pool' => $preparedRequest['request_key'] !== null,
                    'request_started_at' => $preparedRequest['started_at'] ?? null,
                    'request_finished_at' => $preparedRequest['finished_at'] ?? null,
                    'request_duration_ms' => $this->durationMs($preparedRequest),
                ],
                'error' => $response->body(),
                'cache' => [
                    'hit' => false,
                    'key' => $preparedRequest['cache_key'],
                    'ttl_seconds' => $preparedRequest['cache_ttl_seconds'],
                ],
            ];
        }

        $content = $this->extractAiContent($response->json());
        $decoded = $this->decodeAiJsonContent($content);

        if (!$decoded) {
            return [
                'agent' => $preparedRequest['agent_name'],
                'status' => 'invalid_json',
                'execution' => [
                    'mode' => $preparedRequest['request_key'] !== null ? 'parallel_http_pool' : 'single_http_call',
                    'request_key' => $preparedRequest['request_key'] ?? null,
                    'parallel_pool' => $preparedRequest['request_key'] !== null,
                    'request_started_at' => $preparedRequest['started_at'] ?? null,
                    'request_finished_at' => $preparedRequest['finished_at'] ?? null,
                    'request_duration_ms' => $this->durationMs($preparedRequest),
                ],
                'raw_response' => $content,
                'raw_response_type' => gettype($content),
                'cache' => [
                    'hit' => false,
                    'key' => $preparedRequest['cache_key'],
                    'ttl_seconds' => $preparedRequest['cache_ttl_seconds'],
                ],
            ];
        }

        $result = [
            'agent' => $preparedRequest['agent_name'],
            'status' => 'active',
            'execution' => [
                'mode' => $preparedRequest['request_key'] !== null ? 'parallel_http_pool' : 'single_http_call',
                'request_key' => $preparedRequest['request_key'] ?? null,
                'parallel_pool' => $preparedRequest['request_key'] !== null,
                'request_started_at' => $preparedRequest['started_at'] ?? null,
                'request_finished_at' => $preparedRequest['finished_at'] ?? null,
                'request_duration_ms' => $this->durationMs($preparedRequest),
            ],
            'model' => $preparedRequest['model'],
            'api_base_url' => $preparedRequest['api_base_url'] ?? null,
            'result' => $decoded,
            'cache' => [
                'hit' => false,
                'key' => $preparedRequest['cache_key'],
                'ttl_seconds' => $preparedRequest['cache_ttl_seconds'],
            ],
        ];

        if (($preparedRequest['cache_ttl_seconds'] ?? 0) > 0) {
            Cache::put($preparedRequest['cache_key'], $result, $preparedRequest['cache_ttl_seconds']);
        }

        return $result;
    }

    private function attachExecutionMetadata(array $result, array $execution): array
    {
        $result['execution'] = $execution;

        return $result;
    }

    private function orderedResults(array $results, array $requestOrder): array
    {
        $ordered = [];

        foreach ($requestOrder as $key) {
            if (array_key_exists($key, $results)) {
                $ordered[$key] = $results[$key];
            }
        }

        foreach ($results as $key => $result) {
            if (!array_key_exists($key, $ordered)) {
                $ordered[$key] = $result;
            }
        }

        return $ordered;
    }

    private function normalizeForCacheKey($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (in_array($key, [
                'cache',
                'analyzed_at',
                'generated_at',
                'created_at',
                'updated_at',
                'timestamp',
                'key',
                'hit',
                'ttl_seconds',
            ], true)) {
                continue;
            }

            $normalized[$key] = $this->normalizeForCacheKey($item);
        }

        if ($this->isAssoc($normalized)) {
            ksort($normalized);
        }

        return $normalized;
    }

    private function apiKey(): ?string
    {
        return config('services.sumopod.api_key')
            ?: env('SUMOPOD_API_KEY')
            ?: config('services.openai.api_key')
            ?: env('OPENAI_API_KEY');
    }

    private function model(): string
    {
        return config('services.sumopod.model')
            ?: env('SUMOPOD_MODEL')
            ?: config('services.openai.model')
            ?: env('OPENAI_MODEL')
            ?: 'gpt-5-nano';
    }

    private function apiBaseUrl(): string
    {
        return rtrim(
            config('services.sumopod.base_url')
                ?: env('SUMOPOD_BASE_URL')
                ?: config('services.openai.base_url')
                ?: env('OPENAI_BASE_URL')
                ?: 'https://api.openai.com/v1',
            '/'
        );
    }

    private function chatCompletionsUrl(string $apiBaseUrl): string
    {
        if (substr($apiBaseUrl, -3) === '/v1') {
            return $apiBaseUrl . '/chat/completions';
        }

        return $apiBaseUrl . '/v1/chat/completions';
    }

    private function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function extractAiContent($responsePayload)
    {
        if (is_array($responsePayload)) {
            if (isset($responsePayload['choices'][0]['message']['content'])) {
                return $responsePayload['choices'][0]['message']['content'];
            }

            if (isset($responsePayload['result']['choices'][0]['message']['content'])) {
                return $responsePayload['result']['choices'][0]['message']['content'];
            }

            return $responsePayload;
        }

        return $responsePayload;
    }

    private function decodeAiJsonContent($content): ?array
    {
        if (is_array($content)) {
            if (isset($content['choices'][0]['message']['content'])) {
                return $this->decodeAiJsonContent($content['choices'][0]['message']['content']);
            }

            return $content;
        }

        if (!is_string($content)) {
            return null;
        }

        $decoded = json_decode($content, true);

        if (is_array($decoded) && isset($decoded['choices'][0]['message']['content'])) {
            return $this->decodeAiJsonContent($decoded['choices'][0]['message']['content']);
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function durationMs(array $preparedRequest): ?int
    {
        if (!isset($preparedRequest['started_at_epoch'], $preparedRequest['finished_at_epoch'])) {
            return null;
        }

        return (int) round(($preparedRequest['finished_at_epoch'] - $preparedRequest['started_at_epoch']) * 1000);
    }
}