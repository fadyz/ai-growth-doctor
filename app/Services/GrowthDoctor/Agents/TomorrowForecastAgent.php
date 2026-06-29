<?php

namespace App\Services\GrowthDoctor\Agents;

use App\Services\Ai\TomorrowForecastContextBuilder;

class TomorrowForecastAgent
{
    private $client;
    private $contextBuilder;

    public function __construct(AiAgentClient $client, TomorrowForecastContextBuilder $contextBuilder)
    {
        $this->client = $client;
        $this->contextBuilder = $contextBuilder;
    }

    public function run(array $metricsContext, array $requestMeta = []): array
    {
        $noForecastResult = $this->noForecastMetricsResult($metricsContext);

        if ($noForecastResult !== null) {
            return $noForecastResult;
        }

        $request = $this->buildRequest($metricsContext, $requestMeta);
        $result = $this->client->call(
            $request['agent_name'],
            $request['system_prompt'],
            $request['expected_schema'],
            $request['agent_context'],
            $request['request_meta'] ?? []
        );

        return $this->applyDeterministicFallback($result, $metricsContext);
    }

    public function buildRequest(array $metricsContext, array $requestMeta = []): array
    {
        $language = $this->client->outputLanguage();
        $compactContext = $this->contextBuilder->buildCompact($metricsContext);
        $forecastMetrics = $compactContext['tomorrow_forecast_metrics'] ?? [];
        $forecastEvaluation = $metricsContext['forecast_evaluations'] ?? ($metricsContext['evaluations']['forecast_evaluations'] ?? []);
        $forecastCalibration = $metricsContext['forecast_model_calibration'] ?? ($metricsContext['evaluations']['forecast_model_calibration'] ?? []);

        return $this->client->prepareRequest(
            'Tomorrow Forecast Agent',
            'You are the Tomorrow Forecast Agent for AI Growth Doctor. The numeric forecast has already been calculated by a deterministic forecast engine. You must not invent, recalculate, or modify numeric forecast values. Use only numbers that exist in tomorrow_forecast_metrics. Your job is to interpret the deterministic baseline forecast, identify tomorrow\'s operational risk, explain how forecast risk flags should influence today\'s decision, and propose one preventive action. If a metric is missing, say unavailable. Forecast fields such as risk_flags, deprecated guardrails, scaling_caution, or old scaling_guardrail are forecast caution signals only, not deterministic GuardrailPolicyEngine triggers, not direct contact with GuardrailPolicyEngine, and not vetoes. Consider forecast evaluation and calibration only as trust-weighting evidence. Return valid JSON only in ' . $language . '. No markdown, no prose outside JSON, no code fences.',
            [
                'prediction_status' => 'healthy | watch | warning | critical | no_forecast_metrics',
                'confidence_score' => '0-100 integer; confidence in the interpretation, not invented forecast accuracy',
                'forecast_for_date' => 'YYYY-MM-DD or null from tomorrow_forecast_metrics',
                'data_as_of_date' => 'YYYY-MM-DD or null from tomorrow_forecast_metrics',
                'forecast_engine' => 'method/formula from tomorrow_forecast_metrics if available',
                'executive_summary' => 'short ' . $language . ' summary of deterministic forecast and risk',
                'summary' => 'short ' . $language . ' one-sentence forecast summary for interaction log; should mirror executive_summary but shorter',
                'main_predicted_risk' => 'single biggest predicted risk based only on provided forecast metrics',
                'decision_impact_today' => 'how deterministic forecast should affect today operating decision',
                'forecast_evidence' => [
                    'activation' => ['key metrics copied from provided forecast only'],
                    'retention' => ['key metrics copied from provided forecast only'],
                    'monetization' => ['key metrics copied from provided forecast only'],
                    'risk_flags' => ['forecast risk flag values copied from provided forecast only'],
                ],
                'risk_flags' => [
                    'activation_risk' => 'from tomorrow_forecast_metrics.risk_flags or unavailable',
                    'workspace_quality' => 'from tomorrow_forecast_metrics.risk_flags or unavailable',
                    'retention_risk' => 'from tomorrow_forecast_metrics.risk_flags or unavailable',
                    'habit_risk' => 'from tomorrow_forecast_metrics.risk_flags or unavailable',
                    'monetization_sample' => 'from tomorrow_forecast_metrics.risk_flags or unavailable',
                    'scaling_caution' => 'from tomorrow_forecast_metrics.risk_flags.scaling_caution or unavailable',
                ],
                'guardrail_assessment' => 'deprecated alias only if needed for backward compatibility; do not describe as GuardrailPolicyEngine output',
                'risk_drivers' => ['specific forecast-driven risk drivers using provided numbers only'],
                'recommended_preventive_action' => [
                    'action' => 'specific preventive action for today/tomorrow',
                    'target_user_segment' => 'specific user segment',
                    'trigger_condition' => 'when to trigger',
                    'success_metric' => 'metric to judge action',
                    'stop_loss_metric' => 'metric that stops action if worsening',
                    'expected_lift' => 'directional only unless provided by input metrics/config',
                    'experiment_duration' => 'duration',
                    'minimum_sample_size' => 'minimum sample',
                    'rollback_condition' => 'rollback condition',
                ],
                'forecast_trust_weighting' => [
                    'evaluation_status' => 'summary from forecast_evaluations if available',
                    'calibration_status' => 'summary from forecast_model_calibration if available',
                    'forecast_role' => 'primary_forecast_caution | supporting_forecast_caution | directional_signal_only | not_available',
                    'why' => 'why the forecast should receive that weight today',
                ],
                'evaluation_plan_for_tomorrow' => [
                    'metrics_to_compare' => ['metric names that should be compared against actual tomorrow'],
                    'hit_rule' => 'actual inside low-high range is hit; outside range is miss',
                    'decision_quality_rule' => 'how tomorrow evaluation should judge this forecast interpretation',
                ],
                'limitations' => ['forecast limitations and uncertainty notes'],
            ],
            [
                'checkpoint_meta' => $compactContext['checkpoint_meta'],
                'tomorrow_forecast_metrics' => $forecastMetrics,
                'forecast_evaluation_summary' => $compactContext['forecast_evaluation_summary'],
                'forecast_calibration_summary' => $compactContext['forecast_calibration_summary'],
                'activation_compact' => $compactContext['activation_compact'],
                'retention_compact' => $compactContext['retention_compact'],
                'monetization_compact' => $compactContext['monetization_compact'],
                'guardrail_compact' => $compactContext['guardrail_compact'],
                'forecast_evaluations' => [
                    'status' => $forecastEvaluation['status'] ?? null,
                    'latest_quality' => $this->extractLatestForecastEvaluationQuality($forecastEvaluation),
                ],
                'forecast_model_calibration' => [
                    'status' => $forecastCalibration['status'] ?? null,
                    'trust_score' => $forecastCalibration['trust_score']['updated_score'] ?? null,
                    'trust_interpretation' => $forecastCalibration['trust_score']['interpretation'] ?? null,
                    'forecast_role' => $forecastCalibration['decision_instruction']['forecast_role'] ?? null,
                ],
                'terminology_rule' => 'Use risk_flags/scaling_caution terminology. Do not call forecast risk flags deterministic guardrails or GuardrailPolicyEngine triggers.',
                'preferred_forecast_risk_flags' => $forecastMetrics['risk_flags'] ?? ($forecastMetrics['guardrails'] ?? []),
                'strict_numeric_rule' => 'Do not invent, recalculate, or modify forecast numbers. Copy numeric values only from tomorrow_forecast_metrics.',
            ],
            array_merge($requestMeta, [
                'timeout_seconds' => (int) config('ai_growth_doctor.ai.tomorrow_forecast_timeout_seconds', 45),
                'context_mode' => 'compact',
            ])
        );
    }

