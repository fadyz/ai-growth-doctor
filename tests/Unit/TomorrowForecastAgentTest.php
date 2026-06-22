<?php

namespace Tests\Unit;

use App\Services\Ai\TomorrowForecastContextBuilder;
use App\Services\GrowthDoctor\Agents\AiAgentClient;
use App\Services\GrowthDoctor\Agents\TomorrowForecastAgent;
use Tests\TestCase;

class TomorrowForecastAgentTest extends TestCase
{
    public function testBuildRequestUsesCompactTomorrowForecastContext(): void
    {
        $client = $this->createMock(AiAgentClient::class);
        $client->method('outputLanguage')->willReturn('English');
        $client->method('prepareRequest')->willReturnCallback(function ($agentName, $systemPrompt, $expectedSchema, $agentContext, $requestMeta = []) {
            return compact('agentName', 'systemPrompt', 'expectedSchema', 'agentContext', 'requestMeta');
        });

        $builder = new TomorrowForecastContextBuilder();
        $agent = new TomorrowForecastAgent($client, $builder);

        $request = $agent->buildRequest([
            'checkpoint_meta' => [
                'app_name' => 'AGD',
                'window_start' => '2026-06-01',
                'window_end' => '2026-06-22',
                'timezone' => 'Asia/Jakarta',
            ],
            'tomorrow_forecast_metrics' => [
                'forecast_for_date' => '2026-06-23',
                'data_as_of_date' => '2026-06-22',
                'risk_flags' => [
                    'activation_risk' => 'watch',
                ],
                'predicted_metrics' => [
                    'activation' => [
                        'session_users' => ['point' => 100],
                    ],
                ],
            ],
            'forecast_evaluations' => [
                'status' => 'ready',
                'evaluated' => [
                    ['summary' => ['forecast_quality' => 'good']],
                ],
            ],
            'forecast_model_calibration' => [
                'status' => 'ready',
                'trust_score' => [
                    'updated_score' => 72,
                    'interpretation' => 'medium_trust',
                ],
                'decision_instruction' => [
                    'forecast_role' => 'supporting_guardrail',
                ],
            ],
            'activation_metrics' => [
                'metrics_7d' => [
                    'session_users' => 100,
                ],
            ],
        ]);

        $this->assertSame('2026-06-23', $request['agentContext']['tomorrow_forecast_metrics']['forecast_for_date']);
        $this->assertSame('2026-06-22', $request['agentContext']['tomorrow_forecast_metrics']['data_as_of_date']);
        $this->assertSame(['activation_risk' => 'watch'], $request['agentContext']['tomorrow_forecast_metrics']['risk_flags']);
        $this->assertArrayNotHasKey('predicted_metrics', $request['agentContext']['tomorrow_forecast_metrics']);
        $this->assertSame('compact', $request['requestMeta']['context_mode']);
        $this->assertSame(45, $request['requestMeta']['timeout_seconds']);
    }

    public function testApplyDeterministicFallbackConvertsProviderFailureIntoUsableForecast(): void
    {
        config(['ai_growth_doctor.ai.tomorrow_forecast_fallback_enabled' => true]);

        $client = $this->createMock(AiAgentClient::class);
        $builder = new TomorrowForecastContextBuilder();
        $agent = new TomorrowForecastAgent($client, $builder);

        $result = $agent->applyDeterministicFallback([
            'agent' => 'Tomorrow Forecast Agent',
            'status' => 'exception',
            'model' => 'qwen-plus',
            'error' => 'cURL error 28: Operation timed out after 1000 milliseconds with 0 bytes received',
            'response_metrics' => [
                'status' => 'timeout',
                'duration_ms' => 1000,
                'error_class' => 'RuntimeException',
            ],
        ], [
            'tomorrow_forecast_metrics' => [
                'forecast_for_date' => '2026-06-23',
                'data_as_of_date' => '2026-06-22',
                'risk_flags' => [
                    'activation_risk' => 'watch',
                    'retention_risk' => 'watch',
                    'habit_risk' => 'watch',
                    'monetization_sample' => 'low_sample',
                    'scaling_caution' => 'allow_cautious_test',
                ],
            ],
            'forecast_evaluations' => [
                'evaluated' => [
                    [
                        'summary' => [
                            'forecast_quality' => 'partially_correct',
                        ],
                    ],
                ],
            ],
            'forecast_model_calibration' => [
                'status' => 'ready',
                'decision_instruction' => [
                    'forecast_role' => 'can_strengthen_guardrail',
                ],
            ],
        ]);

        $this->assertSame('fallback', $result['status']);
        $this->assertSame('forecast_interpreted_deterministically', $result['result_status']);
        $this->assertTrue($result['decision_usable']);
        $this->assertSame('timeout', $result['llm_status']);
        $this->assertSame('forecast_interpreted_deterministically', $result['result']['status']);
        $this->assertStringContainsString('Monetization remains low-sample.', $result['summary']);
        $this->assertStringContainsString('Scaling should remain cautious and limited to controlled tests.', $result['summary']);
    }
}
