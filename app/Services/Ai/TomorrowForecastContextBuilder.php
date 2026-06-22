<?php

namespace App\Services\Ai;

class TomorrowForecastContextBuilder
{
    public function buildCompact(array $metricsContext): array
    {
        $forecastMetrics = $metricsContext['tomorrow_forecast_metrics'] ?? [];
        $activationMetrics = $metricsContext['activation_metrics'] ?? [];
        $retentionMetrics = $metricsContext['retention_metrics'] ?? [];
        $monetizationMetrics = $metricsContext['monetization_metrics'] ?? [];
        $guardrailPolicy = $metricsContext['guardrail_policy'] ?? [];
        $forecastEvaluation = $metricsContext['forecast_evaluations'] ?? ($metricsContext['evaluations']['forecast_evaluations'] ?? []);
        $forecastCalibration = $metricsContext['forecast_model_calibration'] ?? ($metricsContext['evaluations']['forecast_model_calibration'] ?? []);

        return [
            'checkpoint_meta' => [
                'app_name' => $metricsContext['checkpoint_meta']['app_name'] ?? null,
                'window_start' => $metricsContext['checkpoint_meta']['window_start'] ?? null,
                'window_end' => $metricsContext['checkpoint_meta']['window_end'] ?? null,
                'timezone' => $metricsContext['checkpoint_meta']['timezone'] ?? ($metricsContext['app_profile']['timezone'] ?? null),
            ],
            'tomorrow_forecast_metrics' => [
                'forecast_for_date' => $forecastMetrics['forecast_for_date'] ?? null,
                'data_as_of_date' => $forecastMetrics['data_as_of_date'] ?? null,
                'risk_flags' => $forecastMetrics['risk_flags'] ?? ($forecastMetrics['guardrails'] ?? []),
                'completeness_guard_status' => $forecastMetrics['data_windows']['completeness_guard']['status'] ?? null,
            ],
            'forecast_evaluation_summary' => [
                'evaluated_count' => $forecastEvaluation['evaluated_count'] ?? 0,
                'pending_count' => $forecastEvaluation['pending_count'] ?? 0,
                'skipped_count' => $forecastEvaluation['skipped_count'] ?? 0,
                'latest_quality' => $this->latestForecastQuality($forecastEvaluation),
            ],
            'forecast_calibration_summary' => [
                'trust_score' => $forecastCalibration['trust_score']['updated_score'] ?? null,
                'trust_interpretation' => $forecastCalibration['trust_score']['interpretation'] ?? null,
                'forecast_role' => $forecastCalibration['decision_instruction']['forecast_role'] ?? null,
                'systematic_bias_detected' => $forecastCalibration['bias_detection']['systematic_bias_detected'] ?? null,
            ],
            'activation_compact' => [
                'session_users' => $activationMetrics['metrics_7d']['session_users'] ?? null,
                'workspace_users' => $activationMetrics['metrics_7d']['workspace_users'] ?? null,
                'core_action_success_users' => $activationMetrics['metrics_7d']['food_add_success_users'] ?? null,
                'core_action_success_rate_from_entry' => $activationMetrics['metrics_7d']['food_add_success_rate_from_session'] ?? null,
                'core_action_success_rate_from_workspace' => $activationMetrics['metrics_7d']['food_add_success_rate_from_workspace'] ?? null,
            ],
            'retention_compact' => [
                'd0_rate' => $retentionMetrics['metrics_7d_avg']['d0_logged_rate'] ?? null,
                'd1_rate' => $retentionMetrics['metrics_7d_avg']['d1_logged_rate'] ?? null,
                'habit_7d_rate' => $retentionMetrics['metrics_7d_avg']['habit_7d_rate'] ?? null,
                'avg_active_days_7d' => $retentionMetrics['metrics_7d_avg']['avg_log_days_7d'] ?? null,
            ],
            'monetization_compact' => [
                'paywall_view_users' => $monetizationMetrics['metrics_7d']['paywall_view_users'] ?? null,
                'purchase_start_users' => $monetizationMetrics['metrics_7d']['purchase_start_users'] ?? null,
                'purchase_success_users' => $monetizationMetrics['metrics_7d']['purchase_success_users'] ?? null,
                'purchase_success_rate_from_paywall' => $monetizationMetrics['metrics_7d']['purchase_success_rate_from_paywall'] ?? null,
            ],
            'guardrail_compact' => [
                'triggered_guardrails_count' => is_array($guardrailPolicy['triggered_guardrails'] ?? null) ? count($guardrailPolicy['triggered_guardrails']) : 0,
                'winning_guardrail' => $guardrailPolicy['winning_guardrail'] ?? null,
                'deterministic_business_verdict' => $guardrailPolicy['deterministic_decision']['business_verdict'] ?? null,
                'blocked_decision' => $guardrailPolicy['deterministic_decision']['blocked_decision'] ?? null,
                'allowed_decision' => $guardrailPolicy['deterministic_decision']['allowed_decision'] ?? null,
            ],
        ];
    }

    private function latestForecastQuality(array $forecastEvaluation): ?string
    {
        $evaluated = $forecastEvaluation['evaluated'] ?? [];
        $latest = !empty($evaluated) ? end($evaluated) : [];

        return $latest['summary']['forecast_quality'] ?? ($forecastEvaluation['latest_evaluation']['summary']['forecast_quality'] ?? null);
    }
}