    public function applyDeterministicFallback(array $agentOutput, array $metricsContext): array
    {
        if (!$this->fallbackEnabled()) {
            return $agentOutput;
        }

        if (($agentOutput['status'] ?? null) === 'active') {
            return $agentOutput;
        }

        $forecastMetrics = $metricsContext['tomorrow_forecast_metrics'] ?? [];

        if (empty($forecastMetrics) || !$this->shouldFallback($agentOutput)) {
            return $agentOutput;
        }

        $riskFlags = $forecastMetrics['risk_flags'] ?? ($forecastMetrics['guardrails'] ?? []);
        $forecastEvaluation = $metricsContext['forecast_evaluations'] ?? ($metricsContext['evaluations']['forecast_evaluations'] ?? []);
        $forecastCalibration = $metricsContext['forecast_model_calibration'] ?? ($metricsContext['evaluations']['forecast_model_calibration'] ?? []);
        $forecastRole = $forecastCalibration['decision_instruction']['forecast_role'] ?? 'not_available';
        $latestQuality = $this->extractLatestForecastEvaluationQuality($forecastEvaluation) ?? 'not_available';
        $llmStatus = $agentOutput['response_metrics']['status'] ?? 'unavailable';
        $llmError = [
            'type' => $agentOutput['response_metrics']['error_class'] ?? ($agentOutput['status'] ?? 'provider_error'),
            'message' => mb_substr((string) ($agentOutput['error'] ?? 'Tomorrow Forecast LLM call failed.'), 0, 500),
        ];
        $summary = $this->buildFallbackSummary($riskFlags, $forecastRole, $latestQuality);

        $agentOutput['status'] = 'fallback';
        $agentOutput['result_status'] = 'forecast_interpreted_deterministically';
        $agentOutput['summary'] = $summary;
        $agentOutput['result'] = array_merge($agentOutput['result'] ?? [], [
            'status' => 'forecast_interpreted_deterministically',
            'prediction_status' => $this->predictionStatusFromRiskFlags($riskFlags),
            'forecast_for_date' => $forecastMetrics['forecast_for_date'] ?? null,
            'data_as_of_date' => $forecastMetrics['data_as_of_date'] ?? null,
            'forecast_engine' => $forecastMetrics['forecast_engine'] ?? null,
            'executive_summary' => $summary,
            'summary' => $summary,
            'main_predicted_risk' => $summary,
            'decision_impact_today' => $this->fallbackDecisionImpactToday($riskFlags, $forecastRole, $latestQuality),
            'risk_flags' => $riskFlags,
            'guardrail_assessment' => $riskFlags,
            'forecast_trust_weighting' => [
                'evaluation_status' => $latestQuality,
                'calibration_status' => $forecastCalibration['status'] ?? null,
                'forecast_role' => $forecastRole,
                'why' => 'Fallback summary uses deterministic forecast metrics plus evaluation/calibration weighting because LLM narration was unavailable.',
            ],
            'fallback_used' => true,
            'fallback_reason' => 'Tomorrow Forecast LLM call failed, but deterministic forecast metrics and calibration were available.',
            'llm_status' => $llmStatus,
            'llm_error' => $llmError,
            'decision_usable' => true,
            'forecast_role' => $forecastRole,
            'risk_flags_used' => $riskFlags,
        ]);
        $agentOutput['llm_status'] = $llmStatus;
        $agentOutput['llm_error'] = $llmError;
        $agentOutput['decision_usable'] = true;
        $agentOutput['fallback_reason'] = 'Tomorrow Forecast LLM call failed, but deterministic forecast metrics and calibration were available.';
        $agentOutput['forecast_role'] = $forecastRole;
        $agentOutput['risk_flags_used'] = $riskFlags;

        return $agentOutput;
    }

