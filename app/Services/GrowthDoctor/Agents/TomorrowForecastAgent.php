<?php


namespace App\Services\GrowthDoctor\Agents;

use App\Services\GrowthDoctor\Agents\AiAgentClient;

class TomorrowForecastAgent
{
    private $client;

    public function __construct(AiAgentClient $client)
    {
        $this->client = $client;
    }

    public function run(array $metricsContext): array
    {
        $noForecastResult = $this->noForecastMetricsResult($metricsContext);

        if ($noForecastResult !== null) {
            return $noForecastResult;
        }

        $request = $this->buildRequest($metricsContext);

        return $this->client->call(
            $request['agent_name'],
            $request['system_prompt'],
            $request['expected_schema'],
            $request['agent_context']
        );
    }

    public function buildRequest(array $metricsContext): array
    {
        $language = $this->client->outputLanguage();
        $forecastMetrics = $metricsContext['tomorrow_forecast_metrics'] ?? [];
        $activationMetrics = $metricsContext['activation_metrics'] ?? [];
        $retentionMetrics = $metricsContext['retention_metrics'] ?? [];
        $monetizationMetrics = $metricsContext['monetization_metrics'] ?? [];
        $ruleDecision = $metricsContext['rule_based_decision'] ?? [];
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
                    'risk_flags' => ['forecast risk flag values copied from provided forecast only']
                ],
                'risk_flags' => [
                    'activation_risk' => 'from tomorrow_forecast_metrics.risk_flags or unavailable',
                    'workspace_quality' => 'from tomorrow_forecast_metrics.risk_flags or unavailable',
                    'retention_risk' => 'from tomorrow_forecast_metrics.risk_flags or unavailable',
                    'habit_risk' => 'from tomorrow_forecast_metrics.risk_flags or unavailable',
                    'monetization_sample' => 'from tomorrow_forecast_metrics.risk_flags or unavailable',
                    'scaling_caution' => 'from tomorrow_forecast_metrics.risk_flags.scaling_caution or unavailable'
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
                    'rollback_condition' => 'rollback condition'
                ],
                'forecast_trust_weighting' => [
                    'evaluation_status' => 'summary from forecast_evaluations if available',
                    'calibration_status' => 'summary from forecast_model_calibration if available',
                    'forecast_role' => 'primary_forecast_caution | supporting_forecast_caution | directional_signal_only | not_available',
                    'why' => 'why the forecast should receive that weight today'
                ],
                'evaluation_plan_for_tomorrow' => [
                    'metrics_to_compare' => ['metric names that should be compared against actual tomorrow'],
                    'hit_rule' => 'actual inside low-high range is hit; outside range is miss',
                    'decision_quality_rule' => 'how tomorrow evaluation should judge this forecast interpretation'
                ],
                'limitations' => ['forecast limitations and uncertainty notes']
            ],
            [
                'checkpoint_meta' => $metricsContext['checkpoint_meta'] ?? [],
                'tomorrow_forecast_metrics' => $forecastMetrics,
                'current_activation_metrics' => $activationMetrics,
                'current_retention_metrics' => $retentionMetrics,
                'current_monetization_metrics' => $monetizationMetrics,
                'current_rule_based_decision' => $ruleDecision,
                'forecast_evaluations' => $forecastEvaluation,
                'forecast_model_calibration' => $forecastCalibration,
                'terminology_rule' => 'Use risk_flags/scaling_caution terminology. Do not call forecast risk flags deterministic guardrails or GuardrailPolicyEngine triggers.',
                'preferred_forecast_risk_flags' => $forecastMetrics['risk_flags'] ?? ($forecastMetrics['guardrails'] ?? []),
                'strict_numeric_rule' => 'Do not invent, recalculate, or modify forecast numbers. Copy numeric values only from tomorrow_forecast_metrics.'
            ]
        );
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
    private function buildDeterministicForecastResult(
        array $forecastMetrics,
        array $activationMetrics,
        array $retentionMetrics,
        array $monetizationMetrics,
        array $ruleDecision
    ): array {
        $predictedMetrics = $forecastMetrics['predicted_metrics'] ?? [];
        $riskFlags = $forecastMetrics['risk_flags'] ?? ($forecastMetrics['guardrails'] ?? []);
        $forecastForDate = $forecastMetrics['forecast_for_date'] ?? null;
        $runDate = $forecastMetrics['run_date'] ?? null;
        $runTimestamp = $forecastMetrics['run_timestamp'] ?? null;
        $dataAsOfDate = $forecastMetrics['data_as_of_date'] ?? null;
        $actualDataAvailableUntil = $forecastMetrics['actual_data_available_until'] ?? $dataAsOfDate;
        $evaluationReadyAfter = $forecastMetrics['evaluation_ready_after'] ?? null;
        $evaluationStatus = $forecastMetrics['evaluation_status'] ?? 'pending_actual_data';
        $evaluationRule = $forecastMetrics['evaluation_rule'] ?? 'Evaluate only when actual data for forecast date is available.';

        $activation = $predictedMetrics['activation'] ?? [];
        $retention = $predictedMetrics['retention'] ?? [];
        $monetization = $predictedMetrics['monetization'] ?? [];

        $d1 = $retention['d1_logged_rate']['point'] ?? null;
        $habit = $retention['habit_7d_rate']['point'] ?? null;
        $foodSession = $activation['food_add_success_rate_from_session']['point'] ?? null;
        $foodWorkspace = $activation['food_add_success_rate_from_workspace']['point'] ?? null;
        $purchaseUsers = $monetization['purchase_success_users']['point'] ?? null;

        $riskDrivers = [];

        if ($d1 !== null && $d1 < 15) {
            $riskDrivers[] = 'Predicted D1 logged rate is below the 15% caution threshold.';
        }

        if ($habit !== null && $habit < 16) {
            $riskDrivers[] = 'Predicted 7-day habit remains below the 16% caution threshold.';
        }

        if ($foodSession !== null && $foodSession < 40) {
            $riskDrivers[] = 'Predicted food_add_success_rate_from_session is below 40%, indicating early activation risk.';
        }

        if ($foodWorkspace !== null && $foodWorkspace < 80) {
            $riskDrivers[] = 'Predicted food_add_success_rate_from_workspace is below 80%, indicating workspace quality needs monitoring.';
        }

        if ($purchaseUsers !== null && $purchaseUsers < 3) {
            $riskDrivers[] = 'Predicted purchase_success_users remains small, so tomorrow monetization signal may be noisy.';
        }

        if (empty($riskDrivers)) {
            $riskDrivers[] = 'No major forecast risk flag is deteriorating, but the forecast still needs validation tomorrow.';
        }

        $predictionStatus = $this->predictionStatusFromRiskFlags($riskFlags, $riskDrivers);
        $confidenceScore = $this->confidenceScoreFromForecast($predictedMetrics);
        $mainPredictedRisk = $this->mainPredictedRisk($riskFlags, $riskDrivers);

        return [
            'agent' => 'Tomorrow Forecast Agent',
            'status' => 'active',
            'model' => 'deterministic_forecast_v1',
            'result' => [
                'prediction_status' => $predictionStatus,
                'confidence_score' => $confidenceScore,
                'run_date' => $runDate,
                'run_timestamp' => $runTimestamp,
                'data_as_of_date' => $dataAsOfDate,
                'actual_data_available_until' => $actualDataAvailableUntil,
                'forecast_for_date' => $forecastForDate,
                'evaluation_ready_after' => $evaluationReadyAfter,
                'evaluation_status' => $evaluationStatus,
                'evaluation_rule' => $evaluationRule,
                'executive_summary' => $this->executiveSummary($forecastForDate, $predictionStatus, $mainPredictedRisk, $dataAsOfDate, $evaluationReadyAfter),
                'summary' => $this->executiveSummary($forecastForDate, $predictionStatus, $mainPredictedRisk, $dataAsOfDate, $evaluationReadyAfter),
                'main_predicted_risk' => $mainPredictedRisk,
                'decision_impact_today' => $this->decisionImpactToday($riskFlags, $dataAsOfDate, $forecastForDate),
                'predicted_metrics' => $predictedMetrics,
                'risk_flags' => $riskFlags,
                'guardrail_assessment' => $riskFlags,
                'risk_drivers' => $riskDrivers,
                'recommended_preventive_action' => $this->recommendedPreventiveAction($riskFlags),
                'evaluation_plan_for_tomorrow' => [
                    'metrics_to_compare' => [
                        'session_users',
                        'workspace_users',
                        'food_add_success_users',
                        'food_add_success_rate_from_session',
                        'food_add_success_rate_from_workspace',
                        'd0_logged_rate',
                        'd1_logged_rate',
                        'habit_7d_rate',
                        'paywall_view_users',
                        'purchase_success_users',
                    ],
                    'forecast_for_date' => $forecastForDate,
                    'data_as_of_date' => $dataAsOfDate,
                    'evaluation_ready_after' => $evaluationReadyAfter,
                    'evaluation_status' => $evaluationStatus,
                    'hit_rule' => 'Actuals for forecast_for_date are considered a hit when they fall between the low and high range for the related metric.',
                    'readiness_rule' => $evaluationRule,
                    'decision_quality_rule' => 'If the predicted main risk actually occurs once actual_data_available_until >= forecast_for_date, the forecast caution is considered valid. If actuals fall outside the range on the main metric, lower forecast confidence and correct the decision rule.',
                ],
                'limitations' => [
                    'Forecast V1 uses historical data up to data_as_of_date, not real-time data from the run day.',
                    'Forecast V1 uses weighted historical trend and does not yet include seasonality, campaign changes, or intraday signals.',
                    'Purchase forecast can be noisy because purchase samples are usually small.',
                    'Forecast is a deterministic baseline and risk caution, not a deterministic GuardrailPolicyEngine trigger or a guaranteed outcome.',
                ],
            ],
            'cache' => [
                'hit' => false,
                'key' => null,
                'ttl_seconds' => null,
            ],
        ];
    }

    private function predictionStatusFromRiskFlags(array $riskFlags, array $riskDrivers): string
    {
        if (in_array(($riskFlags['scaling_caution'] ?? ($riskFlags['scaling_guardrail'] ?? null)), ['block_aggressive_scaling', 'block_scaling'], true)) {
            return 'warning';
        }

        if (($riskFlags['retention_risk'] ?? null) === 'at_risk' || ($riskFlags['habit_risk'] ?? null) === 'at_risk') {
            return 'warning';
        }

        if (($riskFlags['activation_risk'] ?? null) === 'at_risk') {
            return 'warning';
        }

        if (count($riskDrivers) > 1) {
            return 'watch';
        }

        return 'healthy';
    }

    private function confidenceScoreFromForecast(array $predictedMetrics): int
    {
        $confidences = [];

        foreach ($predictedMetrics as $group) {
            if (!is_array($group)) {
                continue;
            }

            foreach ($group as $metric) {
                if (!is_array($metric)) {
                    continue;
                }

                $confidence = $metric['confidence'] ?? null;

                if ($confidence === 'medium_high') {
                    $confidences[] = 80;
                } elseif ($confidence === 'medium') {
                    $confidences[] = 68;
                } elseif ($confidence === 'medium_low') {
                    $confidences[] = 55;
                } elseif ($confidence === 'low') {
                    $confidences[] = 40;
                }
            }
        }

        if (empty($confidences)) {
            return 50;
        }

        return (int) round(array_sum($confidences) / count($confidences));
    }

    private function mainPredictedRisk(array $riskFlags, array $riskDrivers): string
    {
        if (($riskFlags['retention_risk'] ?? null) === 'at_risk') {
            return 'D1 retention is predicted to remain at_risk.';
        }

        if (($riskFlags['habit_risk'] ?? null) === 'at_risk') {
            return '7-day habit is predicted to remain weak.';
        }

        if (($riskFlags['activation_risk'] ?? null) === 'at_risk') {
            return 'Early activation is predicted to remain weak from session to food_add_success.';
        }

        if (($riskFlags['monetization_sample'] ?? null) === 'low_sample') {
            return 'Tomorrow monetization signal is predicted to remain low-sample.';
        }

        return $riskDrivers[0] ?? 'No dominant main risk stands out.';
    }

    private function executiveSummary(?string $forecastForDate, string $predictionStatus, string $mainPredictedRisk, ?string $dataAsOfDate = null, ?string $evaluationReadyAfter = null): string
    {
        $dateText = $forecastForDate ?: 'the next forecast date';
        $dataText = $dataAsOfDate ?: 'the latest available data';
        $evaluationText = $evaluationReadyAfter ?: 'after actual data for the forecast date is available';

        return 'Forecast for ' . $dateText . ' was generated from data through ' . $dataText . ' with status ' . strtoupper($predictionStatus) . '. Main risk: ' . $mainPredictedRisk . ' Evaluation can start ' . $evaluationText . '.';
    }

    private function decisionImpactToday(array $riskFlags, ?string $dataAsOfDate = null, ?string $forecastForDate = null): string
    {
        $context = 'This forecast is based on data through ' . ($dataAsOfDate ?: 'the latest available data') . ' and predicts ' . ($forecastForDate ?: 'the next forecast date') . '. ';

        if (in_array(($riskFlags['scaling_caution'] ?? ($riskFlags['scaling_guardrail'] ?? null)), ['block_aggressive_scaling', 'block_scaling'], true)) {
            return $context . 'The forecast gives a risk caution to avoid aggressive scaling and focus on activation/retention recovery.';
        }

        return $context . 'The forecast does not caution against small experiments, but actuals should still be evaluated once forecast-date data is available.';
    }

    private function recommendedPreventiveAction(array $riskFlags): array
    {
        if (($riskFlags['retention_risk'] ?? null) === 'at_risk' || ($riskFlags['habit_risk'] ?? null) === 'at_risk') {
            return [
                'action' => 'Run D1 habit rescue nudge',
                'target_user_segment' => 'New users who completed food_add_success on D0 but have not logged again within 20-24 hours.',
                'trigger_condition' => 'D0_logged = true AND no_log_next_day_by_18:00',
                'success_metric' => 'D1 logged rate',
                'stop_loss_metric' => 'notification opt-out or app_remove',
                'expected_lift' => '+10-15% relative D1 logged rate',
                'experiment_duration' => '7 days',
                'minimum_sample_size' => '500 eligible users or 7 days, whichever comes first',
                'rollback_condition' => 'No D1 uplift after minimum sample or opt-out increases by more than 2 points.',
            ];
        }

        if (($riskFlags['activation_risk'] ?? null) === 'at_risk') {
            return [
                'action' => 'Run first-food-log rescue experiment',
                'target_user_segment' => 'New users who reached session/home but did not complete food_add_success within 2 hours.',
                'trigger_condition' => 'session_started = true AND food_add_success = false after 2 hours',
                'success_metric' => 'food_add_success_rate_from_session',
                'stop_loss_metric' => 'app_remove or session drop',
                'expected_lift' => '+5 absolute points in food_add_success_rate_from_session',
                'experiment_duration' => '7 days',
                'minimum_sample_size' => '500 new users',
                'rollback_condition' => 'food_add_success_rate_from_workspace drops below 80% or there is no uplift after the minimum sample.',
            ];
        }

        return [
            'action' => 'Monitor forecast risk flags without aggressive scaling',
            'target_user_segment' => 'All new users in next-day cohort',
            'trigger_condition' => 'Daily forecast run completed',
            'success_metric' => 'Forecast hit rate and risk flag stability',
            'stop_loss_metric' => 'D1 logged rate or food_add_success_rate_from_session worsening',
            'expected_lift' => 'Maintain stable activation and retention',
            'experiment_duration' => '24 hours',
            'minimum_sample_size' => '1 daily cohort',
            'rollback_condition' => 'Forecast miss on main risk or risk flag worsens materially.',
        ];
    }
}
