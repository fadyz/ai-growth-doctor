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

        $english = strtolower($this->client->outputLanguage()) === 'english';

        return [
            'agent' => 'Tomorrow Forecast Agent',
            'status' => 'inactive',
            'model' => null,
            'result' => [
                'prediction_status' => 'no_forecast_metrics',
                'confidence_score' => 0,
                'summary' => $english
                    ? 'Tomorrow forecast metrics are not available yet. Make sure MetricsExtractor sends tomorrow_forecast_metrics.'
                    : 'Tomorrow forecast metrics belum tersedia. Pastikan MetricsExtractor mengirim tomorrow_forecast_metrics.',
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
            $riskDrivers[] = 'Prediksi D1 logged rate berada di bawah threshold caution 15%.';
        }

        if ($habit !== null && $habit < 16) {
            $riskDrivers[] = 'Prediksi habit 7D masih di bawah threshold caution 16%.';
        }

        if ($foodSession !== null && $foodSession < 40) {
            $riskDrivers[] = 'Prediksi food_add_success_rate_from_session di bawah 40%, menandakan risiko aktivasi awal.';
        }

        if ($foodWorkspace !== null && $foodWorkspace < 80) {
            $riskDrivers[] = 'Prediksi food_add_success_rate_from_workspace di bawah 80%, menandakan kualitas workspace perlu dipantau.';
        }

        if ($purchaseUsers !== null && $purchaseUsers < 3) {
            $riskDrivers[] = 'Prediksi purchase_success_users masih kecil, sehingga sinyal monetisasi besok berisiko noisy.';
        }

        if (empty($riskDrivers)) {
            $riskDrivers[] = 'Tidak ada forecast risk flag utama yang memburuk, namun forecast tetap perlu divalidasi besok.';
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
                    'hit_rule' => 'Actual untuk forecast_for_date dianggap hit jika berada di antara low dan high untuk metric terkait.',
                    'readiness_rule' => $evaluationRule,
                    'decision_quality_rule' => 'Jika risiko utama yang diprediksi benar terjadi saat actual_data_available_until >= forecast_for_date, forecast caution dinilai valid. Jika actual keluar range pada metric utama, turunkan confidence forecast dan koreksi decision rule.',
                ],
                'limitations' => [
                    'Forecast V1 memakai data historis sampai data_as_of_date, bukan data real-time hari run.',
                    'Forecast V1 memakai weighted historical trend, belum memasukkan seasonality, campaign change, atau intraday signal.',
                    'Purchase forecast berpotensi noisy karena sample pembelian biasanya kecil.',
                    'Forecast adalah baseline deterministic dan risk caution, bukan deterministic GuardrailPolicyEngine trigger dan bukan kepastian outcome.',
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
            return 'D1 retention diprediksi tetap berada pada status at_risk.';
        }

        if (($riskFlags['habit_risk'] ?? null) === 'at_risk') {
            return 'Habit 7D diprediksi belum cukup kuat.';
        }

        if (($riskFlags['activation_risk'] ?? null) === 'at_risk') {
            return 'Aktivasi awal diprediksi belum cukup kuat dari session ke food_add_success.';
        }

        if (($riskFlags['monetization_sample'] ?? null) === 'low_sample') {
            return 'Sinyal monetisasi besok diprediksi masih low sample.';
        }

        return $riskDrivers[0] ?? 'Tidak ada risiko utama yang menonjol.';
    }

    private function executiveSummary(?string $forecastForDate, string $predictionStatus, string $mainPredictedRisk, ?string $dataAsOfDate = null, ?string $evaluationReadyAfter = null): string
    {
        $dateText = $forecastForDate ?: 'tanggal forecast berikutnya';
        $dataText = $dataAsOfDate ?: 'data terakhir yang tersedia';
        $evaluationText = $evaluationReadyAfter ?: 'setelah data aktual tanggal forecast tersedia';

        return 'Forecast untuk ' . $dateText . ' dibuat berdasarkan data sampai ' . $dataText . ', berstatus ' . strtoupper($predictionStatus) . '. Risiko utama: ' . $mainPredictedRisk . ' Evaluasi dapat dilakukan mulai ' . $evaluationText . '.';
    }

    private function decisionImpactToday(array $riskFlags, ?string $dataAsOfDate = null, ?string $forecastForDate = null): string
    {
        $context = 'Forecast ini berbasis data sampai ' . ($dataAsOfDate ?: 'data terakhir tersedia') . ' dan memprediksi ' . ($forecastForDate ?: 'tanggal forecast berikutnya') . '. ';

        if (in_array(($riskFlags['scaling_caution'] ?? ($riskFlags['scaling_guardrail'] ?? null)), ['block_aggressive_scaling', 'block_scaling'], true)) {
            return $context . 'Forecast memberi risk caution untuk menahan scaling agresif dan fokus pada activation/retention recovery.';
        }

        return $context . 'Forecast tidak memberi caution untuk memblokir eksperimen kecil, tetapi tetap perlu evaluasi aktual ketika data tanggal forecast sudah tersedia.';
    }

    private function recommendedPreventiveAction(array $riskFlags): array
    {
        if (($riskFlags['retention_risk'] ?? null) === 'at_risk' || ($riskFlags['habit_risk'] ?? null) === 'at_risk') {
            return [
                'action' => 'Run D1 habit rescue nudge',
                'target_user_segment' => 'User baru yang sudah food_add_success di D0 tetapi belum log lagi dalam 20-24 jam.',
                'trigger_condition' => 'D0_logged = true AND no_log_next_day_by_18:00',
                'success_metric' => 'D1 logged rate',
                'stop_loss_metric' => 'notification opt-out atau app_remove',
                'expected_lift' => '+10-15% relative D1 logged rate',
                'experiment_duration' => '7 days',
                'minimum_sample_size' => '500 eligible users atau 7 hari, mana yang tercapai lebih dulu',
                'rollback_condition' => 'Tidak ada uplift D1 setelah sample minimum atau opt-out naik >2 poin.',
            ];
        }

        if (($riskFlags['activation_risk'] ?? null) === 'at_risk') {
            return [
                'action' => 'Run first-food-log rescue experiment',
                'target_user_segment' => 'User baru yang mencapai session/home tetapi belum food_add_success dalam 2 jam.',
                'trigger_condition' => 'session_started = true AND food_add_success = false after 2 hours',
                'success_metric' => 'food_add_success_rate_from_session',
                'stop_loss_metric' => 'app_remove atau session drop',
                'expected_lift' => '+5 poin absolut food_add_success_rate_from_session',
                'experiment_duration' => '7 days',
                'minimum_sample_size' => '500 new users',
                'rollback_condition' => 'food_add_success_rate_from_workspace turun di bawah 80% atau no uplift setelah sample minimum.',
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