    private function noForecastMetricsResult(array $metricsContext): ?array
    {
        $forecastMetrics = $metricsContext['tomorrow_forecast_metrics'] ?? [];

        if (!empty($forecastMetrics)) {
            return null;
        }

        return [
            'agent' => 'Tomorrow Forecast Agent',
            'status' => 'inactive',
            'model' => null,
            'result' => [
                'prediction_status' => 'no_forecast_metrics',
                'confidence_score' => 0,
                'summary' => 'Tomorrow forecast metrics are not available yet. Make sure MetricsExtractor sends tomorrow_forecast_metrics.',
                'forecast_for_date' => null,
                'predicted_metrics' => [],
                'risk_flags' => [],
                'guardrail_assessment' => [],
            ],
            'cache' => [
                'hit' => null,
                'key' => null,
                'ttl_seconds' => null,
            ],
        ];
    }

    private function fallbackEnabled(): bool
    {
        return (bool) config('ai_growth_doctor.ai.tomorrow_forecast_fallback_enabled', true);
    }

    private function shouldFallback(array $agentOutput): bool
    {
        $status = $agentOutput['status'] ?? null;
        $responseStatus = $agentOutput['response_metrics']['status'] ?? null;
        $httpStatus = (int) ($agentOutput['response_metrics']['http_status'] ?? 0);

        if (in_array($status, ['exception', 'invalid_json'], true)) {
            return true;
        }

        if (in_array($responseStatus, ['timeout', 'provider_error', 'parse_error', 'unavailable'], true)) {
            return true;
        }

        if ($status === 'error' && $httpStatus >= 500) {
            return true;
        }

        if ($status === 'error' && empty($agentOutput['error'])) {
            return true;
        }

        return false;
    }

