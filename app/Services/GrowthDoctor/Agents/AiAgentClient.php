<?php

namespace App\Services\GrowthDoctor\Agents;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAgentClient
{
    public function call(
        string $agentName,
        string $systemPrompt,
        array $expectedSchema,
        array $agentContext,
        array $requestMeta = []
    ): array {
        $preparedRequest = $this->buildRequestPayload($agentName, $systemPrompt, $expectedSchema, $agentContext, $requestMeta);

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
                (string) ($request['agent_name'] ?? $request['agentName'] ?? $key),
                (string) ($request['system_prompt'] ?? $request['systemPrompt'] ?? ''),
                (array) ($request['expected_schema'] ?? $request['expectedSchema'] ?? []),
                (array) ($request['agent_context'] ?? $request['agentContext'] ?? []),
                (array) ($request['request_meta'] ?? $request['requestMeta'] ?? [])
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
                'timeout' => $this->maxTimeoutFromPreparedRequests($preparedRequests),
                'http_errors' => false,
            ]);

            $promises = [];

            foreach ($preparedRequests as $key => $prepared) {
                $preparedRequests[$key]['started_at_epoch'] = microtime(true);
                $preparedRequests[$key]['started_at'] = date('Y-m-d H:i:s');
                $preparedRequests[$key]['request_metrics']['request_started_at'] = $preparedRequests[$key]['started_at'];

                $this->logRequestPayload($preparedRequests[$key]);

                $promises[$key] = $client->postAsync($prepared['chat_completions_url'], [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $prepared['api_key'],
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $prepared['http_payload'],
                    'timeout' => $prepared['timeout_seconds'],
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

                        $result = $this->buildThrowableFailureResult($preparedRequests[$key], $reason, 'parallel_http_pool');

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
                $results[$key] = $this->buildThrowableFailureResult($prepared, $reason, 'parallel_http_pool');
            }
        } catch (\Throwable $e) {
            foreach ($preparedRequests as $key => $prepared) {
                $results[$key] = $this->buildThrowableFailureResult($prepared, $e, 'parallel_http_pool_setup_failed');

                if ($onResult) {
                    $onResult($key, $results[$key]);
                }
            }
        }

        return $this->orderedResults($results, $requestOrder);
    }

    public function prepareRequest(
        string $agentName,
        string $systemPrompt,
        array $expectedSchema,
        array $agentContext,
        array $requestMeta = []
    ): array {
        return [
            'agent_name' => $agentName,
            'system_prompt' => $systemPrompt,
            'expected_schema' => $expectedSchema,
            'agent_context' => $agentContext,
            'request_meta' => $requestMeta,
        ];
    }

    public function outputLanguage(): string
    {
        $language = trim((string) config('ai_growth_doctor.ai.output_language', 'English'));

        return $language !== '' ? $language : 'English';
    }

    private function buildRequestPayload(
        string $agentName,
        string $systemPrompt,
        array $expectedSchema,
        array $agentContext,
        array $requestMeta = []
    ): array {
        if (empty($requestMeta['skip_generic_output_fields'])) {
            $systemPrompt = trim($systemPrompt . "\n\n" . $this->genericGrowthContractInstruction());
        } else {
            $systemPrompt = trim($systemPrompt);
        }
        if (empty($requestMeta['skip_generic_output_fields'])) {
            $expectedSchema = $this->addGenericOutputFields($expectedSchema);
        }
        $provider = $this->provider();
        $apiKey = $this->apiKey();
        $model = $this->model();
        $apiBaseUrl = $this->apiBaseUrl();
        $chatCompletionsUrl = $this->chatCompletionsUrl($apiBaseUrl);
        $timeoutSeconds = $this->resolveTimeoutSeconds($requestMeta);

        if (!$apiKey) {
            $diagnosis = 'AI API key is not configured.';

            if ($provider === 'qwen') {
                $diagnosis .= ' Set QWEN_API_KEY to enable AI agents.';
            } elseif ($provider === 'sumopod') {
                $diagnosis .= ' Set SUMOPOD_API_KEY to enable AI agents.';
            } else {
                $diagnosis .= ' Set OPENAI_API_KEY to enable AI agents.';
            }

            return [
                'status' => 'disabled',
                'result' => [
                    'agent' => $agentName,
                    'status' => 'disabled',
                    'diagnosis' => $diagnosis,
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
        $cacheTtlSeconds = (int) config('ai_growth_doctor.ai.agent_cache_ttl_seconds', 1800);

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

        $httpPayload = [
            'model' => $model,
            'messages' => $prompt,
            'temperature' => 0.2,
            'response_format' => [
                'type' => 'json_object',
            ],
        ];

        $payloadJson = json_encode($httpPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payloadBytes = strlen($payloadJson ?: '');
        $estimatedTokens = (int) ceil($payloadBytes / 4);
        $sharedContextKeys = array_values($requestMeta['shared_context_keys'] ?? array_keys($agentContext));
        $requestMetrics = [
            'run_id' => $requestMeta['run_id'] ?? null,
            'agent' => $agentName,
            'model' => $model,
            'provider' => $provider,
            'endpoint' => $chatCompletionsUrl,
            'base_url' => $apiBaseUrl,
            'payload_bytes' => $payloadBytes,
            'estimated_tokens' => $estimatedTokens,
            'message_count' => count($httpPayload['messages'] ?? []),
            'shared_context_keys' => $sharedContextKeys,
            'timeout_seconds' => $timeoutSeconds,
            'request_started_at' => null,
            'context_mode' => $requestMeta['context_mode'] ?? null,
        ];

        if ($cacheTtlSeconds > 0 && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                $cached['cache'] = [
                    'hit' => true,
                    'key' => $cacheKey,
                    'ttl_seconds' => $cacheTtlSeconds,
                ];
                $cached['request_metrics'] = $cached['request_metrics'] ?? $requestMetrics;

                return [
                    'status' => 'cached',
                    'result' => $cached,
                ];
            }
        }

        $prepared = [
            'status' => 'ready',
            'agent_name' => $agentName,
            'request_key' => null,
            'api_key' => $apiKey,
            'provider' => $provider,
            'model' => $model,
            'api_base_url' => $apiBaseUrl,
            'chat_completions_url' => $chatCompletionsUrl,
            'cache_key' => $cacheKey,
            'cache_ttl_seconds' => $cacheTtlSeconds,
            'timeout_seconds' => $timeoutSeconds,
            'http_payload' => $httpPayload,
            'request_meta' => $requestMeta,
            'request_metrics' => $requestMetrics,
        ];

        $this->maybeLogPayloadBody($prepared, $payloadJson ?: '');

        return $prepared;
    }

    private function genericGrowthContractInstruction(): string
    {
        return implode("\n", [
            'Generic Growth Metric Contract instruction:',
            'You are analyzing a mobile app growth system, not a fixed single app.',
            'Use app_profile to understand the app category, core action, workspace concept, and monetization model.',
            'Use generic_metrics_context as the primary growth metric layer.',
            'Use source_metrics_context and source_metric_refs only for audit and app-specific translation.',
            'Do not invent metrics.',
            'Do not assume the app is a calorie tracker unless app_profile says so.',
            'When possible, return both generic diagnosis/recommendation and app-specific diagnosis/recommendation.',
            'Example generic wording: Core action success from entry users is weak.',
            'Example app-specific wording: For this app, food logging success from session users is weak.',
            'For final decisions, distinguish reusable generic growth principles from app-specific execution guidance.',
        ]);
    }

    private function addGenericOutputFields(array $expectedSchema): array
    {
        foreach ([
            'generic_diagnosis' => 'Reusable growth diagnosis in generic metric language.',
            'app_specific_diagnosis' => 'App-specific translation based on app_profile.',
            'generic_recommendation' => 'Reusable growth recommendation in generic metric language.',
            'app_specific_recommendation' => 'App-specific execution guidance based on app_profile.',
        ] as $field => $description) {
            if (!array_key_exists($field, $expectedSchema)) {
                $expectedSchema[$field] = $description;
            }
        }

        return $expectedSchema;
    }

    private function callFromPreparedRequest(array $preparedRequest): array
    {
        try {
            $preparedRequest['started_at_epoch'] = microtime(true);
            $preparedRequest['started_at'] = date('Y-m-d H:i:s');
            $preparedRequest['request_metrics']['request_started_at'] = $preparedRequest['started_at'];

            $this->logRequestPayload($preparedRequest);

            $response = Http::withToken($preparedRequest['api_key'])
                ->timeout($preparedRequest['timeout_seconds'])
                ->post($preparedRequest['chat_completions_url'], $preparedRequest['http_payload']);

            $preparedRequest['finished_at_epoch'] = microtime(true);
            $preparedRequest['finished_at'] = date('Y-m-d H:i:s');

            return $this->handleHttpResponse($preparedRequest, $response);
        } catch (\Throwable $e) {
            $preparedRequest['finished_at_epoch'] = microtime(true);
            $preparedRequest['finished_at'] = date('Y-m-d H:i:s');

            return $this->buildThrowableFailureResult($preparedRequest, $e, 'single_http_call');
        }
    }

    private function handleHttpResponse(array $preparedRequest, $response): array
    {
        $executionMode = $preparedRequest['request_key'] !== null ? 'parallel_http_pool' : 'single_http_call';

        if (!$response || !method_exists($response, 'successful')) {
            $result = $this->baseFailureResult(
                $preparedRequest,
                'error',
                'provider_error',
                'No HTTP response returned by AI client.',
                $executionMode
            );

            $this->logResponseOutcome($preparedRequest, $result);

            return $result;
        }

        $responseBody = (string) $response->body();
        $httpStatus = method_exists($response, 'status') ? $response->status() : null;

        if (!$response->successful()) {
            $result = $this->baseFailureResult(
                $preparedRequest,
                'error',
                $this->classifyHttpFailureStatus($httpStatus),
                $responseBody,
                $executionMode,
                [
                    'http_status' => $httpStatus,
                    'response_bytes' => strlen($responseBody),
                ]
            );

            $this->logResponseOutcome($preparedRequest, $result);

            return $result;
        }

        $content = $this->extractAiContent($response->json());
        $decoded = $this->decodeAiJsonContent($content);

        if (!$decoded) {
            $result = $this->baseFailureResult(
                $preparedRequest,
                'invalid_json',
                'parse_error',
                'AI response could not be parsed into JSON.',
                $executionMode,
                [
                    'http_status' => $httpStatus,
                    'response_bytes' => strlen(is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                    'raw_response' => $content,
                    'raw_response_type' => gettype($content),
                ]
            );

            $this->logResponseOutcome($preparedRequest, $result);

            return $result;
        }

        $result = [
            'agent' => $preparedRequest['agent_name'],
            'status' => 'active',
            'execution' => $this->buildExecution($preparedRequest, $executionMode),
            'model' => $preparedRequest['model'],
            'provider' => $preparedRequest['provider'],
            'api_base_url' => $preparedRequest['api_base_url'] ?? null,
            'result' => $decoded,
            'cache' => [
                'hit' => false,
                'key' => $preparedRequest['cache_key'],
                'ttl_seconds' => $preparedRequest['cache_ttl_seconds'],
            ],
            'request_metrics' => $preparedRequest['request_metrics'],
            'response_metrics' => [
                'status' => 'success',
                'duration_ms' => $this->durationMs($preparedRequest),
                'http_status' => $httpStatus,
                'response_bytes' => strlen($responseBody),
            ],
        ];

        if (($preparedRequest['cache_ttl_seconds'] ?? 0) > 0) {
            Cache::put($preparedRequest['cache_key'], $result, $preparedRequest['cache_ttl_seconds']);
        }

        $this->logResponseOutcome($preparedRequest, $result);

        return $result;
    }

    private function handleGuzzleResponse(array $preparedRequest, $response): array
    {
        if (!$response || !method_exists($response, 'getStatusCode')) {
            $result = $this->baseFailureResult(
                $preparedRequest,
                'error',
                'provider_error',
                'No HTTP response returned by Guzzle AI client.',
                'parallel_http_pool'
            );

            $this->logResponseOutcome($preparedRequest, $result);

            return $result;
        }

        $statusCode = (int) $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            $result = $this->baseFailureResult(
                $preparedRequest,
                'error',
                $this->classifyHttpFailureStatus($statusCode),
                $body,
                'parallel_http_pool',
                [
                    'http_status' => $statusCode,
                    'response_bytes' => strlen($body),
                ]
            );

            $this->logResponseOutcome($preparedRequest, $result);

            return $result;
        }

        $json = json_decode($body, true);
        $content = $this->extractAiContent(is_array($json) ? $json : []);
        $decoded = $this->decodeAiJsonContent($content);

        if (!$decoded) {
            $result = $this->baseFailureResult(
                $preparedRequest,
                'invalid_json',
                'parse_error',
                'AI response could not be parsed into JSON.',
                'parallel_http_pool',
                [
                    'http_status' => $statusCode,
                    'response_bytes' => strlen(is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                    'raw_response' => $content,
                    'raw_response_type' => gettype($content),
                ]
            );

            $this->logResponseOutcome($preparedRequest, $result);

            return $result;
        }

        $result = [
            'agent' => $preparedRequest['agent_name'],
            'status' => 'active',
            'execution' => $this->buildExecution($preparedRequest, 'parallel_http_pool'),
            'model' => $preparedRequest['model'],
            'provider' => $preparedRequest['provider'],
            'api_base_url' => $preparedRequest['api_base_url'] ?? null,
            'result' => $decoded,
            'cache' => [
                'hit' => false,
                'key' => $preparedRequest['cache_key'],
                'ttl_seconds' => $preparedRequest['cache_ttl_seconds'],
            ],
            'request_metrics' => $preparedRequest['request_metrics'],
            'response_metrics' => [
                'status' => 'success',
                'duration_ms' => $this->durationMs($preparedRequest),
                'http_status' => $statusCode,
                'response_bytes' => strlen($body),
            ],
        ];

        if (($preparedRequest['cache_ttl_seconds'] ?? 0) > 0) {
            Cache::put($preparedRequest['cache_key'], $result, $preparedRequest['cache_ttl_seconds']);
        }

        $this->logResponseOutcome($preparedRequest, $result);

        return $result;
    }

    private function buildThrowableFailureResult(array $preparedRequest, $reason, string $executionMode): array
    {
        $message = $reason instanceof \Throwable ? $reason->getMessage() : (string) $reason;
        $errorClass = $reason instanceof \Throwable ? get_class($reason) : null;
        $providerStatus = $this->classifyThrowableStatus($message, $reason);

        $result = $this->baseFailureResult(
            $preparedRequest,
            'exception',
            $providerStatus,
            $message,
            $executionMode,
            [
                'error_class' => $errorClass,
            ]
        );

        $this->logResponseOutcome($preparedRequest, $result);

        return $result;
    }

    private function baseFailureResult(
        array $preparedRequest,
        string $status,
        string $providerStatus,
        string $errorMessage,
        string $executionMode,
        array $extra = []
    ): array {
        return array_merge([
            'agent' => $preparedRequest['agent_name'],
            'status' => $status,
            'execution' => $this->buildExecution($preparedRequest, $executionMode),
            'model' => $preparedRequest['model'] ?? null,
            'provider' => $preparedRequest['provider'] ?? null,
            'api_base_url' => $preparedRequest['api_base_url'] ?? null,
            'error' => $errorMessage,
            'cache' => [
                'hit' => false,
                'key' => $preparedRequest['cache_key'] ?? null,
                'ttl_seconds' => $preparedRequest['cache_ttl_seconds'] ?? null,
            ],
            'request_metrics' => $preparedRequest['request_metrics'] ?? null,
            'response_metrics' => [
                'status' => $providerStatus,
                'duration_ms' => $this->durationMs($preparedRequest),
                'http_status' => $extra['http_status'] ?? null,
                'response_bytes' => $extra['response_bytes'] ?? null,
                'error_class' => $extra['error_class'] ?? null,
                'error_message' => $this->truncateForLog($errorMessage),
            ],
        ], $extra);
    }

    private function buildExecution(array $preparedRequest, string $executionMode): array
    {
        return [
            'mode' => $executionMode,
            'request_key' => $preparedRequest['request_key'] ?? null,
            'parallel_pool' => $preparedRequest['request_key'] !== null,
            'request_started_at' => $preparedRequest['started_at'] ?? null,
            'request_finished_at' => $preparedRequest['finished_at'] ?? null,
            'request_duration_ms' => $this->durationMs($preparedRequest),
        ];
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

    private function provider(): string
    {
        return (string) config('ai_growth_doctor.ai.provider', env('AI_PROVIDER', 'openai'));
    }

    private function apiKey(): ?string
    {
        $provider = $this->provider();

        if ($provider === 'qwen') {
            return config('services.qwen.api_key') ?: env('QWEN_API_KEY');
        }

        if ($provider === 'sumopod') {
            return config('services.sumopod.api_key') ?: env('SUMOPOD_API_KEY');
        }

        return config('services.openai.api_key') ?: env('OPENAI_API_KEY');
    }

    private function model(): string
    {
        $provider = $this->provider();

        if ($provider === 'qwen') {
            return config('services.qwen.model') ?: 'qwen-plus';
        }

        if ($provider === 'sumopod') {
            return config('services.sumopod.model') ?: 'gpt-5.4-nano';
        }

        return config('services.openai.model') ?: 'gpt-5-nano';
    }

    private function apiBaseUrl(): string
    {
        $provider = $this->provider();

        if ($provider === 'qwen') {
            return rtrim((string) (config('services.qwen.base_url') ?: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'), '/');
        }

        if ($provider === 'sumopod') {
            return rtrim((string) (config('services.sumopod.base_url') ?: 'https://ai.sumopod.com'), '/');
        }

        return rtrim((string) (config('services.openai.base_url') ?: 'https://api.openai.com/v1'), '/');
    }

    private function chatCompletionsUrl(string $apiBaseUrl): string
    {
        if (substr($apiBaseUrl, -3) === '/v1') {
            return $apiBaseUrl . '/chat/completions';
        }

        return $apiBaseUrl . '/v1/chat/completions';
    }

    private function resolveTimeoutSeconds(array $requestMeta): int
    {
        $timeout = (int) ($requestMeta['timeout_seconds'] ?? config('ai_growth_doctor.ai.agent_timeout_seconds', 90));

        return $timeout > 0 ? $timeout : 90;
    }

    private function maxTimeoutFromPreparedRequests(array $preparedRequests): int
    {
        $timeouts = array_map(function (array $preparedRequest) {
            return (int) ($preparedRequest['timeout_seconds'] ?? 90);
        }, $preparedRequests);

        return max($timeouts ?: [90]);
    }

    private function logRequestPayload(array $preparedRequest): void
    {
        if (!config('ai_growth_doctor.ai.log_agent_payload_size', true)) {
            return;
        }

        $requestMetrics = $preparedRequest['request_metrics'] ?? [];
        $requestMetrics['request_started_at'] = $preparedRequest['started_at'] ?? null;

        Log::info('ai_agent_request_payload_size', [
            'run_id' => $requestMetrics['run_id'] ?? null,
            'agent_name' => $preparedRequest['agent_name'] ?? null,
            'model' => $preparedRequest['model'] ?? null,
            'provider' => $preparedRequest['provider'] ?? null,
            'endpoint' => $preparedRequest['chat_completions_url'] ?? null,
            'base_url' => $preparedRequest['api_base_url'] ?? null,
            'payload_bytes' => $requestMetrics['payload_bytes'] ?? null,
            'estimated_tokens' => $requestMetrics['estimated_tokens'] ?? null,
            'message_count' => $requestMetrics['message_count'] ?? null,
            'shared_context_keys' => $requestMetrics['shared_context_keys'] ?? [],
            'timeout_seconds' => $requestMetrics['timeout_seconds'] ?? null,
            'request_started_at' => $requestMetrics['request_started_at'] ?? null,
            'context_mode' => $requestMetrics['context_mode'] ?? null,
        ]);
    }

    private function maybeLogPayloadBody(array $preparedRequest, string $payloadJson): void
    {
        if (!config('app.debug') || !config('ai_growth_doctor.ai.log_agent_payload_body', false)) {
            return;
        }

        Log::debug('ai_agent_request_payload_body', [
            'run_id' => $preparedRequest['request_metrics']['run_id'] ?? null,
            'agent_name' => $preparedRequest['agent_name'] ?? null,
            'model' => $preparedRequest['model'] ?? null,
            'provider' => $preparedRequest['provider'] ?? null,
            'payload' => $payloadJson,
        ]);
    }

    private function logResponseOutcome(array $preparedRequest, array $result): void
    {
        $responseMetrics = $result['response_metrics'] ?? [];
        $payload = [
            'run_id' => $preparedRequest['request_metrics']['run_id'] ?? null,
            'agent_name' => $preparedRequest['agent_name'] ?? null,
            'model' => $preparedRequest['model'] ?? null,
            'provider' => $preparedRequest['provider'] ?? null,
            'duration_ms' => $responseMetrics['duration_ms'] ?? null,
            'status' => $responseMetrics['status'] ?? null,
            'http_status' => $responseMetrics['http_status'] ?? null,
            'response_bytes' => $responseMetrics['response_bytes'] ?? null,
            'error_class' => $responseMetrics['error_class'] ?? null,
            'error_message' => $responseMetrics['error_message'] ?? null,
        ];

        if (($responseMetrics['status'] ?? 'success') === 'success') {
            Log::info('ai_agent_response_timing', $payload);

            return;
        }

        Log::warning('ai_agent_provider_error', $payload);
    }

    private function classifyThrowableStatus(string $message, $reason): string
    {
        $haystack = strtolower($message);

        if (strpos($haystack, 'curl error 28') !== false || strpos($haystack, 'timed out') !== false || strpos($haystack, 'timeout') !== false) {
            return 'timeout';
        }

        if (strpos($haystack, 'connection') !== false || strpos($haystack, 'could not resolve host') !== false || strpos($haystack, 'failed to connect') !== false) {
            return 'provider_error';
        }

        if ($reason instanceof \JsonException) {
            return 'parse_error';
        }

        return 'provider_error';
    }

    private function classifyHttpFailureStatus(?int $httpStatus): string
    {
        if ($httpStatus === null) {
            return 'provider_error';
        }

        if ($httpStatus === 408 || $httpStatus === 504) {
            return 'timeout';
        }

        if ($httpStatus >= 500) {
            return 'provider_error';
        }

        return 'provider_error';
    }

    private function truncateForLog(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        return mb_substr($message, 0, 500);
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

        if (!is_string($content) || trim($content) === '') {
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
