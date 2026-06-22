<?php

namespace Tests\Unit;

use App\Services\Ai\TomorrowForecastContextBuilder;
use PHPUnit\Framework\TestCase;

class TomorrowForecastContextBuilderTest extends TestCase
{
    public function testBuildCompactOmitsLargeRawContextAndKeepsExpectedSummaryFields(): void
    {
        $builder = new TomorrowForecastContextBuilder();

        $compact = $builder->buildCompact([
            'checkpoint_meta' => [
                'app_name' => 'AI Growth Doctor',
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
                'data_windows' => [
                    'completeness_guard' => [
                        'status' => 'healthy',
                    ],
                ],
                'predicted_metrics' => ['should_not' => 'be_full_prompt_context_gate'],
            ],
            'forecast_evaluations' => [
                'evaluated_count' => 4,
                'pending_count' => 1,
                'skipped_count' => 2,
                'evaluated' => [
                    [
                        'summary' => [
                            'forecast_quality' => 'partially_correct',
                        ],
                    ],
                ],
            ],
            'forecast_model_calibration' => [
                'trust_score' => [
                    'updated_score' => 67,
                    'interpretation' => 'medium_trust',
                ],
                'decision_instruction' => [
                    'forecast_role' => 'supporting_guardrail',
                ],
                'bias_detection' => [
                    'systematic_bias_detected' => true,
                ],
            ],
            'activation_metrics' => [
                'metrics_7d' => [
                    'session_users' => 100,
                    'workspace_users' => 70,
                    'food_add_success_users' => 30,
                    'food_add_success_rate_from_session' => 30,
                    'food_add_success_rate_from_workspace' => 42.8,
                ],
            ],
            'retention_metrics' => [
                'metrics_7d_avg' => [
                    'd0_logged_rate' => 35,
                    'd1_logged_rate' => 14,
                    'habit_7d_rate' => 11,
                    'avg_log_days_7d' => 2.4,
                ],
            ],
            'monetization_metrics' => [
                'metrics_7d' => [
                    'paywall_view_users' => 20,
                    'purchase_start_users' => 5,
                    'purchase_success_users' => 1,
                    'purchase_success_rate_from_paywall' => 5,
                ],
            ],
            'guardrail_policy' => [
                'triggered_guardrails' => ['retention_guardrail' => ['severity' => 'warning']],
                'winning_guardrail' => 'retention_guardrail',
                'deterministic_decision' => [
                    'business_verdict' => 'HOLD_AND_OPTIMIZE',
                    'blocked_decision' => 'scale_aggressively',
                    'allowed_decision' => 'small_controlled_tests',
                ],
            ],
            'source_metrics_context' => ['very' => 'large'],
            'source_metric_refs' => ['also' => 'large'],
        ]);

        $this->assertSame('AI Growth Doctor', $compact['checkpoint_meta']['app_name']);
        $this->assertSame('2026-06-23', $compact['tomorrow_forecast_metrics']['forecast_for_date']);
        $this->assertSame('partially_correct', $compact['forecast_evaluation_summary']['latest_quality']);
        $this->assertSame(67, $compact['forecast_calibration_summary']['trust_score']);
        $this->assertSame(100, $compact['activation_compact']['session_users']);
        $this->assertSame(14, $compact['retention_compact']['d1_rate']);
        $this->assertSame(1, $compact['monetization_compact']['purchase_success_users']);
        $this->assertSame('retention_guardrail', $compact['guardrail_compact']['winning_guardrail']);
        $this->assertArrayNotHasKey('source_metrics_context', $compact);
        $this->assertArrayNotHasKey('source_metric_refs', $compact);
    }
}