    private function buildFallbackSummary(array $riskFlags, string $forecastRole, string $latestQuality): string
    {
        $parts = [];

        $watchAreas = [];
        if (($riskFlags['activation_risk'] ?? null) === 'watch') {
            $watchAreas[] = 'activation';
        }
        if (($riskFlags['retention_risk'] ?? null) === 'watch') {
            $watchAreas[] = 'retention';
        }
        if (($riskFlags['habit_risk'] ?? null) === 'watch') {
            $watchAreas[] = 'habit';
        }

        if (!empty($watchAreas)) {
            $parts[] = 'Forecast flags ' . implode(', ', $watchAreas) . ' as watch areas.';
        }

        if (($riskFlags['monetization_sample'] ?? null) === 'low_sample') {
            $parts[] = 'Monetization remains low-sample.';
        }

        if ($forecastRole === 'can_strengthen_guardrail') {
            $parts[] = 'Forecast calibration indicates this signal can strengthen guardrail interpretation.';
        } elseif ($forecastRole === 'supporting_guardrail') {
            $parts[] = 'Use forecast as supporting guardrail evidence, not a deterministic decision owner.';
        }

        if (in_array($latestQuality, ['poor', 'partially_correct'], true)) {
            $parts[] = 'Recent forecast quality is ' . $latestQuality . ', so verify against mature actuals.';
        }

        if (($riskFlags['scaling_caution'] ?? null) === 'allow_cautious_test') {
            $parts[] = 'Scaling should remain cautious and limited to controlled tests.';
        }

        if (empty($parts)) {
            $parts[] = 'Deterministic forecast metrics are available and should be treated as directional caution until mature actuals arrive.';
        }

        return implode(' ', $parts);
    }

    private function fallbackDecisionImpactToday(array $riskFlags, string $forecastRole, string $latestQuality): string
    {
        return $this->buildFallbackSummary($riskFlags, $forecastRole, $latestQuality);
    }

    private function extractLatestForecastEvaluationQuality(array $forecastEvaluation): ?string
    {
        $evaluated = $forecastEvaluation['evaluated'] ?? [];
        $latest = $this->latestForecastEvaluation($evaluated);

        return $latest['summary']['forecast_quality'] ?? ($forecastEvaluation['latest_evaluation']['summary']['forecast_quality'] ?? null);
    }

    private function latestForecastEvaluation(array $evaluated): array
    {
        $valid = array_values(array_filter($evaluated, function ($row) {
            return is_array($row) && !empty($row['forecast_for_date']);
        }));

        if (empty($valid)) {
            return is_array($evaluated[0] ?? null) ? $evaluated[0] : [];
        }

        usort($valid, function ($a, $b) {
            $dateCompare = strcmp((string) ($b['forecast_for_date'] ?? ''), (string) ($a['forecast_for_date'] ?? ''));

            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            $dataAsOfCompare = strcmp((string) ($b['data_as_of_date'] ?? ''), (string) ($a['data_as_of_date'] ?? ''));

            if ($dataAsOfCompare !== 0) {
                return $dataAsOfCompare;
            }

            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return $valid[0] ?? [];
    }

    private function predictionStatusFromRiskFlags(array $riskFlags): string
    {
        if (in_array(($riskFlags['scaling_caution'] ?? ($riskFlags['scaling_guardrail'] ?? null)), ['block_aggressive_scaling', 'block_scaling'], true)) {
            return 'warning';
        }

        if (($riskFlags['retention_risk'] ?? null) === 'at_risk' || ($riskFlags['habit_risk'] ?? null) === 'at_risk' || ($riskFlags['activation_risk'] ?? null) === 'at_risk') {
            return 'warning';
        }

        if (($riskFlags['activation_risk'] ?? null) === 'watch' || ($riskFlags['retention_risk'] ?? null) === 'watch' || ($riskFlags['habit_risk'] ?? null) === 'watch') {
            return 'watch';
        }

        return 'healthy';
    }
}
