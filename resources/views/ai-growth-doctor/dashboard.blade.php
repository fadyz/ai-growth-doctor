<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI Growth Doctor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }

        .loading-spinner {
            width: 44px;
            height: 44px;
            border: 4px solid rgba(15, 23, 42, 0.12);
            border-top-color: rgb(15, 23, 42);
            border-radius: 9999px;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .agd-wrap-anywhere {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 overflow-x-hidden">
    <div id="pageLoadingOverlay" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-100/80 backdrop-blur-sm">
        <div class="bg-white border border-slate-200 shadow-xl rounded-3xl px-6 py-5 flex items-center gap-4">
            <div class="loading-spinner"></div>
            <div>
                <div class="font-bold text-slate-950">Running AI Growth Doctor</div>
                <div class="text-sm text-slate-500">Calling agents, reading cache, and composing the business decision...</div>
            </div>
        </div>
    </div>
    @php
        $agents = $analysis['agents'] ?? [];
        $decision = $agents['decision_agent'] ?? [];
        $metrics = $analysis['metrics'] ?? [];
        $activation = $metrics['activation_metrics'] ?? ($agents['activation_agent']['result'] ?? ($agents['activation_agent'] ?? []));
        $retention = $metrics['retention_metrics'] ?? ($agents['retention_agent']['result'] ?? ($agents['retention_agent'] ?? []));
        $monetization = $metrics['monetization_metrics'] ?? ($agents['monetization_agent']['result'] ?? ($agents['monetization_agent'] ?? []));
        $aiDecision = $agents['ai_final_decision_agent'] ?? ($agents['ai_decision_agent'] ?? []);
        $decisionPackage = $analysis['decision_package'] ?? [];
        $aiResult = $decisionPackage['final_decision'] ?? ($aiDecision['result'] ?? []);
        $aiActivationAgent = $agents['ai_activation_agent'] ?? [];
        $aiRetentionAgent = $agents['ai_retention_agent'] ?? [];
        $aiMonetizationAgent = $agents['ai_monetization_agent'] ?? [];
        $aiVersionAgent = $agents['ai_version_agent'] ?? [];
        $aiAdsAgent = $agents['ai_ads_agent'] ?? [];
        $aiTomorrowForecastAgent = $agents['ai_tomorrow_forecast_agent'] ?? [];
        $aiActivationResult = $aiActivationAgent['result'] ?? [];
        $aiRetentionResult = $aiRetentionAgent['result'] ?? [];
        $aiMonetizationResult = $aiMonetizationAgent['result'] ?? [];
        $aiVersionResult = $aiVersionAgent['result'] ?? [];
        $aiAdsResult = $aiAdsAgent['result'] ?? [];
        $aiTomorrowForecastResult = $aiTomorrowForecastAgent['result'] ?? [];
        $agentRequestMetrics = $analysis['agent_request_metrics'] ?? [];
        $showRequestMetrics = config('ai_growth_doctor.ai.show_request_metrics', false);
        $tomorrowForecastMetrics = $metrics['tomorrow_forecast_metrics'] ?? [];
        $adsMetrics = $metrics['ads_metrics'] ?? [];
        $guardrailPolicy = $metrics['guardrail_policy'] ?? [];
        $deterministicGuardrailBasis = $aiResult['deterministic_guardrail_decision_basis'] ?? [];
        $guardrailDeterministicDecision = $guardrailPolicy['deterministic_decision'] ?? [];
        $guardrailTriggered = $guardrailPolicy['triggered_guardrails'] ?? [];
        $tomorrowPredictedMetrics = $aiTomorrowForecastResult['predicted_metrics'] ?? ($tomorrowForecastMetrics['predicted_metrics'] ?? []);
        $tomorrowGuardrails = $aiTomorrowForecastResult['guardrail_assessment'] ?? ($tomorrowForecastMetrics['guardrails'] ?? []);
        $forecastMeta = [
            'run_date' => $aiTomorrowForecastResult['run_date'] ?? ($tomorrowForecastMetrics['run_date'] ?? null),
            'run_timestamp' => $aiTomorrowForecastResult['run_timestamp'] ?? ($tomorrowForecastMetrics['run_timestamp'] ?? null),
            'data_as_of_date' => $aiTomorrowForecastResult['data_as_of_date'] ?? ($tomorrowForecastMetrics['data_as_of_date'] ?? null),
            'actual_data_available_until' => $aiTomorrowForecastResult['actual_data_available_until'] ?? ($tomorrowForecastMetrics['actual_data_available_until'] ?? null),
            'forecast_for_date' => $aiTomorrowForecastResult['forecast_for_date'] ?? ($tomorrowForecastMetrics['forecast_for_date'] ?? null),
            'evaluation_ready_after' => $aiTomorrowForecastResult['evaluation_ready_after'] ?? ($tomorrowForecastMetrics['evaluation_ready_after'] ?? null),
            'evaluation_status' => $aiTomorrowForecastResult['evaluation_status'] ?? ($tomorrowForecastMetrics['evaluation_status'] ?? null),
            'evaluation_rule' => $aiTomorrowForecastResult['evaluation_rule'] ?? ($tomorrowForecastMetrics['evaluation_rule'] ?? null),
        ];
        $charts = $analysis['charts'] ?? [];
        $growthScore = $aiResult['growth_health_score'] ?? [];
        $businessImpact = $aiResult['business_impact_estimate'] ?? [];
        $upliftEstimate = $businessImpact['estimated_uplift_if_fixed'] ?? [];
        $agentDebate = $aiResult['agent_debate_summary'] ?? [];
        $objectiveEvaluation = $aiResult['objective_evaluation_plan'] ?? [];
        $conflictRule = $aiResult['conflict_resolution_rule'] ?? [];
        $operationalActionPlan = $aiResult['operational_action_plan'] ?? [];
        $previousDecisionEvaluation = $aiResult['previous_decision_evaluation'] ?? [];
        $agentDebateTrace = $aiResult['agent_debate_trace'] ?? [];
        $tomorrowForecastDecisionImpact = $aiResult['tomorrow_forecast_decision_impact'] ?? [];
        $adsDecisionImpact = $aiResult['ads_decision_impact'] ?? [];
        $forecastEvaluationDecisionImpact = $aiResult['forecast_evaluation_decision_impact'] ?? [];
        $forecastCalibrationDecisionImpact = $aiResult['forecast_calibration_decision_impact'] ?? [];
        $decisionRiskAssessment = $aiResult['decision_risk_assessment'] ?? [];
        $structuredNegotiation = $analysis['structured_negotiation'] ?? ($agents['structured_negotiation']['result'] ?? []);
        $negotiationRules = $structuredNegotiation['rules'] ?? [];
        $negotiationExecution = $structuredNegotiation['execution'] ?? [];
        $roundSummaries = $structuredNegotiation['round_summaries'] ?? [];
        $negotiationResponses = $structuredNegotiation['agent_responses'] ?? [];
        $negotiationTimeline = $structuredNegotiation['negotiation_timeline'] ?? [];
        $conflictMatrix = $analysis['conflict_matrix'] ?? ($structuredNegotiation['conflict_matrix'] ?? ($structuredNegotiation['conflicts'] ?? []));
        $negotiationSummary = $analysis['negotiation_summary'] ?? ($structuredNegotiation['summary'] ?? []);
        $negotiationUiSummary = $structuredNegotiation['ui_summary'] ?? [];
        $baselineComparison = $structuredNegotiation['baseline_comparison'] ?? [];
        $singleAgentBaseline = $baselineComparison['single_agent_baseline'] ?? [];
        $agentSocietyBaseline = $baselineComparison['agent_society'] ?? [];
        $quantitativeBaselineComparison = $decisionPackage['quantitative_baseline_comparison']
            ?? ($structuredNegotiation['quantitative_baseline_comparison'] ?? ($analysis['quantitative_baseline_comparison'] ?? []));
        $quantitativeSingleAgent = $quantitativeBaselineComparison['single_agent_baseline'] ?? [];
        $quantitativeAgentSociety = $quantitativeBaselineComparison['agent_society'] ?? [];
        $quantitativeDelta = $quantitativeBaselineComparison['delta'] ?? [];
        $quantitativeRows = [
            ['Evidence domains used', 'evidence_domains_used'],
            ['Source metric refs used', 'source_metric_ref_count'],
            ['Resolved material tensions detected', 'resolved_material_tensions_detected'],
            ['Minor bounded cautions detected', 'minor_bounded_cautions_detected'],
            ['Safety-bounded revisions', 'safety_bounded_revisions'],
            ['Guardrail blocks used', 'guardrail_blocks_used'],
            ['Action items', 'action_items_count'],
            ['Action domains', 'action_domains_count'],
            ['Cross-domain constraints', 'cross_domain_constraints_count'],
            ['Evidence coverage score', 'evidence_coverage_score'],
            ['Caveat coverage score', 'caveat_coverage_score'],
            ['Decision completeness score', 'decision_completeness_score'],
            ['Unsafe/overbroad action risk', 'unsafe_or_overbroad_action_risk'],
        ];
        $forecastCalibration = $analysis['evaluations']['forecast_model_calibration'] ?? [];
        $forecastCalibrationTrustScore = $forecastCalibrationDecisionImpact['trust_score']
            ?? ($forecastCalibration['trust_score']['updated_score'] ?? null);
        $forecastCalibrationTrustInterpretation = $forecastCalibrationDecisionImpact['trust_interpretation']
            ?? ($forecastCalibration['trust_score']['interpretation'] ?? 'not_available');
        $forecastCalibrationRole = $forecastCalibrationDecisionImpact['forecast_role']
            ?? ($forecastCalibration['decision_instruction']['forecast_role'] ?? 'not_available');
        $forecastEvaluations = $analysis['evaluations']['forecast_evaluations'] ?? [];
        $evaluatedForecasts = $forecastEvaluations['evaluated'] ?? [];
        $latestForecastEvaluation = $forecastEvaluations['latest_evaluation'] ?? ($evaluatedForecasts[0] ?? []);
        $latestRetentionForecastEvaluation = [];
        $latestAnyRetentionForecastEvaluation = [];
        $actualDataAvailableUntilForRetention = (string) ($forecastEvaluations['actual_data_available_until'] ?? '');
        $latestRetentionEligibleByAge = [];

        foreach (($evaluatedForecasts ?? []) as $evaluation) {
            $metricRows = $evaluation['metric_evaluations'] ?? [];
            $hasRetentionMetric = false;
            $hasMatureRetentionMetric = false;
            $hasPendingRetentionMetric = false;

            foreach (($metricRows ?? []) as $groupName => $metrics) {
                foreach (($metrics ?? []) as $metricName => $row) {
                    if (in_array($metricName, ['habit_7d_rate', 'avg_log_days_7d'], true)) {
                        $hasRetentionMetric = true;

                        if (($row['quality'] ?? null) === 'pending_maturity') {
                            $hasPendingRetentionMetric = true;
                        } else {
                            $hasMatureRetentionMetric = true;
                        }
                    }
                }
            }

            if ($hasRetentionMetric && empty($latestAnyRetentionForecastEvaluation)) {
                $latestAnyRetentionForecastEvaluation = $evaluation;
            }

            $forecastForDate = (string) ($evaluation['forecast_for_date'] ?? '');
            $retentionEligibleByAge = false;
            if ($forecastForDate !== '' && $actualDataAvailableUntilForRetention !== '') {
                $retentionRequiredActualUntil = date('Y-m-d', strtotime($forecastForDate . ' +6 day'));
                $retentionEligibleByAge = strcmp($actualDataAvailableUntilForRetention, $retentionRequiredActualUntil) >= 0;
            }

            if ($hasRetentionMetric && $retentionEligibleByAge && empty($latestRetentionEligibleByAge)) {
                $latestRetentionEligibleByAge = $evaluation;
            }

            if ($hasMatureRetentionMetric) {
                $latestRetentionForecastEvaluation = $evaluation;
                break;
            }
        }

        if (empty($latestRetentionForecastEvaluation) && !empty($latestRetentionEligibleByAge)) {
            $latestRetentionForecastEvaluation = $latestRetentionEligibleByAge;
        }

        if (empty($latestRetentionForecastEvaluation)) {
            $latestRetentionForecastEvaluation = $latestAnyRetentionForecastEvaluation;
        }
        $forecastEvaluationSummary = $latestForecastEvaluation['summary'] ?? [];
        $forecastMetricEvaluations = $latestForecastEvaluation['metric_evaluations'] ?? [];
        $retentionSourceMetricEvaluations = $latestRetentionForecastEvaluation['metric_evaluations'] ?? [];
        $dailyForecastMetricEvaluations = [];
        $retentionForecastMetricEvaluations = [];

        foreach (($forecastMetricEvaluations ?? []) as $groupName => $metrics) {
            foreach (($metrics ?? []) as $metricName => $row) {
                if (!in_array($metricName, ['habit_7d_rate', 'avg_log_days_7d'], true)) {
                    $dailyForecastMetricEvaluations[$groupName][$metricName] = $row;
                }
            }
        }

        foreach (($retentionSourceMetricEvaluations ?? []) as $groupName => $metrics) {
            foreach (($metrics ?? []) as $metricName => $row) {
                if (in_array($metricName, ['habit_7d_rate', 'avg_log_days_7d'], true)) {
                    $retentionForecastMetricEvaluations[$groupName][$metricName] = $row;
                }
            }
        }

        $dailyForecastMetricsPendingMaturity = 0;
        foreach (($dailyForecastMetricEvaluations ?? []) as $groupName => $metrics) {
            foreach (($metrics ?? []) as $metricName => $row) {
                if (($row['quality'] ?? null) === 'pending_maturity') {
                    $dailyForecastMetricsPendingMaturity++;
                }
            }
        }

        $retentionForecastMetricsPendingMaturity = 0;
        foreach (($retentionForecastMetricEvaluations ?? []) as $groupName => $metrics) {
            foreach (($metrics ?? []) as $metricName => $row) {
                if (($row['quality'] ?? null) === 'pending_maturity') {
                    $retentionForecastMetricsPendingMaturity++;
                }
            }
        }
        $forecastEvaluationHitRate = $forecastEvaluationSummary['hit_rate'] ?? null;
        $forecastEvaluationQuality = $forecastEvaluationSummary['forecast_quality'] ?? ($forecastEvaluations['status'] ?? 'not_available');
        $operatingDecision = $aiResult['operating_decision'] ?? [];
        $todayOperatorSummary = $aiResult['today_operator_summary'] ?? null;
        $fallbackDebateTrace = [
            [
                'step' => 1,
                'agent' => 'Activation Agent',
                'dialogue_turn' => 'Activation Agent: The add-food flow is not the main bottleneck. Once users reach the workspace, food_add_success looks reasonably healthy; the problem is getting sessions into the workspace.',
                'evidence' => $agentDebate['activation_agent_view'] ?? ($aiActivationResult['diagnosis'] ?? ($aiResult['main_diagnosis'] ?? '-')),
                'objection_or_veto' => 'Rejects blaming the add-food feature as the main root cause.',
                'vote' => 'investigate',
                'impact_on_final_decision' => 'The decision focus shifts to improving workspace entry and onboarding/home CTA.',
            ],
            [
                'step' => 2,
                'agent' => 'Monetization Agent',
                'dialogue_turn' => 'Monetization Agent: There is an early revenue signal, but the purchase sample is small and a global paywall risks appearing too early.',
                'evidence' => $agentDebate['monetization_agent_view'] ?? ($aiMonetizationResult['diagnosis'] ?? ($aiResult['business_status'] ?? '-')),
                'objection_or_veto' => 'Keeps the revenue opportunity testable, but only through segmentation.',
                'vote' => $operatingDecision['monetization_decision']['decision'] ?? 'segment_only',
                'impact_on_final_decision' => 'Monetization is not turned off, but limited to cohorts that already reached the value moment.',
            ],
            [
                'step' => 3,
                'agent' => 'Retention Agent',
                'dialogue_turn' => 'Retention Agent: I caution against aggressive scaling because D1/habit is still weak; extra traffic will leak before becoming a habit.',
                'evidence' => $agentDebate['retention_agent_view'] ?? ($aiRetentionResult['diagnosis'] ?? ($aiResult['business_status'] ?? '-')),
                'objection_or_veto' => $aiResult['agent_conflicts'][0] ?? 'Constrains ads/paywall scaling before D1 logged rate improves.',
                'vote' => 'caution_against_scale',
                'impact_on_final_decision' => 'Ads are held, and product priority shifts to retention and the D0-D1 loop.',
            ],
            [
                'step' => 4,
                'agent' => 'Version Agent',
                'dialogue_turn' => 'Version Agent: Release does not look like the main risk source; rollout can continue gradually with guardrails.',
                'evidence' => $agentDebate['version_agent_view'] ?? ($aiVersionResult['diagnosis'] ?? ($operatingDecision['release_decision']['reason'] ?? '-')),
                'objection_or_veto' => $aiResult['agent_conflicts'][1] ?? null,
                'vote' => $operatingDecision['release_decision']['decision'] ?? 'continue_with_monitoring',
                'impact_on_final_decision' => 'The final decision does not choose rollback; focus stays on retention and monetization timing.',
            ],
            [
                'step' => 5,
                'agent' => 'Ads Agent',
                'dialogue_turn' => 'Ads Agent: I read acquisition performance and campaign lifecycle. The old Volume Stabil campaign should not automatically be treated as the main campaign if it is marked degraded legacy and Volume Install Reset exists as the reset successor.',
                'evidence' => $agentDebate['ads_agent_view'] ?? ($aiAdsResult['main_diagnosis'] ?? ($adsMetrics['diagnosis'] ?? '-')),
                'objection_or_veto' => $aiAdsResult['campaign_lifecycle_interpretation']['operator_action_interpretation'] ?? 'Do not interpret pausing/reducing Volume Stabil as shutting down acquisition if the reset campaign is being evaluated.',
                'vote' => $aiAdsResult['ads_verdict'] ?? ($adsMetrics['ads_verdict']['decision'] ?? 'monitor_ads'),
                'impact_on_final_decision' => $aiAdsResult['impact_on_final_decision'] ?? ($adsMetrics['ads_verdict']['final_decision_impact'] ?? 'Ads become supporting evidence for the budget decision.'),
            ],
            [
                'step' => 6,
                'agent' => 'Tomorrow Forecast Agent',
                'dialogue_turn' => 'Tomorrow Forecast Agent: Tomorrow forecast strengthens today decision guardrails; scaling is allowed only if predicted activation/retention risk stays safe.',
                'evidence' => 'Forecast date: ' . ($aiTomorrowForecastResult['forecast_for_date'] ?? ($tomorrowForecastMetrics['forecast_for_date'] ?? '-')) . '; scaling guardrail: ' . ($tomorrowGuardrails['scaling_guardrail'] ?? '-') . '; main risk: ' . ($aiTomorrowForecastResult['main_predicted_risk'] ?? '-'),
                'objection_or_veto' => ($tomorrowGuardrails['scaling_guardrail'] ?? null) === 'block_scaling'
                    ? 'Forecast blocks aggressive scaling because tomorrow guardrails are predicted to be unsafe.'
                    : 'Forecast does not block small experiments, but still requires actual evaluation tomorrow.',
                'vote' => ($tomorrowGuardrails['scaling_guardrail'] ?? null) === 'block_scaling'
                    ? 'forecast_blocks_scaling'
                    : 'forecast_allows_cautious_test',
                'impact_on_final_decision' => $aiTomorrowForecastResult['decision_impact_today'] ?? 'Forecast becomes a forward-looking guardrail for today decision.',
            ],
            [
                'step' => 7,
                'agent' => 'Final Decision Agent',
                'dialogue_turn' => 'Final Decision Agent: The retention constraint is stronger than monetization upside; today decision is Hold & Optimize, not aggressive scaling.',
                'evidence' => $agentDebate['final_resolution'] ?? ($aiResult['main_diagnosis'] ?? ($aiResult['business_verdict_reasoning'] ?? '-')),
                'objection_or_veto' => 'Combines revenue signal, release safety, and retention guardrail into one operating decision.',
                'vote' => strtolower((string) ($aiResult['business_verdict'] ?? 'hold_and_optimize')),
                'impact_on_final_decision' => $todayOperatorSummary ?? ($aiResult['executive_summary'] ?? '-'),
            ],
        ];
        $debateLog = !empty($agentDebateTrace) ? $agentDebateTrace : $fallbackDebateTrace;
        $decisionCards = [
            [
                'title' => 'Ads',
                'data' => $operatingDecision['ads_decision'] ?? [],
                'metric_key' => 'guardrail_metric',
                'metric_label' => 'Guardrail',
            ],
            [
                'title' => 'Release',
                'data' => $operatingDecision['release_decision'] ?? [],
                'metric_key' => 'guardrail_metric',
                'metric_label' => 'Guardrail',
            ],
            [
                'title' => 'Product',
                'data' => $operatingDecision['product_decision'] ?? [],
                'metric_key' => 'success_metric',
                'metric_label' => 'Success Metric',
            ],
            [
                'title' => 'Monetization',
                'data' => $operatingDecision['monetization_decision'] ?? [],
                'metric_key' => 'guardrail_metric',
                'metric_label' => 'Guardrail',
            ],
        ];

        $verdict = $aiResult['business_verdict'] ?? ($decision['verdict'] ?? 'UNKNOWN');
        $isWarning = in_array($verdict, ['HOLD_AND_OPTIMIZE', 'ROLLBACK_RISK']);
        $verdictLabel = $isWarning ? 'Hold & Optimize' : 'Continue Monitoring';
        $verdictTone = $isWarning ? 'Attention Required' : 'Stable';

        $statusBadgeClass = function ($status) {
            $status = strtolower((string) $status);

            if (in_array($status, ['healthy', 'safe', 'active_signal', 'continue', 'stable'])) {
                return 'bg-emerald-100 text-emerald-700';
            }

            if (in_array($status, ['warning', 'caution', 'noisy', 'insufficient_sample', 'need_more_data'])) {
                return 'bg-amber-100 text-amber-700';
            }

            if (in_array($status, ['critical', 'risk', 'risky', 'rollback'])) {
                return 'bg-rose-100 text-rose-700';
            }

            return 'bg-slate-100 text-slate-600';
        };

        $renderList = function ($items) {
            return is_array($items) ? $items : [];
        };

        $displayValue = function ($value, $fallback = '-') {
            if ($value === null || $value === '') {
                return $fallback;
            }

            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            if (is_array($value) || is_object($value)) {
                $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                return $json !== false ? $json : $fallback;
            }

            return (string) $value;
        };

        $displayShortValue = function ($value, $fallback = '-') use ($displayValue) {
            $text = $displayValue($value, $fallback);
            $text = preg_replace('/\s+/', ' ', (string) $text);

            if (mb_strlen($text) > 140) {
                return mb_substr($text, 0, 140) . '...';
            }

            return $text;
        };

        $dashboardRunId = $analysis['meta']['run_id'] ?? null;
        $auditTrace = $analysis['full_audit_trace'] ?? [];
        $auditTraceDownloadUrl = !empty($auditTrace['available']) ? ($auditTrace['download_url'] ?? null) : null;
        $tomorrowExecution = $aiTomorrowForecastAgent['execution'] ?? [];
        $tomorrowRequestMetrics = $aiTomorrowForecastAgent['request_metrics'] ?? ($agentRequestMetrics['ai_tomorrow_forecast_agent'] ?? []);
        $tomorrowResponseMetrics = $aiTomorrowForecastAgent['response_metrics'] ?? [];
    @endphp

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 overflow-x-hidden">
        <div class="mb-8">
            <div class="text-sm text-slate-500 mb-1">
                {{ $analysis['meta']['app_name'] ?? 'Unknown App' }}
                · {{ $analysis['meta']['window_start'] ?? '-' }}
                s/d {{ $analysis['meta']['window_end'] ?? '-' }}
                · analyzed {{ $analysis['meta']['analyzed_at'] ?? '-' }}
            </div>

            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold mb-2">
                        AI Growth Doctor
                    </h1>

                    <p class="text-slate-600">
                        Sequential data preparation → parallel specialist evidence collection → sequential final decision synthesis.
                    </p>
                </div>

                <div class="shrink-0 flex flex-col sm:flex-row gap-2">
                    @if ($dashboardRunId)
                        <a
                            href="{{ route('ai-growth-doctor.runs.graph-view', ['runId' => $dashboardRunId]) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center rounded-full bg-indigo-700 text-white px-4 py-2 text-sm font-semibold shadow-sm hover:bg-indigo-800 transition"
                        >
                            Open Agent Graph
                        </a>
                    @endif
                    @if ($auditTraceDownloadUrl)
                        <a
                            href="{{ $auditTraceDownloadUrl }}"
                            data-skip-page-loading="true"
                            class="inline-flex items-center justify-center rounded-full bg-white text-slate-700 border border-slate-200 px-4 py-2 text-sm font-semibold shadow-sm hover:bg-slate-50 transition"
                        >
                            Download Audit JSON
                        </a>
                    @endif
                    <button type="button" id="startAsyncRunButton" class="inline-flex items-center justify-center rounded-full bg-slate-900 text-white px-4 py-2 text-sm font-semibold shadow-sm hover:bg-slate-700 transition">
                        Run Live Agent Progress
                    </button>
                    <button type="button" data-show-loading="true" onclick="window.location.reload()" class="inline-flex items-center justify-center rounded-full bg-white text-slate-700 border border-slate-200 px-4 py-2 text-sm font-semibold shadow-sm hover:bg-slate-50 transition">
                        Refresh Dashboard
                    </button>
                </div>
            </div>
        </div>

        <div id="asyncRunPanel" class="hidden bg-white rounded-2xl mb-8 shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-5 border-b border-slate-200 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 mb-1">Live Agent Progress</div>
                    <h2 class="text-xl font-bold">Real-time Multi-Agent Run</h2>
                    <p id="asyncRunNote" class="text-sm text-slate-500 mt-1">Sequential setup → parallel specialist fan-out → final decision fan-in.</p>
                </div>
                <div class="text-right">
                    <div id="asyncRunStatusBadge" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-slate-100 text-slate-700">IDLE</div>
                    <div id="asyncRunId" class="text-xs text-slate-400 mt-2"></div>
                </div>
            </div>

            <div class="p-5">
                <div class="flex items-center justify-between text-xs text-slate-500 mb-2">
                    <span>Progress</span>
                    <span id="asyncProgressPercent">0%</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2 mb-5">
                    <div id="asyncProgressBar" class="bg-slate-900 h-2 rounded-full transition-all duration-500" style="width: 0%"></div>
                </div>

                <div id="asyncStepsContainer" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3"></div>

                <div id="asyncRunResultActions" class="hidden mt-5 bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                    <div class="font-semibold text-emerald-900 mb-1">Run completed</div>
                    <p class="text-sm text-emerald-800 mb-3">All agents completed. Refresh the dashboard to load the latest result from this run.</p>
                    <button type="button" data-show-loading="true" onclick="window.location.reload()" class="inline-flex items-center rounded-full bg-emerald-700 text-white px-4 py-2 text-sm font-semibold hover:bg-emerald-800 transition">
                        Load Result Into Dashboard
                    </button>
                </div>

                <div id="asyncRunError" class="hidden mt-5 bg-rose-50 border border-rose-200 rounded-xl p-4 text-sm text-rose-800"></div>
            </div>
        </div>

        <div class="bg-white rounded-2xl mb-8 shadow-sm border border-slate-200 overflow-hidden">
            <div class="flex">
                <div class="w-1.5 {{ $isWarning ? 'bg-amber-500' : 'bg-emerald-500' }}"></div>

                <div class="flex-1 p-6">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 mb-2">
                                Latest Operating Verdict
                            </div>
                            <div class="text-2xl md:text-3xl font-bold tracking-tight text-slate-950">
                                {{ $verdictLabel }}
                            </div>
                        </div>

                        <div class="shrink-0 inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $isWarning ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' }}">
                            <span class="w-2 h-2 rounded-full {{ $isWarning ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                            {{ $verdictTone }}
                        </div>
                    </div>

                    <p class="text-slate-700 text-base md:text-lg leading-relaxed max-w-5xl">
                        {{ $displayValue($aiResult['main_diagnosis'] ?? ($decision['summary'] ?? 'No summary available.')) }}
                    </p>
                    @if (!empty($forecastMeta['data_as_of_date']) || !empty($forecastMeta['forecast_for_date']))
                        <div class="mt-4 flex flex-wrap gap-2 text-xs text-slate-600">
                            @if (!empty($forecastMeta['data_as_of_date']))
                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 ring-1 ring-slate-200">
                                    Data as of {{ $forecastMeta['data_as_of_date'] }}
                                </span>
                            @endif
                            @if (!empty($forecastMeta['forecast_for_date']))
                                <span class="inline-flex rounded-full bg-blue-50 text-blue-700 px-3 py-1 ring-1 ring-blue-200">
                                    Forecast for {{ $forecastMeta['forecast_for_date'] }}
                                </span>
                            @endif
                            @if (!empty($forecastMeta['evaluation_ready_after']))
                                <span class="inline-flex rounded-full bg-amber-50 text-amber-700 px-3 py-1 ring-1 ring-amber-200">
                                    Evaluation after {{ $forecastMeta['evaluation_ready_after'] }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if (!empty($operatingDecision))
            <div class="mb-8" x-data="{ openDecision: null }">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-xl font-bold">Current Operating Decisions</h2>
                        <p class="text-sm text-slate-500">Operating decisions based on the latest available data. Click a card to inspect the reason, next action, and guardrail.</p>
                    </div>
                    @if (!empty($todayOperatorSummary))
                        <div class="max-w-2xl text-sm text-slate-600 bg-white border border-slate-200 rounded-2xl px-4 py-3 shadow-sm">
                            {{ $displayValue($todayOperatorSummary) }}
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    @foreach ($decisionCards as $card)
                        @php
                            $cardData = $card['data'] ?? [];
                            $decisionValue = strtolower((string) ($cardData['decision'] ?? ''));
                            $toneClass = 'bg-slate-50 text-slate-700 ring-slate-200';
                            $dotClass = 'bg-slate-400';

                            if (strpos($decisionValue, 'hold') !== false || strpos($decisionValue, 'reduce') !== false || strpos($decisionValue, 'pause') !== false || strpos($decisionValue, 'segment_only') !== false) {
                                $toneClass = 'bg-amber-50 text-amber-700 ring-amber-200';
                                $dotClass = 'bg-amber-500';
                            }

                            if (strpos($decisionValue, 'continue') !== false || strpos($decisionValue, 'increase') !== false || strpos($decisionValue, 'scale') !== false) {
                                $toneClass = 'bg-emerald-50 text-emerald-700 ring-emerald-200';
                                $dotClass = 'bg-emerald-500';
                            }

                            if (strpos($decisionValue, 'rollback') !== false || strpos($decisionValue, 'not_enough_data') !== false) {
                                $toneClass = 'bg-rose-50 text-rose-700 ring-rose-200';
                                $dotClass = 'bg-rose-500';
                            }

                            $pillText = trim((string) ($cardData['label'] ?? ''));
                            if ($pillText === '') {
                                $pillText = trim((string) ($cardData['decision'] ?? ''));
                            }
                            if ($pillText === '') {
                                $pillText = 'no_decision';
                            }
                            if (mb_strlen($pillText) > 30) {
                                $pillText = mb_substr($pillText, 0, 30) . '...';
                            }
                        @endphp

                        <button type="button" @click="openDecision = openDecision === '{{ $card['title'] }}' ? null : '{{ $card['title'] }}'" class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0 text-left hover:border-slate-400 transition">
                            <div class="flex items-start justify-between gap-3 mb-4">
                                <div class="min-w-0">
                                    <div class="text-sm text-slate-500 mb-2">{{ $card['title'] }}</div>
                                    <div class="flex w-full max-w-full min-w-0 items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold ring-1 {{ $toneClass }}" title="{{ strtoupper(str_replace('_', ' ', $displayShortValue($pillText))) }}">
                                        <span class="w-1.5 h-1.5 shrink-0 rounded-full {{ $dotClass }}"></span>
                                        <span class="flex-1 min-w-0 overflow-hidden text-ellipsis whitespace-nowrap text-left">
                                            {{ strtoupper(str_replace('_', ' ', $displayShortValue($pillText))) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-slate-400 text-sm transition-transform duration-300" :class="openDecision === '{{ $card['title'] }}' ? 'rotate-180' : ''">⌄</div>
                            </div>

                            @if (isset($cardData['confidence_score']))
                                <div>
                                    <div class="flex justify-between text-xs text-slate-500 mb-1">
                                        <span>Confidence</span>
                                        <span>{{ $cardData['confidence_score'] }}/100</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-1.5">
                                        <div class="bg-slate-800 h-1.5 rounded-full" style="width: {{ min(100, max(0, (int) $cardData['confidence_score'])) }}%"></div>
                                    </div>
                                </div>
                            @endif
                        </button>
                    @endforeach
                </div>

                @foreach ($decisionCards as $card)
                    @php
                        $cardData = $card['data'] ?? [];
                        $pillText = trim((string) ($cardData['label'] ?? ''));
                        if ($pillText === '') {
                            $pillText = trim((string) ($cardData['decision'] ?? ''));
                        }
                        if ($pillText === '') {
                            $pillText = 'no_decision';
                        }
                    @endphp
                    <div
                        x-show="openDecision === '{{ $card['title'] }}'"
                        x-cloak
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                        x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.98]"
                        class="mt-4 bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0"
                    >
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div>
                                <div class="text-sm text-slate-500 mb-1">{{ $card['title'] }} Decision Detail</div>
                                <h3 class="text-xl font-bold">{{ $displayShortValue($pillText ?? ($cardData['label'] ?? ($cardData['decision'] ?? '-'))) }}</h3>
                            </div>
                            <button type="button" @click="openDecision = null" class="text-xs text-slate-500 hover:text-slate-900">Close</button>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="lg:col-span-1 bg-slate-50 border border-slate-200 rounded-xl p-4">
                                <div class="font-semibold text-slate-800 mb-1">Reason</div>
                                <p class="text-sm text-slate-700 leading-relaxed break-words whitespace-pre-wrap">{{ $displayValue($cardData['reason'] ?? '-') }}</p>
                            </div>
                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                                <div class="font-semibold text-slate-800 mb-1">Next Action</div>
                                <p class="text-sm text-slate-700 leading-relaxed break-words whitespace-pre-wrap">{{ $displayValue($cardData['next_action'] ?? '-') }}</p>
                            </div>
                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                                <div class="font-semibold text-slate-800 mb-1">{{ $card['metric_label'] }}</div>
                                <p class="text-sm text-slate-700 leading-relaxed break-words whitespace-pre-wrap">{{ $displayValue($cardData[$card['metric_key']] ?? '-') }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if (!empty($growthScore) || !empty($businessImpact) || !empty($agentDebate) || !empty($agentDebateTrace) || !empty($conflictRule) || !empty($operationalActionPlan) || !empty($previousDecisionEvaluation) || !empty($forecastEvaluations))
            <div class="mb-8" x-data="{ openInsight: null }">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-7 gap-5 mb-4">
                    <button type="button" @click="openInsight = openInsight === 'score' ? null : 'score'" class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0 text-left hover:border-slate-400 transition xl:col-span-2">
                        <div class="text-sm text-slate-500 mb-1">Growth Health Score</div>
                        <div class="flex items-end gap-2 mb-3">
                            <div class="text-4xl font-bold text-slate-950">{{ $growthScore['overall_score'] ?? '-' }}</div>
                            <div class="text-sm text-slate-500 mb-1">/100</div>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2 mb-3">
                            <div class="bg-slate-900 h-2 rounded-full transition-all duration-500" style="width: {{ min(100, max(0, (int) ($growthScore['overall_score'] ?? 0))) }}%"></div>
                        </div>
                        <div class="text-sm text-slate-700">Constraint: <strong>{{ $growthScore['main_constraint'] ?? '-' }}</strong></div>
                    </button>

                    <button type="button" @click="openInsight = openInsight === 'impact' ? null : 'impact'" class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0 text-left hover:border-slate-400 transition xl:col-span-2">
                        <div class="text-sm text-slate-500 mb-1">Business Impact Estimate</div>
                        <div class="text-xl font-bold mb-3 line-clamp-2">{{ $displayShortValue($businessImpact['growth_blocker'] ?? '-') }}</div>
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="bg-slate-50 rounded-xl p-2">
                                <div class="text-lg font-bold">{{ $upliftEstimate['extra_workspace_users_7d'] ?? '-' }}</div>
                                <div class="text-[11px] text-slate-500">workspace</div>
                            </div>
                            <div class="bg-slate-50 rounded-xl p-2">
                                <div class="text-lg font-bold">{{ $upliftEstimate['extra_food_add_success_users_7d'] ?? '-' }}</div>
                                <div class="text-[11px] text-slate-500">food logs</div>
                            </div>
                            <div class="bg-slate-50 rounded-xl p-2">
                                <div class="text-lg font-bold">{{ $upliftEstimate['extra_paywall_eligible_users_7d'] ?? '-' }}</div>
                                <div class="text-[11px] text-slate-500">paywall</div>
                            </div>
                        </div>
                        @if (($businessImpact['calculation_status'] ?? null) === 'missing_input')
                            <div class="mt-3 text-xs text-amber-700">Uplift estimate unavailable: missing input.</div>
                        @endif
                    </button>

                    <button type="button" @click="openInsight = openInsight === 'debate' ? null : 'debate'" class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0 text-left hover:border-indigo-300 hover:bg-indigo-50/40 transition">
                        <div class="flex h-full flex-col justify-between gap-4">
                            <div>
                                <div class="text-sm text-slate-500 mb-1">Final Decision Evidence Map</div>
                                <div class="text-2xl font-bold text-slate-950">{{ count($debateLog) }}</div>
                                <div class="text-xs text-slate-500 mt-1">signals</div>
                            </div>
                            <span class="inline-flex w-fit text-[11px] px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200">SYNTHESIS</span>
                        </div>
                    </button>


                    <button type="button" @click="openInsight = openInsight === 'operationalPlan' ? null : 'operationalPlan'" class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0 text-left hover:border-blue-300 hover:bg-blue-50/40 transition">
                        <div class="flex h-full flex-col justify-between gap-4">
                            <div>
                                <div class="text-sm text-slate-500 mb-1">Action Plan</div>
                                <div class="text-2xl font-bold text-slate-950">{{ count($operationalActionPlan) }}</div>
                                <div class="text-xs text-slate-500 mt-1">action{{ count($operationalActionPlan) === 1 ? '' : 's' }}</div>
                            </div>
                            <span class="inline-flex w-fit text-[11px] px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 ring-1 ring-blue-200">MEASURABLE</span>
                        </div>
                    </button>
                    <button type="button" @click="openInsight = openInsight === 'forecastEvaluation' ? null : 'forecastEvaluation'" class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0 text-left hover:border-emerald-300 hover:bg-emerald-50/40 transition">
                        <div class="flex h-full flex-col justify-between gap-4">
                            <div>
                                <div class="text-sm text-slate-500 mb-1">Forecast Evaluation</div>
                                <div class="text-2xl font-bold text-slate-950">
                                    {{ $forecastEvaluationHitRate !== null ? $forecastEvaluationHitRate . '%' : '-' }}
                                </div>
                                <div class="text-xs text-slate-500 mt-1">
                                    {{ strtoupper(str_replace('_', ' ', $forecastEvaluationQuality)) }}
                                </div>
                            </div>
                            <span class="inline-flex w-fit text-[11px] px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                                {{ (int) ($forecastEvaluations['evaluated_count'] ?? count($evaluatedForecasts)) }} EVALUATED
                            </span>
                        </div>
                    </button>
                </div>

                <div
                    x-show="openInsight === 'forecastEvaluation'"
                    x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.98]"
                    class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0 mb-4"
                >
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div>
                            <h3 class="text-lg font-bold">Forecast Evaluation</h3>
                            <p class="text-sm text-slate-500">Compares the forecast created from the previous checkpoint with actual metrics from the latest checkpoint.</p>
                        </div>
                        <button type="button" @click="openInsight = null" class="text-xs text-slate-500 hover:text-slate-900">Close</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Evaluation Status</div>
                            <div class="text-lg font-bold">{{ strtoupper(str_replace('_', ' ', $forecastEvaluations['status'] ?? '-')) }}</div>
                        </div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Actual Data Until</div>
                            <div class="text-lg font-bold">{{ $forecastEvaluations['actual_data_available_until'] ?? '-' }}</div>
                        </div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Evaluated</div>
                            <div class="text-lg font-bold">{{ $forecastEvaluations['evaluated_count'] ?? count($evaluatedForecasts) }}</div>
                        </div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Pending / Skipped</div>
                            <div class="text-lg font-bold">{{ (int) ($forecastEvaluations['pending_count'] ?? 0) }} / {{ (int) ($forecastEvaluations['skipped_count'] ?? 0) }}</div>
                        </div>
                    </div>

                    @if (!empty($latestForecastEvaluation))
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 mb-4">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-3">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700 mb-1">Latest Evaluation</div>
                                    <div class="text-lg font-bold text-emerald-950">
                                        Forecast {{ $latestForecastEvaluation['forecast_for_date'] ?? '-' }} from data {{ $latestForecastEvaluation['data_as_of_date'] ?? '-' }}
                                        @if (!empty($latestRetentionForecastEvaluation['forecast_for_date']))
                                            <div class="text-xs text-emerald-700 mt-1">
                                                Retention panel source: forecast {{ $latestRetentionForecastEvaluation['forecast_for_date'] ?? '-' }} from data {{ $latestRetentionForecastEvaluation['data_as_of_date'] ?? '-' }}
                                            </div>
                                        @endif
                                        @if (!empty($forecastEvaluations['latest_evaluation_meta']['selected_by']))
                                            <div class="text-xs text-emerald-700 mt-1">
                                                Selected latest by {{ $forecastEvaluations['latest_evaluation_meta']['selected_by'] ?? '-' }} · actual available until {{ $forecastEvaluations['actual_data_available_until'] ?? '-' }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <span class="inline-flex w-fit rounded-full px-3 py-1 text-[11px] font-semibold bg-white text-emerald-700 ring-1 ring-emerald-200">
                                    {{ strtoupper(str_replace('_', ' ', $forecastEvaluationQuality)) }}
                                </span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-3 text-sm">
                                <div class="bg-white/80 border border-emerald-200 rounded-xl p-3">
                                    <div class="text-xs text-emerald-700 mb-1">Metrics Evaluated</div>
                                    <strong>{{ $forecastEvaluationSummary['metrics_evaluated'] ?? '-' }}</strong>
                                </div>
                                <div class="bg-white/80 border border-emerald-200 rounded-xl p-3">
                                    <div class="text-xs text-emerald-700 mb-1">Metrics Hit</div>
                                    <strong>{{ $forecastEvaluationSummary['metrics_hit'] ?? '-' }}</strong>
                                </div>
                                <div class="bg-white/80 border border-amber-200 rounded-xl p-3">
                                    <div class="text-xs text-amber-700 mb-1">Pending Maturity</div>
                                    <strong>{{ $forecastEvaluationSummary['metrics_pending_maturity'] ?? 0 }}</strong>
                                </div>
                                <div class="bg-white/80 border border-emerald-200 rounded-xl p-3">
                                    <div class="text-xs text-emerald-700 mb-1">Hit Rate</div>
                                    <strong>{{ $forecastEvaluationSummary['hit_rate'] !== null && isset($forecastEvaluationSummary['hit_rate']) ? $forecastEvaluationSummary['hit_rate'] . '%' : '-' }}</strong>
                                </div>
                                <div class="bg-white/80 border border-emerald-200 rounded-xl p-3">
                                    <div class="text-xs text-emerald-700 mb-1">Quality</div>
                                    <strong>{{ strtoupper(str_replace('_', ' ', $forecastEvaluationSummary['forecast_quality'] ?? '-')) }}</strong>
                                </div>
                            </div>
                        </div>

                        @if ($dailyForecastMetricsPendingMaturity > 0)
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4 text-sm text-amber-900">
                                <div class="font-semibold mb-1">Cohort Maturity Note</div>
                                <div>
                                    Some daily metrics are not fair to evaluate yet because the required actual rows have not matured. This note now applies only to daily forecast evaluation. 7D retention metrics are separated into their own panel so they do not mix with the "yesterday prediction vs today realization" pattern.
                                </div>
                            </div>
                        @endif

                        @if (!empty($forecastEvaluationSummary['main_misses']))
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
                                <div class="font-semibold text-amber-900 mb-2">Main Misses</div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                    @foreach (($forecastEvaluationSummary['main_misses'] ?? []) as $miss)
                                        <div class="bg-white/80 border border-amber-200 rounded-xl p-3 text-amber-950">
                                            <div class="font-semibold">{{ $miss['group'] ?? '-' }} · {{ $miss['metric'] ?? '-' }}</div>
                                            <div class="text-xs mt-1">Quality: {{ strtoupper(str_replace('_', ' ', $miss['quality'] ?? 'miss')) }}</div>
                                            <div class="text-xs mt-1">Actual: {{ $miss['actual'] ?? '-' }} | Forecast: {{ $miss['forecast_low'] ?? '-' }} – {{ $miss['forecast_high'] ?? '-' }} ({{ $miss['forecast_point'] ?? '-' }})</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @php
                            $metricEvaluations = $dailyForecastMetricEvaluations;
                        @endphp
                        @if (!empty($metricEvaluations))
                            <div class="border border-slate-200 rounded-2xl overflow-hidden">
                                <div class="bg-slate-50 px-4 py-3 border-b border-slate-200 font-semibold">Metric Comparison</div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-slate-50 text-slate-500">
                                            <tr>
                                                <th class="text-left px-4 py-2">Group</th>
                                                <th class="text-left px-4 py-2">Metric</th>
                                                <th class="text-right px-4 py-2">Actual</th>
                                                <th class="text-right px-4 py-2">Low</th>
                                                <th class="text-right px-4 py-2">Point</th>
                                                <th class="text-right px-4 py-2">High</th>
                                                <th class="text-left px-4 py-2">Result</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach ($metricEvaluations as $groupName => $metrics)
                                                @foreach (($metrics ?? []) as $metricName => $row)
                                                    <tr>
                                                        @php
                                                            $rowQuality = $row['quality'] ?? '-';
                                                            $isPendingMaturity = $rowQuality === 'pending_maturity';
                                                            $badgeClass = $isPendingMaturity
                                                                ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'
                                                                : (($row['range_hit'] ?? false) ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-rose-50 text-rose-700 ring-1 ring-rose-200');
                                                        @endphp
                                                        <td class="px-4 py-2 text-slate-500">{{ $groupName }}</td>
                                                        <td class="px-4 py-2 font-medium text-slate-900">{{ $metricName }}</td>
                                                        <td class="px-4 py-2 text-right">
                                                            {{ $isPendingMaturity ? '-' : ($row['actual'] ?? '-') }}
                                                        </td>
                                                        <td class="px-4 py-2 text-right">{{ $row['forecast_low'] ?? '-' }}</td>
                                                        <td class="px-4 py-2 text-right">{{ $row['forecast_point'] ?? '-' }}</td>
                                                        <td class="px-4 py-2 text-right">{{ $row['forecast_high'] ?? '-' }}</td>
                                                        <td class="px-4 py-2">
                                                            <div class="flex flex-col gap-1">
                                                                <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $badgeClass }}">
                                                                    {{ strtoupper(str_replace('_', ' ', $rowQuality)) }}
                                                                </span>
                                                                @if ($isPendingMaturity && !empty($row['maturity']['required_actual_until']))
                                                                    <span class="text-[11px] text-slate-500">
                                                                        Ready after {{ $row['maturity']['required_actual_until'] }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if (!empty($retentionForecastMetricEvaluations))
                            <div class="mt-4 border border-indigo-200 rounded-2xl overflow-hidden">
                                <div class="bg-indigo-50 px-4 py-3 border-b border-indigo-200">
                                    <div class="font-semibold">7D Retention Forecast Evaluation</div>
                                    @if (!empty($latestRetentionForecastEvaluation['forecast_for_date']) || !empty($latestRetentionForecastEvaluation['data_as_of_date']))
                                        <div class="text-[11px] text-indigo-700 mt-1">
                                            Source snapshot: forecast {{ $latestRetentionForecastEvaluation['forecast_for_date'] ?? '-' }} from data {{ $latestRetentionForecastEvaluation['data_as_of_date'] ?? '-' }} · actual available until {{ $forecastEvaluations['actual_data_available_until'] ?? '-' }}
                                        </div>
                                    @endif
                                </div>

                                @if ($retentionForecastMetricsPendingMaturity > 0)
                                    <div class="bg-amber-50 border-b border-amber-200 px-4 py-3 text-sm text-amber-900">
                                        <div class="font-semibold mb-1">Retention Maturity Note</div>
                                        <div>
                                            Habit 7D and Avg Log Days 7D are treated as cohort/retention metrics that mature several days later. This panel is separated from daily forecast evaluation so it does not mix with the "yesterday prediction vs today realization" pattern.
                                        </div>
                                    </div>
                                @endif

                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-indigo-50 text-indigo-700">
                                            <tr>
                                                <th class="text-left px-4 py-2">Group</th>
                                                <th class="text-left px-4 py-2">Metric</th>
                                                <th class="text-right px-4 py-2">Actual</th>
                                                <th class="text-right px-4 py-2">Low</th>
                                                <th class="text-right px-4 py-2">Point</th>
                                                <th class="text-right px-4 py-2">High</th>
                                                <th class="text-left px-4 py-2">Result</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-indigo-100">
                                            @foreach ($retentionForecastMetricEvaluations as $groupName => $metrics)
                                                @foreach (($metrics ?? []) as $metricName => $row)
                                                    @php
                                                        $rowQuality = $row['quality'] ?? '-';
                                                        $isPendingMaturity = $rowQuality === 'pending_maturity';
                                                        $badgeClass = $isPendingMaturity
                                                            ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'
                                                            : (($row['range_hit'] ?? false) ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-rose-50 text-rose-700 ring-1 ring-rose-200');
                                                    @endphp
                                                    <tr>
                                                        <td class="px-4 py-2 text-slate-500">{{ $groupName }}</td>
                                                        <td class="px-4 py-2 font-medium text-slate-900">{{ $metricName }}</td>
                                                        <td class="px-4 py-2 text-right">
                                                            {{ $isPendingMaturity ? '-' : ($row['actual'] ?? '-') }}
                                                        </td>
                                                        <td class="px-4 py-2 text-right">{{ $row['forecast_low'] ?? '-' }}</td>
                                                        <td class="px-4 py-2 text-right">{{ $row['forecast_point'] ?? '-' }}</td>
                                                        <td class="px-4 py-2 text-right">{{ $row['forecast_high'] ?? '-' }}</td>
                                                        <td class="px-4 py-2">
                                                            <div class="flex flex-col gap-1">
                                                                <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $badgeClass }}">
                                                                    {{ strtoupper(str_replace('_', ' ', $rowQuality)) }}
                                                                </span>
                                                                @if ($isPendingMaturity && !empty($row['maturity']['required_actual_until']))
                                                                    <span class="text-[11px] text-slate-500">
                                                                        Ready after {{ $row['maturity']['required_actual_until'] }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-slate-600">
                            No forecast is ready for evaluation yet. Forecasts will be evaluated automatically after the latest checkpoint has a <code>meta.window_end</code> equal to or newer than <code>forecast_for_date</code>.
                        </div>
                    @endif
                </div>

                <div
                    x-show="openInsight === 'score'"
                    x-cloak
                    x-transition
                    class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0 mb-4"
                >
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <h3 class="text-lg font-bold">Growth Score Detail</h3>
                        <button type="button" @click="openInsight = null" class="text-xs text-slate-500 hover:text-slate-900">Close</button>
                    </div>
                    <p class="text-sm text-slate-600 mb-4">{{ $growthScore['score_explanation'] ?? 'Composite score from activation, retention, monetization, and release risk.' }}</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-slate-50 rounded-xl p-4"><div class="text-xs text-slate-500 mb-1">Activation</div><div class="text-2xl font-bold">{{ $growthScore['activation_score'] ?? '-' }}</div></div>
                        <div class="bg-slate-50 rounded-xl p-4"><div class="text-xs text-slate-500 mb-1">Retention</div><div class="text-2xl font-bold">{{ $growthScore['retention_score'] ?? '-' }}</div></div>
                        <div class="bg-slate-50 rounded-xl p-4"><div class="text-xs text-slate-500 mb-1">Monetization</div><div class="text-2xl font-bold">{{ $growthScore['monetization_score'] ?? '-' }}</div></div>
                        <div class="bg-slate-50 rounded-xl p-4"><div class="text-xs text-slate-500 mb-1">Release</div><div class="text-2xl font-bold">{{ $growthScore['release_score'] ?? '-' }}</div></div>
                    </div>
                </div>

                <div
                    x-show="openInsight === 'impact'"
                    x-cloak
                    x-transition
                    class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0 mb-4"
                >
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <h3 class="text-lg font-bold">Business Impact Detail</h3>
                        <button type="button" @click="openInsight = null" class="text-xs text-slate-500 hover:text-slate-900">Close</button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div class="bg-slate-50 rounded-xl p-4"><div class="text-slate-500 mb-1">Metric at risk</div><strong>{{ $displayShortValue($businessImpact['main_metric_at_risk'] ?? '-') }}</strong></div>
                        <div class="bg-slate-50 rounded-xl p-4"><div class="text-slate-500 mb-1">Revenue risk</div><strong>{{ $displayShortValue($businessImpact['revenue_risk'] ?? '-') }}</strong></div>
                        <div class="bg-slate-50 rounded-xl p-4"><div class="text-slate-500 mb-1">Revenue direction</div><strong>{{ $displayShortValue($upliftEstimate['revenue_direction'] ?? '-') }}</strong></div>
                        <div class="bg-slate-50 rounded-xl p-4"><div class="text-slate-500 mb-1">Assumption</div><span class="whitespace-pre-wrap">{{ $displayValue($upliftEstimate['assumption'] ?? '-') }}</span></div>
                    </div>
                </div>

                <div
                    x-data="{ showSignals: true, openEvidence: null, showLearning: false, showResolution: true, showScenario: false, }"
                    x-show="openInsight === 'debate'"
                    x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.98]"
                    class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0 mb-4"
                >
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div>
                            <h3 class="text-lg font-bold">Final Decision Evidence Map</h3>
                            <p class="text-sm text-slate-500">Evidence layers consumed by the Final Decision Agent. This is not a debate transcript or a step-by-step sequence; specialists are peer inputs and the final agent performs fan-in synthesis.</p>
                        </div>
                        <button type="button" @click="openInsight = null" class="text-xs text-slate-500 hover:text-slate-900">Close</button>
                    </div>

                    <button type="button" @click="showSignals = !showSignals" class="w-full mb-3 flex items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-left hover:bg-slate-100 transition">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Evidence Map</div>
                            <div class="text-sm text-slate-500">{{ count($debateLog) }} evidence items from specialist agents, deterministic guardrails, forecast/calibration, structured negotiation, and final resolution.</div>
                        </div>
                        <div class="shrink-0 text-xs font-semibold text-slate-500" x-text="showSignals ? 'Hide' : 'Show'"></div>
                    </button>

                    <div x-show="showSignals" x-cloak x-transition>
                        <div class="overflow-hidden rounded-2xl border border-slate-200">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-slate-500">
                                        <tr>
                                            <th class="text-left px-4 py-3">Layer</th>
                                            <th class="text-left px-4 py-3">Signal</th>
                                            <th class="text-left px-4 py-3">Decision Weight</th>
                                            <th class="text-left px-4 py-3">Detail</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($debateLog as $trace)
                                @php
                                    $vote = strtolower((string) ($trace['vote'] ?? ''));
                                    $voteTone = 'bg-slate-50 text-slate-700 ring-slate-200';
                                    if (strpos($vote, 'veto') !== false || strpos($vote, 'hold') !== false || strpos($vote, 'reduce') !== false || strpos($vote, 'segment') !== false || strpos($vote, 'caution') !== false) {
                                        $voteTone = 'bg-amber-50 text-amber-700 ring-amber-200';
                                    }
                                    if (strpos($vote, 'scale') !== false || strpos($vote, 'continue') !== false) {
                                        $voteTone = 'bg-emerald-50 text-emerald-700 ring-emerald-200';
                                    }
                                    if (strpos($vote, 'rollback') !== false || strpos($vote, 'pause') !== false) {
                                        $voteTone = 'bg-rose-50 text-rose-700 ring-rose-200';
                                    }
                                @endphp
                                            <tr class="align-top">
                                                <td class="px-4 py-3">
                                                    <div class="text-xs text-slate-500 mb-1">Item {{ $loop->iteration }}</div>
                                                    <div class="font-semibold text-slate-950">{{ $trace['agent'] ?? '-' }}</div>
                                                </td>
                                                <td class="px-4 py-3 min-w-[280px]">
                                                    <div class="font-medium text-slate-900 line-clamp-2 agd-wrap-anywhere">{{ $displayShortValue($trace['dialogue_turn'] ?? ($trace['position'] ?? '-')) }}</div>
                                                    <span class="mt-2 inline-flex w-fit rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $voteTone }}">
                                                {{ strtoupper(str_replace('_', ' ', $displayShortValue($trace['vote'] ?? 'no vote'))) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 min-w-[220px] agd-wrap-anywhere">{{ $displayValue($trace['impact_on_final_decision'] ?? '-') }}</td>
                                                <td class="px-4 py-3">
                                                    <button type="button" @click="openEvidence = openEvidence === {{ $loop->iteration }} ? null : {{ $loop->iteration }}" class="inline-flex rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                                        <span x-text="openEvidence === {{ $loop->iteration }} ? 'Hide' : 'View'"></span>
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr x-show="openEvidence === {{ $loop->iteration }}" x-cloak x-transition>
                                                <td colspan="4" class="bg-slate-50 px-4 py-4">
                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm text-slate-700">
                                                        <div class="bg-white border border-slate-200 rounded-xl p-3 min-w-0 agd-wrap-anywhere"><strong>Evidence</strong><br>{{ $displayValue($trace['evidence'] ?? '-') }}</div>
                                                        <div class="bg-white border border-slate-200 rounded-xl p-3 min-w-0 agd-wrap-anywhere"><strong>Constraint / Counter-signal</strong><br>{{ $displayValue($trace['objection_or_veto'] ?? '-') }}</div>
                                                        <div class="bg-white border border-slate-200 rounded-xl p-3 min-w-0 agd-wrap-anywhere"><strong>Full Decision Weight</strong><br>{{ $displayValue($trace['impact_on_final_decision'] ?? '-') }}</div>
                                                    </div>
                                                </td>
                                            </tr>
                            @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    
                    <button type="button" @click="showLearning = !showLearning" class="w-full mt-6 mb-3 border-t border-slate-200 pt-5 flex items-start justify-between gap-4 text-left">
                        <div class="rounded-2xl border border-violet-200 bg-violet-50/60 px-4 py-3 w-full hover:bg-violet-50 transition">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-violet-700">Learning & Risk Adjustments</div>
                                    <div class="text-sm text-violet-900 mt-1">Deterministic guardrail, ads acquisition, forecast, evaluation, calibration memory, and the risk if the decision is wrong.</div>
                                    <div class="text-xs text-violet-700 mt-2">
                                        Preview: policy {{ strtoupper(str_replace('_', ' ', $displayShortValue($deterministicGuardrailBasis['winning_guardrail'] ?? ($guardrailPolicy['winning_guardrail'] ?? '-')))) }} · ads {{ strtoupper(str_replace('_', ' ', $displayShortValue($adsDecisionImpact['ads_verdict'] ?? ($aiAdsResult['ads_verdict'] ?? ($adsMetrics['ads_verdict']['decision'] ?? '-'))))) }} · trust {{ $forecastCalibrationTrustScore !== null ? $forecastCalibrationTrustScore . '/100' : '-' }}
                                    </div>
                                </div>
                                <div class="shrink-0 text-xs font-semibold text-violet-700" x-text="showLearning ? 'Hide' : 'Show'"></div>
                            </div>
                        </div>
                    </button>

                    <div x-show="showLearning" x-cloak x-transition>

                    @if (!empty($guardrailPolicy) || !empty($deterministicGuardrailBasis))
                        <div class="mt-5 border border-slate-300 rounded-2xl bg-slate-50 p-4">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-600 mb-1">Deterministic Decision Basis</div>
                                    <h4 class="text-lg font-bold text-slate-950">Guardrail Policy Engine</h4>
                                    <p class="text-sm text-slate-600 mt-1 leading-relaxed">
                                        Deterministic layer that determines active guardrails, blocked actions, allowed actions, and the winning guardrail before the Final Decision Agent writes the narrative.
                                    </p>
                                </div>
                                <span class="shrink-0 inline-flex w-fit rounded-full px-3 py-1 text-[11px] font-semibold bg-white text-slate-700 ring-1 ring-slate-200">
                                    {{ strtoupper(str_replace('_', ' ', $displayShortValue($deterministicGuardrailBasis['policy_type'] ?? ($guardrailPolicy['policy_type'] ?? 'guardrail_policy')))) }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm mb-4">
                                <div class="bg-white border border-slate-200 rounded-xl p-3 text-slate-950">
                                    <div class="text-xs font-semibold text-slate-500 mb-1">Policy Version</div>
                                    {{ $displayValue($deterministicGuardrailBasis['policy_version'] ?? ($guardrailPolicy['policy_version'] ?? '-')) }}
                                </div>
                                <div class="bg-white border border-slate-200 rounded-xl p-3 text-slate-950">
                                    <div class="text-xs font-semibold text-slate-500 mb-1">Winning Guardrail</div>
                                    {{ strtoupper(str_replace('_', ' ', $displayShortValue($deterministicGuardrailBasis['winning_guardrail'] ?? ($guardrailPolicy['winning_guardrail'] ?? '-')))) }}
                                </div>
                                <div class="bg-white border border-slate-200 rounded-xl p-3 text-slate-950">
                                    <div class="text-xs font-semibold text-slate-500 mb-1">Blocked Decision</div>
                                    {{ strtoupper(str_replace('_', ' ', $displayShortValue($deterministicGuardrailBasis['blocked_decision'] ?? ($guardrailDeterministicDecision['blocked_decision'] ?? '-')))) }}
                                </div>
                                <div class="bg-white border border-slate-200 rounded-xl p-3 text-slate-950">
                                    <div class="text-xs font-semibold text-slate-500 mb-1">Allowed Decision</div>
                                    {{ strtoupper(str_replace('_', ' ', $displayShortValue($deterministicGuardrailBasis['allowed_decision'] ?? ($guardrailDeterministicDecision['allowed_decision'] ?? '-')))) }}
                                </div>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 text-sm">
                                <div class="bg-white border border-slate-200 rounded-xl p-3 text-slate-800">
                                    <div class="text-xs font-semibold text-slate-500 mb-2">Blocked Actions</div>
                                    @php $blockedActions = $deterministicGuardrailBasis['blocked_actions'] ?? ($guardrailDeterministicDecision['blocked_actions'] ?? []); @endphp
                                    @if (!empty($blockedActions))
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($blockedActions as $action)
                                                <span class="inline-flex rounded-full bg-rose-50 text-rose-700 px-2.5 py-1 text-[11px] font-semibold ring-1 ring-rose-100">{{ strtoupper(str_replace('_', ' ', $action)) }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        -
                                    @endif
                                </div>

                                <div class="bg-white border border-slate-200 rounded-xl p-3 text-slate-800">
                                    <div class="text-xs font-semibold text-slate-500 mb-2">Allowed Actions</div>
                                    @php $allowedActions = $deterministicGuardrailBasis['allowed_actions'] ?? ($guardrailDeterministicDecision['allowed_actions'] ?? []); @endphp
                                    @if (!empty($allowedActions))
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($allowedActions as $action)
                                                <span class="inline-flex rounded-full bg-emerald-50 text-emerald-700 px-2.5 py-1 text-[11px] font-semibold ring-1 ring-emerald-100">{{ strtoupper(str_replace('_', ' ', $action)) }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        -
                                    @endif
                                </div>

                                <div class="bg-white border border-slate-200 rounded-xl p-3 text-slate-800">
                                    <div class="text-xs font-semibold text-slate-500 mb-2">Reason Codes</div>
                                    @php $reasonCodes = $deterministicGuardrailBasis['reason_codes'] ?? ($guardrailDeterministicDecision['reason_codes'] ?? []); @endphp
                                    @if (!empty($reasonCodes))
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($reasonCodes as $reasonCode)
                                                <span class="inline-flex rounded-full bg-slate-100 text-slate-700 px-2.5 py-1 text-[11px] font-semibold ring-1 ring-slate-200">{{ $displayShortValue($reasonCode) }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>

                            @if (!empty($guardrailTriggered))
                                <div class="mt-4 bg-white border border-slate-200 rounded-xl p-3">
                                    <div class="text-xs font-semibold text-slate-500 mb-2">Triggered Guardrails</div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-sm">
                                            <thead class="text-slate-500">
                                                <tr>
                                                    <th class="text-left px-3 py-2">Guardrail</th>
                                                    <th class="text-left px-3 py-2">Severity</th>
                                                    <th class="text-right px-3 py-2">Priority</th>
                                                    <th class="text-left px-3 py-2">Reason</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                @foreach ($guardrailTriggered as $guardrailName => $guardrailRow)
                                                    <tr>
                                                        <td class="px-3 py-2 font-medium text-slate-950">{{ strtoupper(str_replace('_', ' ', $guardrailName)) }}</td>
                                                        <td class="px-3 py-2">{{ strtoupper(str_replace('_', ' ', $guardrailRow['severity'] ?? '-')) }}</td>
                                                        <td class="px-3 py-2 text-right">{{ $guardrailRow['priority'] ?? '-' }}</td>
                                                        <td class="px-3 py-2 text-slate-600 whitespace-pre-wrap">{{ $displayValue($guardrailRow['reason_codes'] ?? []) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-3 text-xs text-slate-500">
                                {{ $displayValue($guardrailPolicy['reproducibility_note'] ?? 'Same input and policy version should produce the same deterministic guardrail decision.') }}
                            </div>
                        </div>
                    @endif

                    @if (!empty($adsDecisionImpact) || !empty($aiAdsResult) || !empty($adsMetrics))
                        <div class="mt-5 border border-sky-200 rounded-2xl bg-sky-50/70 p-4">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700 mb-1">Ads Acquisition Impact</div>
                                    <h4 class="text-lg font-bold text-sky-950">AI Ads Agent Decision Impact</h4>
                                    <p class="text-sm text-sky-900 mt-1 leading-relaxed">
                                        Impact of Google Ads performance, campaign lifecycle, and reset campaign on today budget decision.
                                    </p>
                                </div>
                                <span class="shrink-0 inline-flex w-fit rounded-full px-3 py-1 text-[11px] font-semibold bg-white text-sky-700 ring-1 ring-sky-200">
                                    {{ strtoupper(str_replace('_', ' ', $displayShortValue($adsDecisionImpact['ads_verdict'] ?? ($aiAdsResult['ads_verdict'] ?? ($adsMetrics['ads_verdict']['decision'] ?? 'ads'))))) }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div class="bg-white/80 border border-sky-200 rounded-xl p-3 text-sky-950">
                                    <div class="text-xs font-semibold text-sky-700 mb-1">Campaign Health</div>
                                    {{ strtoupper(str_replace('_', ' ', $displayShortValue($adsDecisionImpact['campaign_health'] ?? ($aiAdsResult['campaign_health'] ?? '-')))) }}
                                </div>
                                <div class="bg-white/80 border border-sky-200 rounded-xl p-3 text-sky-950">
                                    <div class="text-xs font-semibold text-sky-700 mb-1">Budget Decision</div>
                                    {{ strtoupper(str_replace('_', ' ', $displayShortValue($adsDecisionImpact['budget_decision'] ?? ($aiAdsResult['budget_decision']['decision'] ?? '-')))) }}
                                </div>
                                <div class="bg-white/80 border border-sky-200 rounded-xl p-3 text-sky-950">
                                    <div class="text-xs font-semibold text-sky-700 mb-1">Legacy Campaign</div>
                                    {{ $displayValue($adsDecisionImpact['legacy_campaign_interpretation'] ?? ($aiAdsResult['campaign_lifecycle_interpretation']['legacy_campaign_status'] ?? '-')) }}
                                </div>
                                <div class="bg-white/80 border border-sky-200 rounded-xl p-3 text-sky-950">
                                    <div class="text-xs font-semibold text-sky-700 mb-1">Reset Campaign</div>
                                    {{ $displayValue($adsDecisionImpact['reset_campaign_interpretation'] ?? ($aiAdsResult['campaign_lifecycle_interpretation']['reset_campaign_status'] ?? '-')) }}
                                </div>
                                <div class="md:col-span-2 bg-white/80 border border-sky-200 rounded-xl p-3 text-sky-950">
                                    <div class="text-xs font-semibold text-sky-700 mb-1">Ads Supply vs Product Quality</div>
                                    {{ $displayValue($adsDecisionImpact['ads_supply_vs_product_quality'] ?? ($aiAdsResult['ads_supply_vs_product_quality']['interpretation'] ?? '-')) }}
                                </div>
                                <div class="md:col-span-2 bg-white/80 border border-sky-200 rounded-xl p-3 text-sky-950">
                                    <div class="text-xs font-semibold text-sky-700 mb-1">Impact on Today Decision</div>
                                    {{ $displayValue($adsDecisionImpact['impact_on_today_decision'] ?? ($aiAdsResult['impact_on_final_decision'] ?? ($adsMetrics['ads_verdict']['final_decision_impact'] ?? '-'))) }}
                                </div>
                            </div>

                            @if (!empty($aiAdsResult['campaign_observations']))
                                <div class="mt-3 bg-white/80 border border-sky-200 rounded-xl p-3">
                                    <div class="text-xs font-semibold text-sky-700 mb-2">Campaign Observations</div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-sky-950">
                                        @foreach (($aiAdsResult['campaign_observations'] ?? []) as $observation)
                                            <div class="border border-sky-100 rounded-xl p-3 bg-white">
                                                <div class="font-semibold">{{ $displayShortValue($observation['campaign'] ?? '-') }}</div>
                                                <div class="text-xs mt-1">Lifecycle: {{ strtoupper(str_replace('_', ' ', $displayShortValue($observation['lifecycle_status'] ?? '-'))) }}</div>
                                                <div class="text-xs mt-1">Signal: {{ $displayValue($observation['performance_signal'] ?? '-') }}</div>
                                                <div class="text-xs mt-1">Risk: {{ $displayValue($observation['risk'] ?? '-') }}</div>
                                                <div class="text-xs mt-1">Recommendation: {{ $displayValue($observation['recommendation'] ?? '-') }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @elseif (!empty($adsMetrics['campaigns']))
                                <div class="mt-3 bg-white/80 border border-sky-200 rounded-xl p-3">
                                    <div class="text-xs font-semibold text-sky-700 mb-2">Campaign Metrics Snapshot</div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-sm">
                                            <thead class="text-sky-700">
                                                <tr>
                                                    <th class="text-left px-3 py-2">Campaign</th>
                                                    <th class="text-left px-3 py-2">Lifecycle</th>
                                                    <th class="text-right px-3 py-2">Cost</th>
                                                    <th class="text-right px-3 py-2">Conv.</th>
                                                    <th class="text-right px-3 py-2">CPI</th>
                                                    <th class="text-left px-3 py-2">Health</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-sky-100">
                                                @foreach (array_slice($adsMetrics['campaigns'] ?? [], 0, 8, true) as $campaignName => $campaignRow)
                                                    <tr>
                                                        <td class="px-3 py-2 font-medium text-sky-950">{{ $campaignName }}</td>
                                                        <td class="px-3 py-2">{{ strtoupper(str_replace('_', ' ', $campaignRow['lifecycle_context']['lifecycle_status'] ?? '-')) }}</td>
                                                        <td class="px-3 py-2 text-right">{{ $campaignRow['summary']['cost'] ?? '-' }}</td>
                                                        <td class="px-3 py-2 text-right">{{ $campaignRow['summary']['conversions'] ?? '-' }}</td>
                                                        <td class="px-3 py-2 text-right">{{ $campaignRow['summary']['cost_per_install'] ?? '-' }}</td>
                                                        <td class="px-3 py-2">{{ strtoupper(str_replace('_', ' ', $campaignRow['health']['status'] ?? '-')) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if (!empty($tomorrowForecastDecisionImpact))
                        <div class="mt-5 border border-blue-200 rounded-2xl bg-blue-50/70 p-4">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700 mb-1">Forecast Impact</div>
                                    <h4 class="text-lg font-bold text-blue-950">Tomorrow Forecast Decision Impact</h4>
                                    <p class="text-sm text-blue-900 mt-1 leading-relaxed">
                                        Impact of tomorrow quantitative prediction on today operating decision.
                                    </p>
                                </div>
                                <span class="shrink-0 inline-flex w-fit rounded-full px-3 py-1 text-[11px] font-semibold bg-white text-blue-700 ring-1 ring-blue-200">
                                    {{ strtoupper(str_replace('_', ' ', $tomorrowForecastDecisionImpact['scaling_caution'] ?? ($tomorrowForecastDecisionImpact['scaling_guardrail'] ?? 'forecast caution'))) }}
                                </span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div class="bg-white/80 border border-blue-200 rounded-xl p-3 text-blue-950">
                                    <div class="text-xs font-semibold text-blue-700 mb-1">Forecast Date</div>
                                    {{ $tomorrowForecastDecisionImpact['forecast_for_date'] ?? '-' }}
                                </div>
                                <div class="bg-white/80 border border-blue-200 rounded-xl p-3 text-blue-950">
                                    <div class="text-xs font-semibold text-blue-700 mb-1">Main Forecast Risk</div>
                                    {{ $tomorrowForecastDecisionImpact['main_forecast_risk'] ?? '-' }}
                                </div>
                                <div class="md:col-span-2 bg-white/80 border border-blue-200 rounded-xl p-3 text-blue-950">
                                    <div class="text-xs font-semibold text-blue-700 mb-1">Impact on Today Decision</div>
                                    {{ $tomorrowForecastDecisionImpact['impact_on_today_decision'] ?? '-' }}
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (!empty($forecastEvaluationDecisionImpact))
                        <div class="mt-5 border border-emerald-200 rounded-2xl bg-emerald-50/70 p-4">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700 mb-1">Evaluation Impact</div>
                                    <h4 class="text-lg font-bold text-emerald-950">Forecast Evaluation Decision Impact</h4>
                                    <p class="text-sm text-emerald-900 mt-1 leading-relaxed">
                                        Impact of previous forecast accuracy on trust, guardrails, and today operating decision.
                                    </p>
                                </div>
                                <span class="shrink-0 inline-flex w-fit rounded-full px-3 py-1 text-[11px] font-semibold bg-white text-emerald-700 ring-1 ring-emerald-200">
                                    {{ strtoupper(str_replace('_', ' ', $displayShortValue($forecastEvaluationDecisionImpact['latest_forecast_quality'] ?? 'evaluation'))) }}
                                </span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div class="bg-white/80 border border-emerald-200 rounded-xl p-3 text-emerald-950">
                                    <div class="text-xs font-semibold text-emerald-700 mb-1">Actual Data Until</div>
                                    {{ $displayValue($forecastEvaluationDecisionImpact['actual_data_available_until'] ?? '-') }}
                                </div>
                                <div class="bg-white/80 border border-emerald-200 rounded-xl p-3 text-emerald-950">
                                    <div class="text-xs font-semibold text-emerald-700 mb-1">Latest Hit Rate</div>
                                    {{ $displayShortValue($forecastEvaluationDecisionImpact['latest_hit_rate'] ?? '-') }}{{ isset($forecastEvaluationDecisionImpact['latest_hit_rate']) ? '%' : '' }}
                                </div>
                                <div class="bg-white/80 border border-emerald-200 rounded-xl p-3 text-emerald-950">
                                    <div class="text-xs font-semibold text-emerald-700 mb-1">Latest Forecast Date</div>
                                    {{ $displayValue($forecastEvaluationDecisionImpact['latest_forecast_for_date'] ?? '-') }}
                                </div>
                                <div class="bg-white/80 border border-amber-200 rounded-xl p-3 text-amber-950">
                                    <div class="text-xs font-semibold text-amber-700 mb-1">Pending Maturity</div>
                                    {{ $displayValue($forecastEvaluationDecisionImpact['metrics_pending_maturity'] ?? ($forecastEvaluationSummary['metrics_pending_maturity'] ?? 0)) }}
                                </div>
                                <div class="bg-white/80 border border-emerald-200 rounded-xl p-3 text-emerald-950">
                                    <div class="text-xs font-semibold text-emerald-700 mb-1">Evaluation Available</div>
                                    {{ $displayValue($forecastEvaluationDecisionImpact['evaluation_available'] ?? '-') }}
                                </div>
                                <div class="md:col-span-2 bg-white/80 border border-emerald-200 rounded-xl p-3 text-emerald-950">
                                    <div class="text-xs font-semibold text-emerald-700 mb-1">Impact on Today Decision</div>
                                    {{ $displayValue($forecastEvaluationDecisionImpact['impact_on_today_decision'] ?? '-') }}
                                </div>
                            </div>

                            @if (!empty($forecastEvaluationDecisionImpact['main_misses']))
                                <div class="mt-3 bg-white/80 border border-emerald-200 rounded-xl p-3">
                                    <div class="text-xs font-semibold text-emerald-700 mb-2">Main Misses</div>
                                    <ul class="list-disc ml-5 space-y-1 text-sm text-emerald-950">
                                        @foreach (($forecastEvaluationDecisionImpact['main_misses'] ?? []) as $miss)
                                            <li class="whitespace-pre-wrap">{{ $displayValue($miss) }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif


                    @if (!empty($forecastCalibrationDecisionImpact))
                        <div class="mt-5 border border-violet-200 rounded-2xl bg-violet-50/70 p-4">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 mb-1">Calibration Impact</div>
                                    <h4 class="text-lg font-bold text-violet-950">Forecast Calibration Decision Impact</h4>
                                    <p class="text-sm text-violet-900 mt-1 leading-relaxed">
                                        Impact of forecast accuracy history on today evidence weight. This is not the primary decision, only a weighting layer.
                                    </p>
                                </div>
                                <span class="shrink-0 inline-flex w-fit rounded-full px-3 py-1 text-[11px] font-semibold bg-white text-violet-700 ring-1 ring-violet-200">
                                    {{ strtoupper(str_replace('_', ' ', $displayShortValue($forecastCalibrationDecisionImpact['forecast_role'] ?? 'calibration'))) }}
                                </span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div class="bg-white/80 border border-violet-200 rounded-xl p-3 text-violet-950">
                                    <div class="text-xs font-semibold text-violet-700 mb-1">Trust Score</div>
                                    {{ $displayShortValue($forecastCalibrationDecisionImpact['trust_score'] ?? '-') }}{{ isset($forecastCalibrationDecisionImpact['trust_score']) ? '/100' : '' }}
                                </div>
                                <div class="bg-white/80 border border-violet-200 rounded-xl p-3 text-violet-950">
                                    <div class="text-xs font-semibold text-violet-700 mb-1">Mature Hit Rate</div>
                                    {{ $displayShortValue($forecastCalibrationDecisionImpact['overall_mature_hit_rate'] ?? '-') }}{{ isset($forecastCalibrationDecisionImpact['overall_mature_hit_rate']) ? '%' : '' }}
                                </div>
                                <div class="bg-white/80 border border-violet-200 rounded-xl p-3 text-violet-950">
                                    <div class="text-xs font-semibold text-violet-700 mb-1">Guardrail Adjustment</div>
                                    {{ strtoupper(str_replace('_', ' ', $displayShortValue($forecastCalibrationDecisionImpact['guardrail_adjustment'] ?? '-'))) }}
                                </div>
                                <div class="bg-white/80 border border-violet-200 rounded-xl p-3 text-violet-950">
                                    <div class="text-xs font-semibold text-violet-700 mb-1">Evaluations Used</div>
                                    {{ $displayValue($forecastCalibrationDecisionImpact['evaluations_used'] ?? '-') }}
                                </div>
                                <div class="md:col-span-2 bg-white/80 border border-violet-200 rounded-xl p-3 text-violet-950">
                                    <div class="text-xs font-semibold text-violet-700 mb-1">Impact on Today Decision</div>
                                    {{ $displayValue($forecastCalibrationDecisionImpact['impact_on_today_decision'] ?? '-') }}
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (!empty($decisionRiskAssessment))
                        <div class="mt-5 border border-rose-200 rounded-2xl bg-rose-50/70 p-4">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-rose-700 mb-1">Decision Risk</div>
                                    <h4 class="text-lg font-bold text-rose-950">Decision Risk Assessment</h4>
                                    <p class="text-sm text-rose-900 mt-1 leading-relaxed">
                                        Decision, reason, confidence, risk if wrong, and the condition that should reverse the decision.
                                    </p>
                                </div>
                                <span class="shrink-0 inline-flex w-fit rounded-full px-3 py-1 text-[11px] font-semibold bg-white text-rose-700 ring-1 ring-rose-200">
                                    {{ strtoupper(str_replace('_', ' ', $displayShortValue($decisionRiskAssessment['if_wrong']['risk_type'] ?? 'risk'))) }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div class="bg-white/80 border border-rose-200 rounded-xl p-3 text-rose-950">
                                    <div class="text-xs font-semibold text-rose-700 mb-1">Decision</div>
                                    {{ strtoupper(str_replace('_', ' ', $displayShortValue($decisionRiskAssessment['decision'] ?? '-'))) }}
                                </div>
                                <div class="bg-white/80 border border-rose-200 rounded-xl p-3 text-rose-950">
                                    <div class="text-xs font-semibold text-rose-700 mb-1">Confidence</div>
                                    {{ $displayShortValue($decisionRiskAssessment['confidence_score'] ?? '-') }}{{ isset($decisionRiskAssessment['confidence_score']) ? '/100' : '' }}
                                </div>
                                <div class="md:col-span-2 bg-white/80 border border-rose-200 rounded-xl p-3 text-rose-950">
                                    <div class="text-xs font-semibold text-rose-700 mb-1">Primary Reason</div>
                                    {{ $displayValue($decisionRiskAssessment['primary_reason'] ?? '-') }}
                                </div>
                                <div class="bg-white/80 border border-rose-200 rounded-xl p-3 text-rose-950">
                                    <div class="text-xs font-semibold text-rose-700 mb-1">Estimated 7D Impact if Wrong</div>
                                    {{ $displayValue($decisionRiskAssessment['if_wrong']['estimated_7d_impact'] ?? '-') }}
                                </div>
                                <div class="bg-white/80 border border-blue-200 rounded-xl p-3 text-blue-950">
                                    <div class="text-xs font-semibold text-blue-700 mb-1">Reverse Condition</div>
                                    {{ $displayValue($decisionRiskAssessment['reverse_condition']['condition'] ?? '-') }}
                                </div>
                            </div>
                        </div>
                    @endif
                    </div>
                    @php
                        $decisionScenarioAgent = $agents['decision_scenario_simulator'] ?? [];
                        $decisionScenarioResult = $decisionScenarioAgent['result'] ?? $decisionScenarioAgent;
                        $scenarioBaseline = $decisionScenarioResult['baseline_without_intervention'] ?? [];
                        $scenarioIntervention = $decisionScenarioResult['scenario_with_intervention'] ?? [];
                        $scenarioRecommendedAction = $decisionScenarioResult['recommended_intervention'] ?? [];
                        $scenarioComparison = $decisionScenarioResult['baseline_vs_intervention_comparison'] ?? [];
                    @endphp

                    @if (!empty($decisionScenarioResult))
                        <button type="button" @click="showScenario = !showScenario" class="w-full mt-6 mb-3 border-t border-slate-200 pt-5 flex items-start justify-between gap-4 text-left">
                            <div class="rounded-2xl border border-cyan-200 bg-cyan-50/60 px-4 py-3 w-full hover:bg-cyan-50 transition">
                                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-cyan-700">Decision Scenario Simulation</div>
                                        <div class="text-sm text-cyan-900 mt-1">Compares the baseline without major intervention against the scenario if the final recommendation is executed.</div>
                                        <div class="text-xs text-cyan-700 mt-2">
                                            Preview: {{ strtoupper(str_replace('_', ' ', $displayShortValue($scenarioRecommendedAction['action'] ?? ($decisionScenarioResult['simulation_type'] ?? 'baseline_vs_recommended_action')))) }} · confidence {{ strtoupper(str_replace('_', ' ', $displayShortValue($scenarioIntervention['confidence'] ?? '-'))) }}
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-xs font-semibold text-cyan-700" x-text="showScenario ? 'Hide' : 'Show'"></div>
                                </div>
                            </div>
                        </button>

                        <div x-show="showScenario" x-cloak x-transition>
                            <div class="mt-5 border border-cyan-200 rounded-2xl bg-cyan-50/70 p-4">
                                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-4">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-cyan-700 mb-1">Baseline vs Recommended Action</div>
                                        <h4 class="text-lg font-bold text-cyan-950">Decision Scenario Simulator</h4>
                                        <p class="text-sm text-cyan-900 mt-1 leading-relaxed">
                                            This is an evidence-based probabilistic simulation, not a new deterministic forecast and not a guaranteed uplift promise.
                                        </p>
                                    </div>
                                    <span class="shrink-0 inline-flex w-fit rounded-full px-3 py-1 text-[11px] font-semibold bg-white text-cyan-700 ring-1 ring-cyan-200">
                                        {{ strtoupper(str_replace('_', ' ', $displayShortValue($scenarioIntervention['confidence'] ?? ($decisionScenarioResult['simulation_scope'] ?? 'scenario')))) }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 text-sm mb-3">
                                    <div class="bg-white/80 border border-cyan-200 rounded-xl p-4 text-cyan-950">
                                        <div class="text-xs font-semibold text-cyan-700 mb-1">Baseline Without Intervention</div>
                                        <div class="font-semibold mb-2">Forecast For: {{ $displayShortValue($scenarioBaseline['forecast_for_date'] ?? '-') }}</div>
                                        <div>{{ $displayValue($scenarioBaseline['summary'] ?? '-') }}</div>
                                    </div>

                                    <div class="bg-white/80 border border-cyan-200 rounded-xl p-4 text-cyan-950">
                                        <div class="text-xs font-semibold text-cyan-700 mb-1">Recommended Intervention</div>
                                        <div class="font-semibold mb-2">{{ strtoupper(str_replace('_', ' ', $displayShortValue($scenarioRecommendedAction['action'] ?? '-'))) }}</div>
                                        <div>{{ $displayValue($scenarioRecommendedAction['why_this_action'] ?? '-') }}</div>
                                    </div>
                                </div>

                                <div class="bg-white/80 border border-cyan-200 rounded-xl p-4 text-cyan-950 mb-3">
                                    <div class="text-xs font-semibold text-cyan-700 mb-1">Scenario With Intervention</div>
                                    <div class="mb-3">{{ $displayValue($scenarioIntervention['summary'] ?? '-') }}</div>

                                    @if (!empty($scenarioIntervention['expected_direction']))
                                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2 text-xs">
                                            @foreach (($scenarioIntervention['expected_direction'] ?? []) as $metric => $direction)
                                                <div class="bg-cyan-50 border border-cyan-100 rounded-lg p-2">
                                                    <div class="font-semibold text-cyan-700">{{ strtoupper(str_replace('_', ' ', $metric)) }}</div>
                                                    <div class="text-cyan-950 mt-1">{{ strtoupper(str_replace('_', ' ', (string) $direction)) }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                                    <div class="bg-white/80 border border-emerald-200 rounded-xl p-3 text-emerald-950">
                                        <div class="text-xs font-semibold text-emerald-700 mb-1">Allowed Use</div>
                                        {{ $displayValue($decisionScenarioResult['allowed_use'] ?? '-') }}
                                    </div>
                                    <div class="bg-white/80 border border-rose-200 rounded-xl p-3 text-rose-950">
                                        <div class="text-xs font-semibold text-rose-700 mb-1">Not Allowed Use</div>
                                        {{ $displayValue($decisionScenarioResult['not_allowed_use'] ?? '-') }}
                                    </div>
                                    <div class="bg-white/80 border border-slate-200 rounded-xl p-3 text-slate-950">
                                        <div class="text-xs font-semibold text-slate-500 mb-1">Human Review Note</div>
                                        {{ $displayValue($decisionScenarioResult['human_review_note'] ?? '-') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                    @php
                        $conflictWinningGuardrail = strtolower((string) ($conflictRule['winning_guardrail'] ?? ($guardrailPolicy['winning_guardrail'] ?? '')));
                        $conflictResolutionType = strtolower((string) ($conflictRule['resolution_type'] ?? ''));
                        $isNormalOperatingResolution = $conflictResolutionType === 'normal_operating_resolution'
                            || $conflictWinningGuardrail === ''
                            || $conflictWinningGuardrail === 'none'
                            || $conflictWinningGuardrail === 'null';
                        $conflictRuleBadgeLabel = $isNormalOperatingResolution ? 'OPERATING RESOLUTION' : strtoupper(str_replace('_', ' ', $conflictRule['winning_guardrail'] ?? 'guardrail'));
                        $conflictRuleTitle = $isNormalOperatingResolution ? 'Final Operating Resolution' : 'Final Resolution Rule';
                        $conflictRuleTone = $isNormalOperatingResolution ? 'emerald' : 'amber';
                        $conflictPreviewLabel = $isNormalOperatingResolution ? 'operating resolution' : 'guardrail';
                    @endphp
                    <button type="button" @click="showResolution = !showResolution" class="w-full mt-6 mb-3 border-t border-slate-200 pt-5 flex items-start justify-between gap-4 text-left">
                        <div class="rounded-2xl border {{ $isNormalOperatingResolution ? 'border-emerald-200 bg-emerald-50/60 hover:bg-emerald-50' : 'border-amber-200 bg-amber-50/60 hover:bg-amber-50' }} px-4 py-3 w-full transition">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] {{ $isNormalOperatingResolution ? 'text-emerald-700' : 'text-amber-700' }}">Final Resolution</div>
                                    <div class="text-sm {{ $isNormalOperatingResolution ? 'text-emerald-900' : 'text-amber-900' }} mt-1">
                                        {{ $isNormalOperatingResolution
                                            ? 'Final operating resolution when no deterministic guardrail is triggered: normal decision, narrow constraints, and actions that remain allowed.'
                                            : 'The guardrail that ultimately wins, the blocked decision, and the operating decision that remains allowed.' }}
                                    </div>
                                    <div class="text-xs {{ $isNormalOperatingResolution ? 'text-emerald-700' : 'text-amber-700' }} mt-2">
                                        Preview: {{ strtoupper($conflictPreviewLabel) }} · blocked {{ strtoupper(str_replace('_', ' ', $displayShortValue($conflictRule['blocked_decision'] ?? ($guardrailDeterministicDecision['blocked_decision'] ?? '-')))) }} · allowed {{ strtoupper(str_replace('_', ' ', $displayShortValue($conflictRule['allowed_decision'] ?? ($guardrailDeterministicDecision['allowed_decision'] ?? '-')))) }}
                                    </div>
                                </div>
                                <div class="shrink-0 text-xs font-semibold {{ $isNormalOperatingResolution ? 'text-emerald-700' : 'text-amber-700' }}" x-text="showResolution ? 'Hide' : 'Show'"></div>
                            </div>
                        </div>
                    </button>

                    <div x-show="showResolution" x-cloak x-transition>

                    @if (!empty($conflictRule))
                        <div class="mt-5 border {{ $isNormalOperatingResolution ? 'border-emerald-200 bg-emerald-50/70' : 'border-amber-200 bg-amber-50/70' }} rounded-2xl p-4">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] {{ $isNormalOperatingResolution ? 'text-emerald-700' : 'text-amber-700' }} mb-1">{{ $conflictRuleTitle }}</div>
                                </div>
                                <span class="shrink-0 inline-flex w-fit rounded-full px-3 py-1 text-[11px] font-semibold bg-white {{ $isNormalOperatingResolution ? 'text-emerald-700 ring-emerald-200' : 'text-amber-700 ring-amber-200' }} ring-1">
                                    {{ $conflictRuleBadgeLabel }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div class="bg-white/80 border {{ $isNormalOperatingResolution ? 'border-emerald-200 text-emerald-950' : 'border-amber-200 text-amber-950' }} rounded-xl p-3">
                                    <div class="text-xs font-semibold {{ $isNormalOperatingResolution ? 'text-emerald-700' : 'text-amber-700' }} mb-1">Rule Triggered</div>
                                    {{ $displayValue($isNormalOperatingResolution ? ($conflictRule['rule_triggered'] ?? 'No deterministic guardrail was triggered.') : ($conflictRule['rule_triggered'] ?? '-')) }}
                                </div>
                                <div class="bg-white/80 border {{ $isNormalOperatingResolution ? 'border-emerald-200 text-emerald-950' : 'border-amber-200 text-amber-950' }} rounded-xl p-3">
                                    <div class="text-xs font-semibold {{ $isNormalOperatingResolution ? 'text-emerald-700' : 'text-amber-700' }} mb-1">Why It Wins</div>
                                    {{ $displayValue($isNormalOperatingResolution ? ($conflictRule['why_veto_won'] ?? 'No deterministic guardrail won because no deterministic guardrail was triggered.') : ($conflictRule['why_veto_won'] ?? '-')) }}
                                </div>
                                <div class="bg-white/80 border {{ $isNormalOperatingResolution ? 'border-emerald-200 text-emerald-950' : 'border-amber-200 text-amber-950' }} rounded-xl p-3">
                                    <div class="text-xs font-semibold text-rose-700 mb-1">Blocked Decision</div>
                                    {{ $displayValue($conflictRule['blocked_decision'] ?? '-') }}
                                </div>
                                <div class="bg-white/80 border {{ $isNormalOperatingResolution ? 'border-emerald-200 text-emerald-950' : 'border-amber-200 text-amber-950' }} rounded-xl p-3">
                                    <div class="text-xs font-semibold text-emerald-700 mb-1">Allowed Decision</div>
                                    {{ $displayValue($conflictRule['allowed_decision'] ?? '-') }}
                                </div>
                            </div>

                            @php
                                $objectiveThresholds = $conflictRule['objective_thresholds_used'] ?? [];
                                if ($isNormalOperatingResolution && empty($objectiveThresholds)) {
                                    $objectiveThresholds = ['No deterministic threshold was triggered.'];
                                }
                            @endphp
                            @if (!empty($objectiveThresholds))
                                <div class="mt-3 bg-white/80 border {{ $isNormalOperatingResolution ? 'border-emerald-200' : 'border-amber-200' }} rounded-xl p-3">
                                    <div class="text-xs font-semibold {{ $isNormalOperatingResolution ? 'text-emerald-700' : 'text-amber-700' }} mb-2">Objective Thresholds Used</div>
                                    <ul class="list-disc ml-5 space-y-1 text-sm {{ $isNormalOperatingResolution ? 'text-emerald-950' : 'text-amber-950' }}">
                                        @foreach ($objectiveThresholds as $threshold)
                                            <li>{{ $displayValue($threshold) }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                    </div>
                </div>


                <div
                    x-show="openInsight === 'operationalPlan'"
                    x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.98]"
                    class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0 mb-4"
                >
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div>
                            <h3 class="text-lg font-bold">Operational Action Plan</h3>
                            <p class="text-sm text-slate-500">Action plan that can be executed and evaluated objectively.</p>
                        </div>
                        <button type="button" @click="openInsight = null" class="text-xs text-slate-500 hover:text-slate-900">Close</button>
                    </div>

                    @if (!empty($operationalActionPlan))
                        <div class="space-y-4">
                            @foreach ($operationalActionPlan as $action)
                                <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50">
                                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 mb-4">
                                        <div>
                                            <div class="text-xs text-slate-500 mb-1">{{ $action['owner_area'] ?? 'owner_area' }}</div>
                                            <div class="text-lg font-bold text-slate-950">{{ $displayShortValue($action['action'] ?? '-') }}</div>
                                        </div>
                                        <span class="inline-flex w-fit rounded-full px-3 py-1 text-[11px] font-semibold ring-1 bg-blue-50 text-blue-700 ring-blue-200">
                                            EXPERIMENT READY
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 text-sm text-slate-700">
                                        <div class="bg-white border border-slate-200 rounded-xl p-3 whitespace-pre-wrap"><strong>Target Segment</strong><br>{{ $displayValue($action['target_user_segment'] ?? '-') }}</div>
                                        <div class="bg-white border border-slate-200 rounded-xl p-3 whitespace-pre-wrap"><strong>Trigger Condition</strong><br>{{ $displayValue($action['trigger_condition'] ?? '-') }}</div>
                                        <div class="bg-white border border-slate-200 rounded-xl p-3 whitespace-pre-wrap"><strong>Success Metric</strong><br>{{ $displayValue($action['success_metric'] ?? '-') }}</div>
                                        <div class="bg-white border border-slate-200 rounded-xl p-3 whitespace-pre-wrap"><strong>Stop-loss Metric</strong><br>{{ $displayValue($action['stop_loss_metric'] ?? '-') }}</div>
                                        <div class="bg-white border border-slate-200 rounded-xl p-3 whitespace-pre-wrap"><strong>Expected Lift</strong><br>{{ $displayValue($action['expected_lift'] ?? '-') }}</div>
                                        <div class="bg-white border border-slate-200 rounded-xl p-3 whitespace-pre-wrap"><strong>Duration</strong><br>{{ $displayValue($action['experiment_duration'] ?? '-') }}</div>
                                        <div class="bg-white border border-slate-200 rounded-xl p-3 whitespace-pre-wrap"><strong>Minimum Sample</strong><br>{{ $displayValue($action['minimum_sample_size'] ?? '-') }}</div>
                                        <div class="bg-white border border-slate-200 rounded-xl p-3 md:col-span-2 whitespace-pre-wrap"><strong>Rollback Condition</strong><br>{{ $displayValue($action['rollback_condition'] ?? '-') }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Operational action plan is not available yet. Clear cache and regenerate the analysis after the FinalDecisionAgent patch.</p>
                    @endif
                </div>

                <div
                    x-show="openInsight === 'previousDecision'"
                    x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.98]"
                    class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0 mb-4"
                >
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div>
                            <h3 class="text-lg font-bold">Previous Decision Evaluation</h3>
                            <p class="text-sm text-slate-500">Evaluation loop: did the previous checkpoint decision prove correct based on the current outcome?</p>
                        </div>
                        <button type="button" @click="openInsight = null" class="text-xs text-slate-500 hover:text-slate-900">Close</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-slate-700">
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4"><strong>Available</strong><br>{{ $previousDecisionEvaluation['available'] ?? 'false' }}</div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4"><strong>Decision Quality</strong><br>{{ $previousDecisionEvaluation['decision_quality'] ?? 'not_enough_data' }}</div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4"><strong>Previous Decision</strong><br>{{ $previousDecisionEvaluation['previous_decision'] ?? '-' }}</div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4"><strong>Expected Outcome</strong><br>{{ $previousDecisionEvaluation['expected_outcome'] ?? '-' }}</div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4"><strong>Actual Outcome</strong><br>{{ $previousDecisionEvaluation['actual_outcome'] ?? '-' }}</div>
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4"><strong>Lesson</strong><br>{{ $previousDecisionEvaluation['lesson'] ?? '-' }}</div>
                    </div>
                </div>

            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-8">
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0">
                <h2 class="text-lg font-bold mb-4">Activation Metrics 7D</h2>

                @php $m = $activation['metrics_7d'] ?? []; @endphp

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span>Session Users</span><strong>{{ $m['session_users'] ?? '-' }}</strong></div>
                    <div class="flex justify-between"><span>Workspace Users</span><strong>{{ $m['workspace_users'] ?? '-' }}</strong></div>
                    <div class="flex justify-between"><span>Food Add Success Users</span><strong>{{ $m['food_add_success_users'] ?? '-' }}</strong></div>
                    <div class="flex justify-between"><span>Food Success / Session</span><strong>{{ $m['food_add_success_rate_from_session'] ?? '-' }}%</strong></div>
                    <div class="flex justify-between"><span>Food Success / Workspace</span><strong>{{ $m['food_add_success_rate_from_workspace'] ?? '-' }}%</strong></div>
                    <div class="flex justify-between"><span>Purchase / Paywall</span><strong>{{ $m['purchase_success_rate_from_paywall'] ?? '-' }}%</strong></div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0">
                <h2 class="text-lg font-bold mb-4">Retention Metrics 7D Avg</h2>

                @php $r = $retention['metrics_7d_avg'] ?? []; @endphp

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span>D0 Logged Rate</span><strong>{{ $r['d0_logged_rate'] ?? '-' }}%</strong></div>
                    <div class="flex justify-between"><span>D1 Logged Rate</span><strong>{{ $r['d1_logged_rate'] ?? '-' }}%</strong></div>
                    <div class="flex justify-between"><span>Habit 7D Rate</span><strong>{{ $r['habit_7d_rate'] ?? '-' }}%</strong></div>
                    <div class="flex justify-between"><span>Avg Log Days 7D</span><strong>{{ $r['avg_log_days_7d'] ?? '-' }}</strong></div>
                </div>
            </div>
        </div>

        <div class="mb-8" x-data="{ openAgent: null }">
            <div class="mb-4 flex flex-col md:flex-row md:items-end md:justify-between gap-2">
                <div>
                    <h2 class="text-xl font-bold">Specialist AI Agents</h2>
                    <p class="text-sm text-slate-500 mt-1">Summary of 6 specialist agents. Click a card to open its analysis detail.</p>
                </div>
                <div class="text-xs text-slate-500 bg-slate-100 border border-slate-200 rounded-full px-3 py-1 w-fit">
                    Evidence layer · Activation · Retention · Monetization · Version · Ads · Forecast
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 items-stretch">
                <button type="button" @click="openAgent = openAgent === 'activation' ? null : 'activation'" class="text-left bg-white rounded-2xl p-5 shadow-sm border border-slate-200 hover:border-slate-400 transition min-w-0 h-full min-h-[190px] flex flex-col overflow-hidden">
                    <div class="flex items-start justify-between gap-3 mb-3 min-w-0">
                        <div class="min-w-0 flex-1 pr-2">
                            <div class="text-sm text-slate-500 mb-1 truncate">AI Activation</div>
                            <div class="text-xl font-bold leading-tight truncate" title="{{ strtoupper($displayShortValue($aiActivationResult['status'] ?? ($aiActivationAgent['status'] ?? 'unknown'))) }}">{{ strtoupper($displayShortValue($aiActivationResult['status'] ?? ($aiActivationAgent['status'] ?? 'unknown'))) }}</div>
                        </div>
                        <div class="shrink-0 whitespace-nowrap text-xs px-3 py-1 rounded-full {{ ($aiActivationAgent['status'] ?? '') === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ strtoupper($aiActivationAgent['status'] ?? 'unknown') }}
                        </div>
                    </div>
                    <div class="mt-auto pt-2">
                        @if (isset($aiActivationResult['confidence_score']))
                            <div class="text-xs text-slate-500 mb-2">Confidence {{ $displayShortValue($aiActivationResult['confidence_score']) }}/100</div>
                        @endif
                        <p class="text-slate-600 text-sm line-clamp-2">{{ $displayShortValue($aiActivationResult['diagnosis'] ?? ($aiActivationAgent['diagnosis'] ?? '-')) }}</p>
                    </div>
                </button>

                <button type="button" @click="openAgent = openAgent === 'retention' ? null : 'retention'" class="text-left bg-white rounded-2xl p-5 shadow-sm border border-slate-200 hover:border-slate-400 transition min-w-0 h-full min-h-[190px] flex flex-col overflow-hidden">
                    <div class="flex items-start justify-between gap-3 mb-3 min-w-0">
                        <div class="min-w-0 flex-1 pr-2">
                            <div class="text-sm text-slate-500 mb-1 truncate">AI Retention</div>
                            <div class="text-xl font-bold leading-tight truncate" title="{{ strtoupper($displayShortValue($aiRetentionResult['status'] ?? ($aiRetentionAgent['status'] ?? 'unknown'))) }}">{{ strtoupper($displayShortValue($aiRetentionResult['status'] ?? ($aiRetentionAgent['status'] ?? 'unknown'))) }}</div>
                        </div>
                        <div class="shrink-0 whitespace-nowrap text-xs px-3 py-1 rounded-full {{ ($aiRetentionAgent['status'] ?? '') === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ strtoupper($aiRetentionAgent['status'] ?? 'unknown') }}
                        </div>
                    </div>
                    <div class="mt-auto pt-2">
                        @if (isset($aiRetentionResult['confidence_score']))
                            <div class="text-xs text-slate-500 mb-2">Confidence {{ $displayShortValue($aiRetentionResult['confidence_score']) }}/100</div>
                        @endif
                        <p class="text-slate-600 text-sm line-clamp-2">{{ $displayShortValue($aiRetentionResult['diagnosis'] ?? ($aiRetentionAgent['diagnosis'] ?? '-')) }}</p>
                    </div>
                </button>

                <button type="button" @click="openAgent = openAgent === 'monetization' ? null : 'monetization'" class="text-left bg-white rounded-2xl p-5 shadow-sm border border-slate-200 hover:border-slate-400 transition min-w-0 h-full min-h-[190px] flex flex-col overflow-hidden">
                    <div class="flex items-start justify-between gap-3 mb-3 min-w-0">
                        <div class="min-w-0 flex-1 pr-2">
                            <div class="text-sm text-slate-500 mb-1 truncate">AI Monetization</div>
                            <div class="text-xl font-bold leading-tight truncate" title="{{ strtoupper($displayShortValue($aiMonetizationResult['status'] ?? ($aiMonetizationAgent['status'] ?? 'unknown'))) }}">{{ strtoupper($displayShortValue($aiMonetizationResult['status'] ?? ($aiMonetizationAgent['status'] ?? 'unknown'))) }}</div>
                        </div>
                        <div class="shrink-0 whitespace-nowrap text-xs px-3 py-1 rounded-full {{ ($aiMonetizationAgent['status'] ?? '') === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ strtoupper($aiMonetizationAgent['status'] ?? 'unknown') }}
                        </div>
                    </div>
                    <div class="mt-auto pt-2">
                        @if (isset($aiMonetizationResult['confidence_score']))
                            <div class="text-xs text-slate-500 mb-2">Confidence {{ $displayShortValue($aiMonetizationResult['confidence_score']) }}/100</div>
                        @endif
                        <p class="text-slate-600 text-sm line-clamp-2">{{ $displayShortValue($aiMonetizationResult['diagnosis'] ?? ($aiMonetizationAgent['diagnosis'] ?? '-')) }}</p>
                    </div>
                </button>

                <button type="button" @click="openAgent = openAgent === 'version' ? null : 'version'" class="text-left bg-white rounded-2xl p-5 shadow-sm border border-slate-200 hover:border-slate-400 transition min-w-0 h-full min-h-[190px] flex flex-col overflow-hidden">
                    <div class="flex items-start justify-between gap-3 mb-3 min-w-0">
                        <div class="min-w-0 flex-1 pr-2">
                            <div class="text-sm text-slate-500 mb-1 truncate">AI Version</div>
                            <div class="text-xl font-bold leading-tight truncate" title="{{ strtoupper($displayShortValue($aiVersionResult['status'] ?? ($aiVersionAgent['status'] ?? 'unknown'))) }}">{{ strtoupper($displayShortValue($aiVersionResult['status'] ?? ($aiVersionAgent['status'] ?? 'unknown'))) }}</div>
                        </div>
                        <div class="shrink-0 whitespace-nowrap text-xs px-3 py-1 rounded-full {{ ($aiVersionAgent['status'] ?? '') === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ strtoupper($aiVersionAgent['status'] ?? 'unknown') }}
                        </div>
                    </div>
                    <div class="mt-auto pt-2">
                        @if (isset($aiVersionResult['confidence_score']))
                            <div class="text-xs text-slate-500 mb-2">Confidence {{ $displayShortValue($aiVersionResult['confidence_score']) }}/100</div>
                        @endif
                        <p class="text-slate-600 text-sm line-clamp-2">{{ $displayShortValue($aiVersionResult['diagnosis'] ?? ($aiVersionAgent['diagnosis'] ?? '-')) }}</p>
                    </div>
                </button>

                <button type="button" @click="openAgent = openAgent === 'ads' ? null : 'ads'" class="text-left bg-white rounded-2xl p-5 shadow-sm border border-slate-200 hover:border-sky-300 hover:bg-sky-50/30 transition min-w-0 h-full min-h-[190px] flex flex-col overflow-hidden">
                    <div class="flex items-start justify-between gap-3 mb-3 min-w-0">
                        <div class="min-w-0 flex-1 pr-2">
                            <div class="text-sm text-slate-500 mb-1 truncate">AI Ads</div>
                            <div class="text-xl font-bold leading-tight truncate" title="{{ strtoupper($displayShortValue($aiAdsResult['ads_verdict'] ?? ($aiAdsAgent['status'] ?? 'unknown'))) }}">{{ strtoupper(str_replace('_', ' ', $displayShortValue($aiAdsResult['ads_verdict'] ?? ($aiAdsAgent['status'] ?? 'unknown')))) }}</div>
                        </div>
                        <div class="shrink-0 whitespace-nowrap text-xs px-3 py-1 rounded-full {{ ($aiAdsAgent['status'] ?? '') === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ strtoupper($aiAdsAgent['status'] ?? 'unknown') }}
                        </div>
                    </div>
                    <div class="mt-auto pt-2">
                        @if (isset($aiAdsResult['confidence_score']))
                            <div class="text-xs text-slate-500 mb-2">Confidence {{ $displayShortValue($aiAdsResult['confidence_score']) }}/100</div>
                        @endif
                        <p class="text-slate-600 text-sm line-clamp-2">
                            {{ $displayValue($aiAdsResult['main_diagnosis'] ?? ($adsMetrics['ads_verdict']['reason'] ?? 'Ads acquisition evidence is not available yet.')) }}
                        </p>
                    </div>
                </button>

                <button type="button" @click="openAgent = openAgent === 'tomorrowForecast' ? null : 'tomorrowForecast'" class="text-left bg-white rounded-2xl p-5 shadow-sm border border-slate-200 hover:border-slate-400 transition min-w-0 h-full min-h-[190px] flex flex-col overflow-hidden">
                    <div class="flex items-start justify-between gap-3 mb-3 min-w-0">
                        <div class="min-w-0 flex-1 pr-2">
                            <div class="text-sm text-slate-500 mb-1 truncate">AI Forecast</div>
                            <div class="text-xl font-bold leading-tight truncate" title="{{ strtoupper($displayShortValue($aiTomorrowForecastResult['prediction_status'] ?? ($aiTomorrowForecastAgent['status'] ?? 'unknown'))) }}">{{ strtoupper($displayShortValue($aiTomorrowForecastResult['prediction_status'] ?? ($aiTomorrowForecastAgent['status'] ?? 'unknown'))) }}</div>
                        </div>
                        <div class="shrink-0 whitespace-nowrap text-xs px-3 py-1 rounded-full {{
                            ($aiTomorrowForecastAgent['status'] ?? '') === 'active'
                                ? 'bg-emerald-100 text-emerald-700'
                                : (($aiTomorrowForecastAgent['status'] ?? '') === 'fallback'
                                    ? 'bg-amber-100 text-amber-700'
                                    : (($aiTomorrowForecastAgent['status'] ?? '') === 'exception' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600'))
                        }}">
                            {{ ($aiTomorrowForecastAgent['status'] ?? '') === 'fallback' ? 'FALLBACK USED' : strtoupper($aiTomorrowForecastAgent['status'] ?? 'unknown') }}
                        </div>
                    </div>
                    <div class="mt-auto pt-2">
                        @if (isset($aiTomorrowForecastResult['confidence_score']))
                            <div class="text-xs text-slate-500 mb-2">Confidence {{ $displayShortValue($aiTomorrowForecastResult['confidence_score']) }}/100</div>
                        @endif
                        <p class="text-slate-600 text-sm line-clamp-2">
                            {{ $displayShortValue($aiTomorrowForecastResult['executive_summary'] ?? ($aiTomorrowForecastResult['main_predicted_risk'] ?? 'Tomorrow quantitative forecast is not available yet.')) }}
                        </p>
                    </div>
                </button>
            </div>
            <div
                x-show="openAgent === 'tomorrowForecast'"
                x-cloak
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.98]"
                class="mt-5 bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0"
            >
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <h3 class="text-lg font-bold">Tomorrow Forecast Agent Detail</h3>
                        <p class="text-sm text-slate-500">Quantitative forecast based on the latest available data. Evaluation runs only when actual data for the forecast date is available.</p>
                    </div>
                    <button type="button" @click="openAgent = null" class="text-xs text-slate-500 hover:text-slate-900">Close</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                        <div class="text-xs text-slate-500 mb-1">Data As Of</div>
                        <div class="text-lg font-bold">{{ $forecastMeta['data_as_of_date'] ?? '-' }}</div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                        <div class="text-xs text-slate-500 mb-1">Forecast For</div>
                        <div class="text-lg font-bold">{{ $forecastMeta['forecast_for_date'] ?? '-' }}</div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                        <div class="text-xs text-slate-500 mb-1">Prediction Status</div>
                        <div class="text-lg font-bold">{{ strtoupper($displayShortValue($aiTomorrowForecastResult['prediction_status'] ?? '-')) }}</div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                        <div class="text-xs text-slate-500 mb-1">Scaling Caution</div>
                        <div class="text-lg font-bold">{{ strtoupper(str_replace('_', ' ', $tomorrowGuardrails['scaling_caution'] ?? ($tomorrowGuardrails['scaling_guardrail'] ?? '-'))) }}</div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                        <div class="text-xs text-slate-500 mb-1">Evaluation Ready After</div>
                        <div class="text-lg font-bold">{{ $forecastMeta['evaluation_ready_after'] ?? '-' }}</div>
                    </div>
                </div>

                @if (($aiTomorrowForecastAgent['status'] ?? null) === 'fallback')
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4 text-sm text-amber-900">
                        <div class="font-semibold mb-1">Fallback used</div>
                        <div>LLM narration timed out. Deterministic forecast metrics were used instead.</div>
                        <div class="mt-2">Attempted model: <strong>{{ $displayShortValue($aiTomorrowForecastAgent['model'] ?? '-') }}</strong></div>
                        <div>Timeout: <strong>{{ $tomorrowRequestMetrics['timeout_seconds'] ?? '-' }}</strong> seconds</div>
                        @if (!empty($aiTomorrowForecastAgent['llm_error']['message'] ?? null))
                            <div class="mt-2 text-xs whitespace-pre-wrap">Provider note: {{ $displayValue($aiTomorrowForecastAgent['llm_error']['message']) }}</div>
                        @endif
                    </div>
                @endif

                @if (!empty($forecastMeta['evaluation_status']) || !empty($forecastMeta['evaluation_rule']))
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4 text-sm text-amber-900">
                        <div class="font-semibold mb-1">Evaluation Readiness</div>
                        <div>Status: <strong>{{ strtoupper(str_replace('_', ' ', $displayShortValue($forecastMeta['evaluation_status'] ?? 'pending'))) }}</strong></div>
                        @if (!empty($forecastMeta['evaluation_rule']))
                            <div class="mt-1 whitespace-pre-wrap">{{ $displayValue($forecastMeta['evaluation_rule']) }}</div>
                        @endif
                    </div>
                @endif

                @if (!empty($aiTomorrowForecastResult['executive_summary']))
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4 text-sm text-blue-900">
                        <div class="font-semibold mb-1">Executive Summary</div>
                        <div class="whitespace-pre-wrap">{{ $displayValue($aiTomorrowForecastResult['executive_summary']) }}</div>
                    </div>
                @endif

                @if (!empty($aiTomorrowForecastResult['risk_flags_used'] ?? []))
                    <div class="bg-white border border-amber-200 rounded-xl p-4 mb-4">
                        <div class="font-semibold text-amber-800 mb-2">Risk Flags Used</div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                            @foreach (($aiTomorrowForecastResult['risk_flags_used'] ?? []) as $flag => $value)
                                <div class="bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
                                    <div class="text-xs text-amber-700 mb-1">{{ strtoupper(str_replace('_', ' ', $flag)) }}</div>
                                    <div class="font-semibold text-amber-950">{{ $displayValue($value) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                    <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50">
                        <div class="font-semibold text-slate-900 mb-3">Activation Forecast</div>
                        @php $activationForecast = $tomorrowPredictedMetrics['activation'] ?? []; @endphp
                        <div class="space-y-2 text-sm">
                            @foreach (['session_users' => 'Session Users', 'workspace_users' => 'Workspace Users', 'food_add_success_users' => 'Food Add Success', 'food_add_success_rate_from_session' => 'Food Success / Session', 'food_add_success_rate_from_workspace' => 'Food Success / Workspace'] as $key => $label)
                                @php $forecast = $activationForecast[$key] ?? []; @endphp
                                <div class="flex items-center justify-between gap-3 bg-white border border-slate-200 rounded-xl px-3 py-2">
                                    <span class="text-slate-600">{{ $label }}</span>
                                    <strong class="text-slate-950 whitespace-nowrap">
                                        {{ $forecast['low'] ?? '-' }} – {{ $forecast['high'] ?? '-' }}
                                        <span class="text-xs text-slate-500">({{ $forecast['point'] ?? '-' }})</span>
                                    </strong>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50">
                        <div class="font-semibold text-slate-900 mb-3">Retention & Monetization Forecast</div>
                        @php
                            $retentionForecast = $tomorrowPredictedMetrics['retention'] ?? [];
                            $monetizationForecast = $tomorrowPredictedMetrics['monetization'] ?? [];
                            $combinedForecast = [
                                'd0_logged_rate' => ['label' => 'D0 Logged Rate', 'data' => $retentionForecast['d0_logged_rate'] ?? []],
                                'd1_logged_rate' => ['label' => 'D1 Logged Rate', 'data' => $retentionForecast['d1_logged_rate'] ?? []],
                                'habit_7d_rate' => ['label' => 'Habit 7D Rate', 'data' => $retentionForecast['habit_7d_rate'] ?? []],
                                'paywall_view_users' => ['label' => 'Paywall View Users', 'data' => $monetizationForecast['paywall_view_users'] ?? []],
                                'purchase_success_users' => ['label' => 'Purchase Success Users', 'data' => $monetizationForecast['purchase_success_users'] ?? []],
                            ];
                        @endphp
                        <div class="space-y-2 text-sm">
                            @foreach ($combinedForecast as $forecastRow)
                                @php $forecast = $forecastRow['data'] ?? []; @endphp
                                <div class="flex items-center justify-between gap-3 bg-white border border-slate-200 rounded-xl px-3 py-2">
                                    <span class="text-slate-600">{{ $forecastRow['label'] }}</span>
                                    <strong class="text-slate-950 whitespace-nowrap">
                                        {{ $forecast['low'] ?? '-' }} – {{ $forecast['high'] ?? '-' }}
                                        <span class="text-xs text-slate-500">({{ $forecast['point'] ?? '-' }})</span>
                                    </strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if (!empty($aiTomorrowForecastResult['risk_drivers']))
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
                        <div class="font-semibold text-amber-800 mb-2">Risk Drivers</div>
                        <ul class="list-disc ml-5 space-y-1 text-sm text-amber-900">
                            @foreach ($renderList($aiTomorrowForecastResult['risk_drivers'] ?? []) as $risk)
                                <li class="whitespace-pre-wrap">{{ $displayValue($risk) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (!empty($aiTomorrowForecastResult['recommended_preventive_action']))
                    @php $preventiveAction = $aiTomorrowForecastResult['recommended_preventive_action']; @endphp
                    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm text-emerald-900">
                        <div class="font-semibold mb-1">Recommended Preventive Action: {{ $displayShortValue($preventiveAction['action'] ?? '-') }}</div>
                        <div>Target: {{ $displayValue($preventiveAction['target_user_segment'] ?? '-') }}</div>
                        <div>Trigger: {{ $displayValue($preventiveAction['trigger_condition'] ?? '-') }}</div>
                        <div class="text-xs mt-2">Success: {{ $displayValue($preventiveAction['success_metric'] ?? '-') }}</div>
                        <div class="text-xs">Rollback: {{ $displayValue($preventiveAction['rollback_condition'] ?? '-') }}</div>
                    </div>
                @endif
            </div>

            <div
                x-show="openAgent === 'activation'"
                x-cloak
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.98]"
                class="mt-5 bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0"
            >
                <h3 class="text-lg font-bold mb-2">AI Activation Agent Detail</h3>
                <p class="text-slate-700 mb-3 whitespace-pre-wrap">{{ $displayValue($aiActivationResult['diagnosis'] ?? '-') }}</p>
                @if (!empty($aiActivationResult['main_leak']))
                    <div class="text-sm text-slate-500 mb-3">Main leak: <strong class="text-slate-800">{{ $displayValue($aiActivationResult['main_leak']) }}</strong></div>
                @endif
                @if (!empty($aiActivationResult['metric_facts']))
                    <div class="font-semibold mb-1">Metric Facts</div>
                    <ul class="list-disc ml-5 text-sm text-slate-700 space-y-1 mb-4">
                        @foreach ($renderList($aiActivationResult['metric_facts'] ?? []) as $fact)
                            <li class="whitespace-pre-wrap">{{ $displayValue($fact) }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (!empty($aiActivationResult['recommended_experiment']))
                    @php $experiment = $aiActivationResult['recommended_experiment']; @endphp
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm">
                        <div class="font-semibold mb-1">Experiment: {{ $displayShortValue($experiment['name'] ?? '-') }}</div>
                        <div class="text-slate-700 whitespace-pre-wrap">{{ $displayValue($experiment['change'] ?? '-') }}</div>
                        <div class="text-xs text-slate-500 mt-2">Primary metric: {{ $displayValue($experiment['primary_metric'] ?? '-') }}</div>
                        <div class="text-xs text-slate-500">Success: {{ $displayValue($experiment['success_criteria'] ?? '-') }}</div>
                    </div>
                @endif
            </div>

            <div
                x-show="openAgent === 'retention'"
                x-cloak
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.98]"
                class="mt-5 bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0"
            >
                <h3 class="text-lg font-bold mb-2">AI Retention Agent Detail</h3>
                <p class="text-slate-700 mb-3 whitespace-pre-wrap">{{ $displayValue($aiRetentionResult['diagnosis'] ?? '-') }}</p>
                @if (!empty($aiRetentionResult['habit_risk']))
                    <div class="text-sm text-slate-500 mb-3">Habit risk: <strong class="text-slate-800">{{ $displayValue($aiRetentionResult['habit_risk']) }}</strong></div>
                @endif
                @if (!empty($aiRetentionResult['d0_to_d1_interpretation']))
                    <div class="font-semibold mb-1">D0 → D1 Interpretation</div>
                    <p class="text-sm text-slate-700 mb-4 whitespace-pre-wrap">{{ $displayValue($aiRetentionResult['d0_to_d1_interpretation']) }}</p>
                @endif
                @if (!empty($aiRetentionResult['metric_facts']))
                    <div class="font-semibold mb-1">Metric Facts</div>
                    <ul class="list-disc ml-5 text-sm text-slate-700 space-y-1 mb-4">
                        @foreach ($renderList($aiRetentionResult['metric_facts'] ?? []) as $fact)
                            <li class="whitespace-pre-wrap">{{ $displayValue($fact) }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (!empty($aiRetentionResult['recommended_experiment']))
                    @php $experiment = $aiRetentionResult['recommended_experiment']; @endphp
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm">
                        <div class="font-semibold mb-1">Experiment: {{ $displayShortValue($experiment['name'] ?? '-') }}</div>
                        <div class="text-slate-700">Target: {{ $displayValue($experiment['target_segment'] ?? '-') }}</div>
                        <div class="text-slate-700 whitespace-pre-wrap">{{ $displayValue($experiment['change'] ?? '') }}</div>
                        <div class="text-xs text-slate-500 mt-2">Primary metric: {{ $displayValue($experiment['primary_metric'] ?? '-') }}</div>
                        <div class="text-xs text-slate-500">Success: {{ $displayValue($experiment['success_criteria'] ?? '-') }}</div>
                    </div>
                @endif
            </div>

            <div
                x-show="openAgent === 'monetization'"
                x-cloak
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.98]"
                class="mt-5 bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0"
            >
                <h3 class="text-lg font-bold mb-2">AI Monetization Agent Detail</h3>
                <p class="text-slate-700 mb-3 whitespace-pre-wrap">{{ $displayValue($aiMonetizationResult['diagnosis'] ?? '-') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm text-slate-500 mb-4">
                    <div>Revenue: <strong class="text-slate-800">{{ $displayValue($aiMonetizationResult['revenue_signal'] ?? '-') }}</strong></div>
                    <div>Activation risk: <strong class="text-slate-800">{{ $displayValue($aiMonetizationResult['activation_risk'] ?? '-') }}</strong></div>
                </div>
                @if (!empty($aiMonetizationResult['opportunities']))
                    <div class="font-semibold mb-1">Opportunities</div>
                    <ul class="list-disc ml-5 text-sm text-slate-700 space-y-1 mb-4">
                        @foreach ($renderList($aiMonetizationResult['opportunities'] ?? []) as $item)
                            <li class="whitespace-pre-wrap">{{ $displayValue($item) }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (!empty($aiMonetizationResult['recommended_experiment']))
                    @php $experiment = $aiMonetizationResult['recommended_experiment']; @endphp
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm">
                        <div class="font-semibold mb-1">Experiment: {{ $displayShortValue($experiment['name'] ?? '-') }}</div>
                        <div class="text-slate-700 whitespace-pre-wrap">Trigger: {{ $displayValue($experiment['trigger_rule'] ?? '-') }}</div>
                        <div class="text-xs text-slate-500 mt-2">Primary metric: {{ $displayValue($experiment['primary_metric'] ?? '-') }}</div>
                        <div class="text-xs text-slate-500">Guardrail: {{ $displayValue($experiment['guardrail_metric'] ?? '-') }}</div>
                    </div>
                @endif
            </div>

            <div
                x-show="openAgent === 'version'"
                x-cloak
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.98]"
                class="mt-5 bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0"
            >
                <h3 class="text-lg font-bold mb-2">AI Version Agent Detail</h3>
                <p class="text-slate-700 mb-3 whitespace-pre-wrap">{{ $displayValue($aiVersionResult['diagnosis'] ?? '-') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-sm text-slate-500 mb-4">
                    <div>Best: <strong class="text-slate-800">{{ $displayValue($aiVersionResult['best_version'] ?? '-') }}</strong></div>
                    <div>Watch: <strong class="text-slate-800">{{ $displayValue($aiVersionResult['version_under_watch'] ?? '-') }}</strong></div>
                    <div>Decision: <strong class="text-slate-800">{{ $displayValue($aiVersionResult['rollout_decision'] ?? '-') }}</strong></div>
                </div>
                @if (!empty($aiVersionResult['trusted_versions']))
                    <div class="font-semibold mb-1">Trusted Versions</div>
                    <div class="flex flex-wrap gap-2 mb-4">
                        @foreach ($renderList($aiVersionResult['trusted_versions'] ?? []) as $version)
                            <span class="text-xs bg-emerald-50 text-emerald-700 px-2 py-1 rounded-full">{{ $version }}</span>
                        @endforeach
                    </div>
                @endif
                @if (!empty($aiVersionResult['release_risks']))
                    <div class="font-semibold mb-1">Release Risks</div>
                    <ul class="list-disc ml-5 text-sm text-slate-700 space-y-1">
                        @foreach ($renderList($aiVersionResult['release_risks'] ?? []) as $risk)
                            <li class="whitespace-pre-wrap">{{ $displayValue($risk) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div
                x-show="openAgent === 'ads'"
                x-cloak
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.98]"
                class="mt-5 bg-white rounded-2xl p-5 shadow-sm border border-sky-200 min-w-0"
            >
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <h3 class="text-lg font-bold">AI Ads Agent Detail</h3>
                        <p class="text-sm text-slate-500">Reads Google Ads performance, campaign lifecycle, and reset campaign context so the budget decision is not misinterpreted.</p>
                    </div>
                    <button type="button" @click="openAgent = null" class="text-xs text-slate-500 hover:text-slate-900">Close</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
                    <div class="bg-sky-50 border border-sky-200 rounded-xl p-4">
                        <div class="text-xs text-sky-700 mb-1">Ads Verdict</div>
                        <div class="text-lg font-bold text-sky-950">{{ strtoupper(str_replace('_', ' ', $displayShortValue($aiAdsResult['ads_verdict'] ?? ($adsMetrics['ads_verdict']['decision'] ?? '-')))) }}</div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                        <div class="text-xs text-slate-500 mb-1">Campaign Health</div>
                        <div class="text-lg font-bold">{{ strtoupper(str_replace('_', ' ', $displayShortValue($aiAdsResult['campaign_health'] ?? '-'))) }}</div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                        <div class="text-xs text-slate-500 mb-1">Budget Decision</div>
                        <div class="text-lg font-bold">{{ strtoupper(str_replace('_', ' ', $displayShortValue($aiAdsResult['budget_decision']['decision'] ?? '-'))) }}</div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                        <div class="text-xs text-slate-500 mb-1">Confidence</div>
                        <div class="text-lg font-bold">{{ $displayShortValue($aiAdsResult['confidence_score'] ?? '-') }}{{ isset($aiAdsResult['confidence_score']) ? '/100' : '' }}</div>
                    </div>
                </div>

                @if (!empty($aiAdsResult['main_diagnosis']))
                    <div class="bg-sky-50 border border-sky-200 rounded-xl p-4 mb-4 text-sm text-sky-950">
                        <div class="font-semibold mb-1">Main Diagnosis</div>
                        <div class="whitespace-pre-wrap">{{ $displayValue($aiAdsResult['main_diagnosis']) }}</div>
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                    <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50">
                        <div class="font-semibold text-slate-900 mb-3">Campaign Lifecycle Interpretation</div>
                        <div class="space-y-2 text-sm text-slate-700">
                            <div class="bg-white border border-slate-200 rounded-xl p-3 whitespace-pre-wrap"><strong>Legacy Campaign</strong><br>{{ $displayValue($aiAdsResult['campaign_lifecycle_interpretation']['legacy_campaign_status'] ?? '-') }}</div>
                            <div class="bg-white border border-slate-200 rounded-xl p-3 whitespace-pre-wrap"><strong>Reset Campaign</strong><br>{{ $displayValue($aiAdsResult['campaign_lifecycle_interpretation']['reset_campaign_status'] ?? '-') }}</div>
                            <div class="bg-white border border-slate-200 rounded-xl p-3 whitespace-pre-wrap"><strong>Operator Action Interpretation</strong><br>{{ $displayValue($aiAdsResult['campaign_lifecycle_interpretation']['operator_action_interpretation'] ?? '-') }}</div>
                        </div>
                    </div>

                    <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50">
                        <div class="font-semibold text-slate-900 mb-3">Guardrails</div>
                        <div class="space-y-2 text-sm text-slate-700">
                            <div class="bg-white border border-slate-200 rounded-xl p-3 whitespace-pre-wrap"><strong>Scale Guardrail</strong><br>{{ $displayValue($aiAdsResult['guardrails']['scale_guardrail'] ?? '-') }}</div>
                            <div class="bg-white border border-slate-200 rounded-xl p-3 whitespace-pre-wrap"><strong>Stop-loss Guardrail</strong><br>{{ $displayValue($aiAdsResult['guardrails']['stop_loss_guardrail'] ?? '-') }}</div>
                            <div class="bg-white border border-slate-200 rounded-xl p-3 whitespace-pre-wrap"><strong>Monitoring Metric</strong><br>{{ $displayValue($aiAdsResult['guardrails']['monitoring_metric'] ?? '-') }}</div>
                        </div>
                    </div>
                </div>

                @if (!empty($aiAdsResult['campaign_observations']))
                    <div class="bg-white border border-slate-200 rounded-2xl p-4 mb-4">
                        <div class="font-semibold text-slate-900 mb-3">Campaign Observations</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            @foreach (($aiAdsResult['campaign_observations'] ?? []) as $observation)
                                <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                                    <div class="font-semibold text-slate-950">{{ $displayShortValue($observation['campaign'] ?? '-') }}</div>
                                    <div class="text-xs text-slate-500 mt-1">Lifecycle: {{ strtoupper(str_replace('_', ' ', $displayShortValue($observation['lifecycle_status'] ?? '-'))) }}</div>
                                    <div class="mt-2 text-slate-700 whitespace-pre-wrap">{{ $displayValue($observation['performance_signal'] ?? '-') }}</div>
                                    <div class="text-xs text-rose-700 mt-2 whitespace-pre-wrap">Risk: {{ $displayValue($observation['risk'] ?? '-') }}</div>
                                    <div class="text-xs text-emerald-700 mt-1 whitespace-pre-wrap">Recommendation: {{ $displayValue($observation['recommendation'] ?? '-') }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (!empty($adsMetrics['campaigns']))
                    <div class="bg-white border border-slate-200 rounded-2xl p-4">
                        <div class="font-semibold text-slate-900 mb-3">Deterministic Campaign Metrics</div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-slate-500">
                                    <tr>
                                        <th class="text-left px-3 py-2">Campaign</th>
                                        <th class="text-left px-3 py-2">Lifecycle</th>
                                        <th class="text-right px-3 py-2">Cost</th>
                                        <th class="text-right px-3 py-2">Conv.</th>
                                        <th class="text-right px-3 py-2">CPI</th>
                                        <th class="text-left px-3 py-2">Health</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach (array_slice($adsMetrics['campaigns'] ?? [], 0, 8, true) as $campaignName => $campaignRow)
                                        <tr>
                                            <td class="px-3 py-2 font-medium text-slate-950">{{ $campaignName }}</td>
                                            <td class="px-3 py-2">{{ strtoupper(str_replace('_', ' ', $campaignRow['lifecycle_context']['lifecycle_status'] ?? '-')) }}</td>
                                            <td class="px-3 py-2 text-right">{{ $campaignRow['summary']['cost'] ?? '-' }}</td>
                                            <td class="px-3 py-2 text-right">{{ $campaignRow['summary']['conversions'] ?? '-' }}</td>
                                            <td class="px-3 py-2 text-right">{{ $campaignRow['summary']['cost_per_install'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ strtoupper(str_replace('_', ' ', $campaignRow['health']['status'] ?? '-')) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 mb-8 overflow-hidden" x-data="{ openAgentSociety: false }">
            @if (empty($structuredNegotiation))
                <button type="button" @click="openAgentSociety = !openAgentSociety" class="w-full text-left p-5 hover:bg-slate-50 transition">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 mb-1">Agent Society Layer</div>
                            <h2 class="text-xl font-bold">Adaptive Structured Negotiation</h2>
                            <p class="text-sm text-slate-500 mt-1">Structured negotiation was not run for this analysis.</p>
                        </div>
                        <div class="text-slate-400 text-sm transition-transform duration-300" :class="openAgentSociety ? 'rotate-180' : ''">⌄</div>
                    </div>
                </button>
                <div x-show="openAgentSociety" x-cloak x-transition class="px-5 pb-5">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        No Agent Society negotiation payload is available for this run.
                    </div>
                </div>
            @else
                <button type="button" @click="openAgentSociety = !openAgentSociety" class="w-full text-left p-5 hover:bg-slate-50 transition">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 mb-1">Agent Society Layer</div>
                            <h2 class="text-xl font-bold">Adaptive Structured Negotiation</h2>
                            <p class="text-sm text-slate-500 mt-1">Peer specialist summaries are examined in up to three evidence-bound rounds with early exit when material conflicts are resolved.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex w-fit rounded-full px-3 py-1 text-[11px] font-semibold bg-slate-100 text-slate-700 ring-1 ring-slate-200">
                                {{ $negotiationExecution['rounds_completed'] ?? ($structuredNegotiation['rounds_completed'] ?? ($structuredNegotiation['round'] ?? 0)) }} / {{ $negotiationRules['max_rounds'] ?? 3 }} ROUNDS
                            </span>
                            <div class="text-slate-400 text-sm transition-transform duration-300" :class="openAgentSociety ? 'rotate-180' : ''">⌄</div>
                        </div>
                    </div>
                </button>

                <div x-show="openAgentSociety" x-cloak x-transition class="p-5 border-t border-slate-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                            <div class="text-xs text-emerald-700 mb-1">Resolved Material Tensions</div>
                            <div class="text-lg font-bold text-emerald-950">{{ $negotiationUiSummary['resolved_material_tension_count'] ?? ($negotiationSummary['resolved_material_tension_count'] ?? 0) }} material tensions resolved in Round 1</div>
                        </div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Unresolved Hard Conflicts</div>
                            <div class="text-lg font-bold">{{ ($negotiationUiSummary['unresolved_hard_conflict_count'] ?? ($negotiationSummary['total_conflict_count'] ?? 0)) === 0 ? 'No unresolved hard conflicts' : ($negotiationUiSummary['unresolved_hard_conflict_count'] ?? ($negotiationSummary['total_conflict_count'] ?? 0)) }}</div>
                        </div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Round 2</div>
                            <div class="text-lg font-bold">{{ ($negotiationUiSummary['round_2_status'] ?? null) === 'skipped_by_policy' ? 'Round 2 skipped by policy' : (!empty($negotiationExecution['early_exit']) ? 'Round 2 skipped by policy' : 'Round 2 open') }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-5">
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Max Rounds</div>
                            <div class="text-lg font-bold">{{ $negotiationUiSummary['rounds_supported'] ?? ($negotiationRules['max_rounds'] ?? 3) }}</div>
                        </div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Rounds Completed</div>
                            <div class="text-lg font-bold">{{ $negotiationUiSummary['rounds_completed'] ?? ($negotiationExecution['rounds_completed'] ?? ($structuredNegotiation['rounds_completed'] ?? ($structuredNegotiation['round'] ?? 0))) }}</div>
                        </div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Early Exit</div>
                            <div class="text-lg font-bold">{{ !empty($negotiationExecution['early_exit']) ? 'YES' : 'NO' }}</div>
                        </div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Early Exit Reason</div>
                            <div class="text-sm font-bold break-words whitespace-pre-wrap">{{ $displayValue($negotiationExecution['early_exit_reason'] ?? '-') }}</div>
                        </div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Unresolved Hard Conflicts</div>
                            <div class="text-lg font-bold">{{ $negotiationUiSummary['unresolved_hard_conflict_count'] ?? ($negotiationExecution['material_or_higher_conflict_count'] ?? ($negotiationSummary['material_or_higher_conflict_count'] ?? 0)) }}</div>
                        </div>
                    </div>

                    @if (!empty($roundSummaries))
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
                            @foreach ($roundSummaries as $roundSummary)
                                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="text-xs text-slate-500 mb-1">{{ $roundSummary['label'] ?? ('Round ' . ($roundSummary['round'] ?? '-')) }}</div>
                                        <div class="font-bold">{{ $displayShortValue($roundSummary['purpose'] ?? '-') }}</div>
                                        </div>
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ ($roundSummary['status'] ?? '') === 'completed' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200' }}">
                                            {{ strtoupper($roundSummary['status'] ?? 'unknown') }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-2">{{ $roundSummary['turn_count'] ?? 0 }} turns · {{ $roundSummary['unresolved_material_conflict_count_after_round'] ?? ($roundSummary['material_or_higher_conflict_count_after_round'] ?? 0) }} unresolved material · {{ $roundSummary['resolved_material_tension_count_after_round'] ?? 0 }} resolved material tensions</div>
                                    @if (!empty($roundSummary['skip_reason']))
                                        <div class="text-xs text-slate-500 mt-1 whitespace-pre-wrap">{{ $displayValue($roundSummary['skip_reason']) }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($negotiationTimeline) || !empty($negotiationResponses))
                        @php $timelineRows = !empty($negotiationTimeline) ? $negotiationTimeline : $negotiationResponses; @endphp
                        <details class="mb-5 rounded-2xl border border-slate-200 bg-slate-50">
                            <summary class="cursor-pointer list-none p-4">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-slate-900">Cross-Agent Response Matrix</div>
                                    <div class="text-xs text-slate-500 mt-1">{{ count($timelineRows) }} evidence-bound peer turns across completed negotiation rounds.</div>
                                </div>
                                    <span class="inline-flex w-fit rounded-full px-3 py-1 text-[11px] font-semibold bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200">
                                        VIEW MATRIX
                                    </span>
                                </div>
                            </summary>
                            <div class="overflow-x-auto border-t border-slate-200 bg-white">
                                <div class="border border-transparent rounded-b-2xl">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-slate-500">
                                        <tr>
                                            <th class="text-left px-4 py-2">From</th>
                                            <th class="text-left px-4 py-2">To</th>
                                            <th class="text-left px-4 py-2">Type</th>
                                            <th class="text-left px-4 py-2">Severity</th>
                                            <th class="text-left px-4 py-2">Claim</th>
                                            <th class="text-left px-4 py-2">Evidence</th>
                                            <th class="text-left px-4 py-2">Revised Recommendation</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($timelineRows as $response)
                                            @php
                                                $severity = strtolower((string) ($response['severity'] ?? 'none'));
                                                $severityClass = in_array($severity, ['material', 'critical'], true)
                                                    ? 'bg-amber-50 text-amber-700 ring-amber-200'
                                                    : 'bg-slate-50 text-slate-600 ring-slate-200';
                                                $responseType = $response['ui_label'] ?? ($response['display_type'] ?? ($response['type'] ?? ($response['response_type'] ?? '-')));
                                            @endphp
                                            <tr>
                                                <td class="px-4 py-3 font-medium text-slate-950">{{ $displayShortValue($response['from'] ?? ($response['agent_name'] ?? '-')) }}</td>
                                                <td class="px-4 py-3">{{ $displayShortValue($response['to'] ?? ($response['target_agent'] ?? '-')) }}</td>
                                                <td class="px-4 py-3">{{ strtoupper(str_replace('_', ' ', $displayShortValue($responseType))) }}</td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $severityClass }}">
                                                        {{ strtoupper(str_replace('_', ' ', $displayShortValue($response['severity'] ?? 'none'))) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 min-w-[260px] whitespace-pre-wrap">{{ $displayValue($response['claim'] ?? '-') }}</td>
                                                <td class="px-4 py-3 min-w-[180px]">{{ $displayValue($response['evidence_refs'] ?? []) }}</td>
                                                <td class="px-4 py-3 min-w-[240px] whitespace-pre-wrap">{{ $displayValue($response['revised_recommendation'] ?? '-') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            </div>
                        </details>
                    @endif

                    @if (!empty($conflictMatrix))
                        <div class="mb-5">
                            <div class="font-semibold text-slate-900 mb-3">Tension & Resolution Matrix</div>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                @foreach ($conflictMatrix as $conflict)
                                    @php
                                        $isBoundedTension = (($conflict['type'] ?? null) === 'bounded_tension') || (($conflict['conflict_type'] ?? null) === 'bounded_tension');
                                    @endphp
                                    <div class="border {{ $isBoundedTension ? 'border-sky-200 bg-sky-50/60' : 'border-amber-200 bg-amber-50/60' }} rounded-2xl p-4">
                                        <div class="flex items-start justify-between gap-3 mb-3">
                                            <div>
                                                <div class="text-xs {{ $isBoundedTension ? 'text-sky-700' : 'text-amber-700' }} mb-1">{{ $conflict['conflict_id'] ?? '-' }}</div>
                                                <div class="font-bold {{ $isBoundedTension ? 'text-sky-950' : 'text-amber-950' }}">{{ $displayShortValue($conflict['title'] ?? ($conflict['topic'] ?? '-')) }}</div>
                                            </div>
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold bg-white {{ $isBoundedTension ? 'text-sky-700 ring-sky-200' : 'text-amber-700 ring-amber-200' }} ring-1">
                                                {{ $isBoundedTension ? (!empty($conflict['is_resolved_material_tension']) ? 'RESOLVED MATERIAL TENSION' : 'MINOR BOUNDED CAUTION') : strtoupper(str_replace('_', ' ', $conflict['severity'] ?? 'none')) }}
                                            </span>
                                        </div>
                                        <div class="text-xs {{ $isBoundedTension ? 'text-sky-800' : 'text-amber-800' }} mb-3">
                                            Agents: {{ $displayValue($conflict['supporting_agents'] ?? ($conflict['agents_involved'] ?? [])) }}
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                            <div class="bg-white/80 border {{ $isBoundedTension ? 'border-sky-200' : 'border-amber-200' }} rounded-xl p-3 whitespace-pre-wrap"><strong>{{ $isBoundedTension ? 'Domain-only Tension' : 'Initial Position' }}</strong><br>{{ $displayValue($conflict['domain_only_tension'] ?? ($conflict['initial_position'] ?? '-')) }}</div>
                                            <div class="bg-white/80 border {{ $isBoundedTension ? 'border-sky-200' : 'border-amber-200' }} rounded-xl p-3 whitespace-pre-wrap"><strong>{{ $isBoundedTension ? 'Bounded-system Resolution' : 'Counter Position' }}</strong><br>{{ $displayValue($conflict['bounded_system_resolution'] ?? ($conflict['counter_position'] ?? '-')) }}</div>
                                            <div class="bg-white/80 border {{ $isBoundedTension ? 'border-sky-200' : 'border-amber-200' }} rounded-xl p-3 md:col-span-2 whitespace-pre-wrap"><strong>Resolution Mode</strong><br>{{ $displayValue($conflict['resolution_mode'] ?? ($conflict['resolution_candidate'] ?? '-')) }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-5">
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Unresolved Hard Conflicts</div>
                            <div class="text-2xl font-bold">{{ $negotiationUiSummary['unresolved_hard_conflict_count'] ?? ($negotiationSummary['total_conflict_count'] ?? count($conflictMatrix)) }}</div>
                        </div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Resolved Material Tensions</div>
                            <div class="text-2xl font-bold">{{ $negotiationSummary['resolved_material_tension_count'] ?? 0 }}</div>
                        </div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Minor Bounded Cautions</div>
                            <div class="text-2xl font-bold">{{ $negotiationUiSummary['minor_bounded_caution_count'] ?? ($negotiationSummary['minor_bounded_tension_count'] ?? 0) }}</div>
                        </div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Safety-Bounded Partial Concessions</div>
                            <div class="text-2xl font-bold">{{ $negotiationSummary['safety_bounded_revision_count'] ?? 0 }}</div>
                        </div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="text-xs text-slate-500 mb-1">Round 2</div>
                            <div class="text-2xl font-bold">{{ ($negotiationUiSummary['round_2_status'] ?? null) === 'skipped_by_policy' ? 'SKIPPED BY POLICY' : (!empty($negotiationExecution['early_exit']) ? 'SKIPPED BY POLICY' : 'OPEN') }}</div>
                        </div>
                    </div>

                    @if (!empty($quantitativeBaselineComparison))
                        <div>
                            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-2 mb-4">
                                <div>
                                    <div class="font-semibold text-slate-900">Agent Society vs Single-Agent Baseline</div>
                                    <div class="text-sm text-slate-500">Data-derived comparison from this run, not a fixed benchmark.</div>
                                </div>
                                <div class="text-xs px-3 py-1 rounded-full bg-slate-100 text-slate-600 w-fit">
                                    {{ $quantitativeBaselineComparison['baseline_source_agent'] ?? 'Selected specialist' }}
                                </div>
                            </div>

                            @if (!empty($quantitativeBaselineComparison['headline']))
                                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-950">
                                    {{ $quantitativeBaselineComparison['headline'] }}
                                </div>
                            @endif

                            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-slate-600">
                                        <tr>
                                            <th class="text-left font-semibold px-4 py-3">Metric</th>
                                            <th class="text-left font-semibold px-4 py-3">Single Agent Baseline</th>
                                            <th class="text-left font-semibold px-4 py-3">Agent Society</th>
                                            <th class="text-left font-semibold px-4 py-3">Delta</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        @foreach ($quantitativeRows as $row)
                                            @php
                                                $label = $row[0];
                                                $key = $row[1];
                                                $baselineValue = $quantitativeSingleAgent[$key] ?? 'unknown';
                                                $societyValue = $quantitativeAgentSociety[$key] ?? 'unknown';
                                                $deltaValue = $key === 'unsafe_or_overbroad_action_risk'
                                                    ? ($quantitativeDelta['unsafe_or_overbroad_action_risk_reduction'] ?? 'unknown')
                                                    : ($quantitativeDelta[$key]['display'] ?? 'unknown');
                                            @endphp
                                            <tr class="bg-white">
                                                <td class="px-4 py-3 font-medium text-slate-800">{{ $label }}</td>
                                                <td class="px-4 py-3 text-slate-700 whitespace-pre-wrap">{{ $displayValue(is_bool($baselineValue) ? ($baselineValue ? 'yes' : 'no') : $baselineValue) }}</td>
                                                <td class="px-4 py-3 text-slate-900 font-semibold whitespace-pre-wrap">{{ $displayValue(is_bool($societyValue) ? ($societyValue ? 'yes' : 'no') : $societyValue) }}</td>
                                                <td class="px-4 py-3 text-emerald-700 font-semibold whitespace-pre-wrap">{{ $displayValue($deltaValue) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                                    Baseline is derived from the selected strongest single specialist output for this run. Agent Society uses guardrail-mediated specialist agents plus structured negotiation and final synthesis.
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-700">
                                    <div class="font-semibold text-slate-900 mb-2">Limitations</div>
                                    <ul class="list-disc pl-5 space-y-1">
                                        @foreach (($quantitativeBaselineComparison['limitations'] ?? ['This is a heuristic audit comparison, not causal proof.', 'A separate LLM rerun baseline can be added later.']) as $limitation)
                                            <li class="whitespace-pre-wrap">{{ $displayValue($limitation) }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @elseif (!empty($baselineComparison))
                        <div>
                            <div class="font-semibold text-slate-900 mb-3">Baseline Comparison</div>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50">
                                    <div class="font-bold text-slate-950 mb-2">Single Agent Baseline</div>
                                    <div class="space-y-2 text-sm">
                                        <div><span class="text-slate-500">Recommendation:</span> {{ $displayValue($singleAgentBaseline['recommendation'] ?? '-') }}</div>
                                        <div><span class="text-slate-500">Missed unresolved conflicts:</span> <strong>{{ $displayValue($singleAgentBaseline['missed_unresolved_conflicts'] ?? ($singleAgentBaseline['missed_conflicts'] ?? 0)) }}</strong></div>
                                        <div><span class="text-slate-500">Missed resolved material tensions:</span> <strong>{{ $displayValue($singleAgentBaseline['missed_resolved_material_tensions'] ?? 0) }}</strong></div>
                                        <div><span class="text-slate-500">Missed minor bounded cautions:</span> <strong>{{ $displayValue($singleAgentBaseline['missed_minor_bounded_tensions'] ?? 0) }}</strong></div>
                                        <div><span class="text-slate-500">Unsafe recommendation detected:</span> <strong>{{ !empty($singleAgentBaseline['unsafe_recommendation_detected']) ? 'yes' : 'no' }}</strong></div>
                                        <div><span class="text-slate-500">Evidence coverage:</span> <strong>{{ $displayValue($singleAgentBaseline['evidence_coverage_score'] ?? '-') }}</strong></div>
                                        <div><span class="text-slate-500">Caveat coverage:</span> <strong>{{ $displayValue($singleAgentBaseline['caveat_coverage_score'] ?? '-') }}</strong></div>
                                    </div>
                                </div>
                                <div class="border border-emerald-200 rounded-2xl p-4 bg-emerald-50/60">
                                    <div class="font-bold text-emerald-950 mb-2">Agent Society</div>
                                    <div class="space-y-2 text-sm text-emerald-950">
                                        <div><span class="text-emerald-700">Recommendation:</span> {{ $displayValue($agentSocietyBaseline['recommendation'] ?? '-') }}</div>
                                        <div><span class="text-emerald-700">Unresolved conflicts detected:</span> <strong>{{ $displayValue($agentSocietyBaseline['unresolved_conflicts_detected'] ?? ($agentSocietyBaseline['conflicts_detected'] ?? 0)) }}</strong></div>
                                        <div><span class="text-emerald-700">Resolved material tensions detected:</span> <strong>{{ $displayValue($agentSocietyBaseline['resolved_material_tensions_detected'] ?? 0) }}</strong></div>
                                        <div><span class="text-emerald-700">Minor bounded cautions detected:</span> <strong>{{ $displayValue($agentSocietyBaseline['minor_bounded_tensions_detected'] ?? 0) }}</strong></div>
                                        <div><span class="text-emerald-700">Unsafe recommendation prevented:</span> <strong>{{ !empty($agentSocietyBaseline['unsafe_recommendation_prevented']) ? 'yes' : 'no' }}</strong></div>
                                        <div><span class="text-emerald-700">Evidence coverage:</span> <strong>{{ $displayValue($agentSocietyBaseline['evidence_coverage_score'] ?? '-') }}</strong></div>
                                        <div><span class="text-emerald-700">Caveat coverage:</span> <strong>{{ $displayValue($agentSocietyBaseline['caveat_coverage_score'] ?? '-') }}</strong></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 mb-8" x-data="{ openFinalDecision: false }">
            <button type="button" @click="openFinalDecision = !openFinalDecision" class="w-full text-left p-5 hover:bg-slate-50 rounded-2xl transition">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <h2 class="text-lg font-bold">AI Final Decision Agent</h2>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-xs px-3 py-1 rounded-full {{ ($aiDecision['status'] ?? '') === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ strtoupper($aiDecision['status'] ?? 'unknown') }}
                        </div>
                        <div class="text-slate-400 text-sm transition-transform duration-300" :class="openFinalDecision ? 'rotate-180' : ''">⌄</div>
                    </div>
                </div>

                @if (!empty($aiResult))
                    @if (($aiDecision['status'] ?? null) === 'fallback' || !empty($aiResult['fallback_reason']))
                        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            <div class="font-semibold">Final Decision fallback used</div>
                            <div class="mt-1 whitespace-pre-wrap">{{ $displayValue($aiResult['fallback_reason'] ?? 'Deterministic fallback was used because the Final Decision LLM response was invalid or incomplete.') }}</div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <div class="text-sm text-slate-500 mb-1">Business Status</div>
                            <div class="text-xl font-bold mb-3">{{ $displayValue($aiResult['business_status'] ?? '-') }}</div>
                        </div>

                        @if (isset($aiResult['confidence_score']))
                            <div>
                                <div class="text-sm text-slate-500 mb-1">Final Confidence</div>
                                <div class="mb-1">
                                    <div class="w-full bg-slate-100 rounded-full h-2">
                                        <div class="bg-slate-800 h-2 rounded-full transition-all duration-500" style="width: {{ min(100, max(0, (int) $aiResult['confidence_score'])) }}%"></div>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1">{{ $displayShortValue($aiResult['confidence_score']) }}/100</div>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-slate-600">AI Decision Agent has not produced a diagnosis yet. Check the API key, model, or raw JSON below.</p>
                @endif
            </button>

            @if (!empty($aiResult))
                <div
                    x-show="openFinalDecision"
                    x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.98]"
                    class="px-5 pb-5"
                >
                    <div class="border-t border-slate-200 pt-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                @if (!empty($aiResult['agent_consensus']))
                                    <div class="text-sm text-slate-500 mb-1">Agent Consensus</div>
                                    <p class="text-slate-700 mb-3 whitespace-pre-wrap">{{ $displayValue($aiResult['agent_consensus']) }}</p>
                                @endif

                                <div class="text-sm text-slate-500 mb-1">Main Diagnosis</div>
                                <p class="text-slate-700 whitespace-pre-wrap">{{ $displayValue($aiResult['main_diagnosis'] ?? '-') }}</p>
                            </div>

                            <div>
                                <div class="text-sm text-slate-500 mb-2">Root Cause Hypothesis</div>
                                <ul class="list-disc ml-5 space-y-1 text-slate-700">
                                    @foreach (($aiResult['root_cause_hypothesis'] ?? []) as $hypothesis)
                                        <li class="whitespace-pre-wrap">{{ $displayValue($hypothesis) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        @if (!empty($aiResult['agent_conflicts']))
                            <div class="mt-5 bg-slate-50 border border-slate-200 rounded-xl p-4">
                                <div class="font-semibold text-slate-800 mb-2">Agent Conflicts</div>
                                <ul class="list-disc ml-5 space-y-1 text-slate-700 text-sm">
                                    @foreach (($aiResult['agent_conflicts'] ?? []) as $conflict)
                                        <li class="whitespace-pre-wrap">{{ $displayValue($conflict) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (!empty($aiResult['weak_evidence_or_uncertainty']))
                            <div class="mt-5 bg-orange-50 border border-orange-200 rounded-xl p-4">
                                <div class="font-semibold text-orange-800 mb-2">Weak Evidence / Uncertainty</div>
                                <ul class="list-disc ml-5 space-y-1 text-orange-900 text-sm">
                                    @foreach (($aiResult['weak_evidence_or_uncertainty'] ?? []) as $uncertainty)
                                        <li class="whitespace-pre-wrap">{{ $displayValue($uncertainty) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (!empty($aiResult['risk_notes']))
                            <div class="mt-5 bg-amber-50 border border-amber-200 rounded-xl p-4">
                                <div class="font-semibold text-amber-800 mb-2">Risk Notes</div>
                                <ul class="list-disc ml-5 space-y-1 text-amber-900 text-sm">
                                    @foreach (($aiResult['risk_notes'] ?? []) as $risk)
                                        <li class="whitespace-pre-wrap">{{ $displayValue($risk) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @php
                            $decisionScenarioAgent = $agents['decision_scenario_simulator'] ?? [];
                            $decisionScenarioResult = $decisionScenarioAgent['result'] ?? $decisionScenarioAgent;
                            $scenarioBaseline = $decisionScenarioResult['baseline_without_intervention'] ?? [];
                            $scenarioIntervention = $decisionScenarioResult['scenario_with_intervention'] ?? [];
                            $scenarioRecommendedAction = $decisionScenarioResult['recommended_intervention'] ?? [];
                            $scenarioComparison = $decisionScenarioResult['baseline_vs_intervention_comparison'] ?? [];
                        @endphp

                        @if (!empty($decisionScenarioResult))
                            <div class="mt-5 bg-cyan-50 border border-cyan-200 rounded-xl p-4">
                                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-4">
                                    <div>
                                        <div class="font-semibold text-cyan-900">Decision Scenario Simulation</div>
                                        <div class="text-sm text-cyan-900 mt-1">Baseline without major intervention compared with the scenario if the final recommendation is executed.</div>
                                    </div>
                                    <span class="shrink-0 inline-flex w-fit rounded-full px-3 py-1 text-[11px] font-semibold bg-white text-cyan-700 ring-1 ring-cyan-200">
                                        {{ strtoupper(str_replace('_', ' ', $displayShortValue($scenarioIntervention['confidence'] ?? ($decisionScenarioResult['simulation_scope'] ?? 'scenario')))) }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 text-sm mb-3">
                                    <div class="bg-white/80 border border-cyan-200 rounded-xl p-3 text-cyan-950">
                                        <div class="text-xs font-semibold text-cyan-700 mb-1">Baseline Without Intervention</div>
                                        <div class="text-xs text-cyan-700 mb-2">Forecast For: {{ $displayShortValue($scenarioBaseline['forecast_for_date'] ?? '-') }}</div>
                                        <div>{{ $displayValue($scenarioBaseline['summary'] ?? '-') }}</div>
                                    </div>
                                    <div class="bg-white/80 border border-cyan-200 rounded-xl p-3 text-cyan-950">
                                        <div class="text-xs font-semibold text-cyan-700 mb-1">Recommended Intervention</div>
                                        <div class="font-semibold mb-2">{{ strtoupper(str_replace('_', ' ', $displayShortValue($scenarioRecommendedAction['action'] ?? '-'))) }}</div>
                                        <div>{{ $displayValue($scenarioRecommendedAction['why_this_action'] ?? '-') }}</div>
                                    </div>
                                </div>

                                <div class="bg-white/80 border border-cyan-200 rounded-xl p-3 text-cyan-950 mb-3">
                                    <div class="text-xs font-semibold text-cyan-700 mb-1">Scenario With Intervention</div>
                                    <div class="mb-3">{{ $displayValue($scenarioIntervention['summary'] ?? '-') }}</div>

                                    @if (!empty($scenarioIntervention['expected_direction']))
                                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2 text-xs">
                                            @foreach (($scenarioIntervention['expected_direction'] ?? []) as $metric => $direction)
                                                <div class="bg-cyan-50 border border-cyan-100 rounded-lg p-2">
                                                    <div class="font-semibold text-cyan-700">{{ strtoupper(str_replace('_', ' ', $metric)) }}</div>
                                                    <div class="text-cyan-950 mt-1">{{ strtoupper(str_replace('_', ' ', (string) $direction)) }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                @if (!empty($scenarioComparison))
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm mb-3">
                                        <div class="bg-white/80 border border-cyan-200 rounded-xl p-3 text-cyan-950">
                                            <div class="text-xs font-semibold text-cyan-700 mb-1">Main Difference</div>
                                            {{ $displayValue($scenarioComparison['main_difference'] ?? '-') }}
                                        </div>
                                        <div class="bg-white/80 border border-cyan-200 rounded-xl p-3 text-cyan-950">
                                            <div class="text-xs font-semibold text-cyan-700 mb-1">Decision Implication</div>
                                            {{ $displayValue($scenarioComparison['decision_implication'] ?? '-') }}
                                        </div>
                                    </div>
                                @endif

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                                    <div class="bg-white/80 border border-emerald-200 rounded-xl p-3 text-emerald-950">
                                        <div class="text-xs font-semibold text-emerald-700 mb-1">Allowed Use</div>
                                        {{ $displayValue($decisionScenarioResult['allowed_use'] ?? '-') }}
                                    </div>
                                    <div class="bg-white/80 border border-rose-200 rounded-xl p-3 text-rose-950">
                                        <div class="text-xs font-semibold text-rose-700 mb-1">Not Allowed Use</div>
                                        {{ $displayValue($decisionScenarioResult['not_allowed_use'] ?? '-') }}
                                    </div>
                                    <div class="bg-white/80 border border-slate-200 rounded-xl p-3 text-slate-950">
                                        <div class="text-xs font-semibold text-slate-500 mb-1">Human Review Note</div>
                                        {{ $displayValue($decisionScenarioResult['human_review_note'] ?? '-') }}
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if (!empty($objectiveEvaluation))
                            <div class="mt-5 bg-blue-50 border border-blue-200 rounded-xl p-4">
                                <div class="font-semibold text-blue-800 mb-2">Objective Evaluation Plan</div>
                                <div class="text-sm text-blue-900 mb-2">
                                    Primary metric: <strong>{{ $objectiveEvaluation['primary_metric'] ?? '-' }}</strong>
                                </div>
                                @if (!empty($objectiveEvaluation['secondary_metrics']))
                                    <ul class="list-disc ml-5 space-y-1 text-blue-900 text-sm mb-2">
                                        @foreach (($objectiveEvaluation['secondary_metrics'] ?? []) as $metric)
                                            <li class="whitespace-pre-wrap">{{ $displayValue($metric) }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <div class="text-sm text-blue-900">
                                    Decision rule: {{ $objectiveEvaluation['decision_rule'] ?? '-' }}
                                </div>
                            </div>
                        @endif

                        @if (!empty($conflictRule))
                            @php
                                $simpleWinningGuardrail = strtolower((string) ($conflictRule['winning_guardrail'] ?? ''));
                                $simpleResolutionType = strtolower((string) ($conflictRule['resolution_type'] ?? ''));
                                $simpleIsOperatingResolution = $simpleResolutionType === 'normal_operating_resolution'
                                    || $simpleWinningGuardrail === ''
                                    || $simpleWinningGuardrail === 'none'
                                    || $simpleWinningGuardrail === 'null';
                            @endphp
                            <div class="mt-5 {{ $simpleIsOperatingResolution ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200' }} border rounded-xl p-4">
                                <div class="font-semibold {{ $simpleIsOperatingResolution ? 'text-emerald-800' : 'text-amber-800' }} mb-2">
                                    {{ $simpleIsOperatingResolution ? 'Final Operating Resolution' : 'Conflict Resolution Rule' }}
                                </div>
                                <div class="text-sm {{ $simpleIsOperatingResolution ? 'text-emerald-900' : 'text-amber-900' }} mb-1">
                                    {{ $simpleIsOperatingResolution ? 'Operating resolution' : 'Winning guardrail' }}:
                                    <strong>{{ $simpleIsOperatingResolution ? strtoupper(str_replace('_', ' ', $displayShortValue($conflictRule['resolution_type'] ?? 'normal_operating_resolution'))) : $displayShortValue($conflictRule['winning_guardrail'] ?? '-') }}</strong>
                                </div>
                                <div class="text-sm {{ $simpleIsOperatingResolution ? 'text-emerald-900' : 'text-amber-900' }}">
                                    {{ $displayValue($conflictRule['why_veto_won'] ?? ($conflictRule['rule_triggered'] ?? '-')) }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-8">
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0">
                <div class="mb-4">
                    <h2 class="text-lg font-bold">30-Day Activation Trend</h2>
                </div>

                @php $m = $activation['metrics_7d'] ?? []; @endphp
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="text-xs text-slate-500 mb-1">7D Session</div>
                        <div class="text-xl font-bold">{{ number_format((int) ($m['session_users'] ?? 0)) }}</div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="text-xs text-slate-500 mb-1">7D Workspace</div>
                        <div class="text-xl font-bold">{{ number_format((int) ($m['workspace_users'] ?? 0)) }}</div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="text-xs text-slate-500 mb-1">7D Food Success</div>
                        <div class="text-xl font-bold">{{ number_format((int) ($m['food_add_success_users'] ?? 0)) }}</div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="text-xs text-slate-500 mb-1">Success / Session</div>
                        <div class="text-xl font-bold">{{ $m['food_add_success_rate_from_session'] ?? '-' }}%</div>
                    </div>
                </div>

                <div class="h-[320px]">
                    <canvas id="activationTrendChart"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 min-w-0">
                <div class="mb-4">
                    <h2 class="text-lg font-bold">30-Day Retention Trend</h2>
                </div>

                @php $r = $retention['metrics_7d_avg'] ?? []; @endphp
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="text-xs text-slate-500 mb-1">D0 Avg</div>
                        <div class="text-xl font-bold">{{ $r['d0_logged_rate'] ?? '-' }}%</div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="text-xs text-slate-500 mb-1">D1 Avg</div>
                        <div class="text-xl font-bold">{{ $r['d1_logged_rate'] ?? '-' }}%</div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="text-xs text-slate-500 mb-1">Habit 7D Avg</div>
                        <div class="text-xl font-bold">{{ $r['habit_7d_rate'] ?? '-' }}%</div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="text-xs text-slate-500 mb-1">Avg Log Days</div>
                        <div class="text-xl font-bold">{{ $r['avg_log_days_7d'] ?? '-' }}</div>
                    </div>
                </div>

                <div class="h-[320px]">
                    <canvas id="retentionTrendChart"></canvas>
                </div>
            </div>
        </div>

        <div class="bg-slate-900 text-slate-100 rounded-2xl shadow-sm min-w-0" x-data="{ openRawJson: false }">
            <button type="button" @click="openRawJson = !openRawJson" class="w-full flex items-center justify-between gap-4 text-left p-5">
                <h2 class="text-lg font-bold">Raw Agent JSON</h2>
                <span class="text-slate-400 text-sm transition-transform duration-300" :class="openRawJson ? 'rotate-180' : ''">⌄</span>
            </button>
            <div x-show="openRawJson" x-cloak x-transition class="px-5 pb-5">
                <pre class="text-xs overflow-x-auto whitespace-pre-wrap break-words max-w-full">{{ json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    </div>

    <script>
        const chartData = @json($charts);

        const asyncStepSections = [
            {
                title: 'Data Preparation Sequence',
                subtitle: 'Load checkpoint and extract metrics run sequentially as the deterministic data source.',
                keys: ['checkpoint_load', 'metrics_extraction']
            },
            {
                title: 'Parallel Specialist Fan-Out',
                subtitle: 'Specialist agents run in parallel and finish according to each agent callback.',
                keys: [
                    'activation_agent',
                    'retention_agent',
                    'monetization_agent',
                    'version_agent',
                    'ads_agent',
                    'tomorrow_forecast_agent'
                ]
            },
            {
                title: 'Structured Negotiation',
                subtitle: 'One evidence-based cross-examination round produces responses and a conflict matrix.',
                keys: ['structured_negotiation']
            },
            {
                title: 'Final Decision Sequence',
                subtitle: 'Final Decision Agent waits for specialist and negotiation evidence, then Scenario Simulator compares baseline vs action before the run closes.',
                keys: ['final_decision_agent', 'decision_scenario_simulator', 'done']
            }
        ];

        const asyncStepOrder = asyncStepSections.flatMap(function (section) {
            return section.keys;
        });

        let asyncPollTimer = null;

        function asyncStatusClasses(status) {
            switch ((status || '').toLowerCase()) {
                case 'done':
                    return {
                        card: 'bg-emerald-50 border-emerald-200',
                        pill: 'bg-emerald-100 text-emerald-700',
                        dot: 'bg-emerald-500'
                    };
                case 'running':
                    return {
                        card: 'bg-blue-50 border-blue-200',
                        pill: 'bg-blue-100 text-blue-700',
                        dot: 'bg-blue-500 animate-pulse'
                    };
                case 'failed':
                    return {
                        card: 'bg-rose-50 border-rose-200',
                        pill: 'bg-rose-100 text-rose-700',
                        dot: 'bg-rose-500'
                    };
                default:
                    return {
                        card: 'bg-slate-50 border-slate-200',
                        pill: 'bg-slate-100 text-slate-600',
                        dot: 'bg-slate-300'
                    };
            }
        }

        // --- BEGIN: AGENT EXECUTION TIMING HELPERS ---
        function formatAgentDurationMs(durationMs) {
            if (durationMs === null || durationMs === undefined || durationMs === '') {
                return '-';
            }

            const numeric = Number(durationMs);
            if (!Number.isFinite(numeric)) {
                return '-';
            }

            return `${(numeric / 1000).toFixed(2)}s`;
        }

        function extractStepExecution(step) {
            const result = step?.result || step?.result_summary || step?.agent_result || step?.payload || {};
            const execution = result?.execution || result?.payload?.execution || step?.execution || {};

            return {
                mode: execution?.mode || null,
                requestStartedAt: execution?.request_started_at || null,
                requestFinishedAt: execution?.request_finished_at || null,
                durationMs: execution?.request_duration_ms ?? null,
                parallelPool: Boolean(execution?.parallel_pool),
            };
        }

        function renderStepExecutionTiming(step) {
            return '';
        }
        // --- END: AGENT EXECUTION TIMING HELPERS ---

        function showAsyncPanel() {
            const panel = document.getElementById('asyncRunPanel');
            if (!panel) return;
            panel.classList.remove('hidden');
        }

        function buildInitialAsyncRunState(message) {
            return {
                run_id: null,
                status: 'queued',
                progress_percent: 0,
                note: message || 'Starting a new multi-agent run...',
                steps: {
                    checkpoint_load: { label: 'Load Checkpoint', status: 'waiting', started_at: null, finished_at: null },
                    metrics_extraction: { label: 'Extract Metrics', status: 'waiting', started_at: null, finished_at: null },
                    activation_agent: { label: 'Activation Agent', status: 'waiting', started_at: null, finished_at: null },
                    retention_agent: { label: 'Retention Agent', status: 'waiting', started_at: null, finished_at: null },
                    monetization_agent: { label: 'Monetization Agent', status: 'waiting', started_at: null, finished_at: null },
                    version_agent: { label: 'Version Agent', status: 'waiting', started_at: null, finished_at: null },
                    ads_agent: { label: 'Ads Agent', status: 'waiting', started_at: null, finished_at: null },
                    tomorrow_forecast_agent: { label: 'Tomorrow Forecast Agent', status: 'waiting', started_at: null, finished_at: null },
                    final_decision_agent: { label: 'Final Decision Agent', status: 'waiting', started_at: null, finished_at: null },
                    decision_scenario_simulator: { label: 'Decision Scenario Simulator', status: 'waiting', started_at: null, finished_at: null },
                    done: { label: 'Done', status: 'waiting', started_at: null, finished_at: null }
                }
            };
        }

        function updateAsyncRunUI(run) {
            showAsyncPanel();

            const runId = document.getElementById('asyncRunId');
            const note = document.getElementById('asyncRunNote');
            const statusBadge = document.getElementById('asyncRunStatusBadge');
            const percent = document.getElementById('asyncProgressPercent');
            const bar = document.getElementById('asyncProgressBar');
            const stepsContainer = document.getElementById('asyncStepsContainer');
            const resultActions = document.getElementById('asyncRunResultActions');
            const errorBox = document.getElementById('asyncRunError');

            if (runId) runId.textContent = run.run_id ? 'Run ID: ' + run.run_id : '';
            if (note) note.textContent = run.note || 'Processing agents...';
            if (percent) percent.textContent = (run.progress_percent || 0) + '%';
            if (bar) bar.style.width = Math.max(0, Math.min(100, Number(run.progress_percent || 0))) + '%';

            if (statusBadge) {
                const status = (run.status || 'unknown').toUpperCase();
                statusBadge.textContent = status;
                statusBadge.className = 'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ' + (
                    run.status === 'done'
                        ? 'bg-emerald-100 text-emerald-700'
                        : run.status === 'failed'
                            ? 'bg-rose-100 text-rose-700'
                            : run.status === 'running'
                                ? 'bg-blue-100 text-blue-700'
                                : 'bg-slate-100 text-slate-700'
                );
            }

            if (stepsContainer) {
                const steps = run.steps || {};

                function renderAsyncStepCard(key) {
                    const step = steps[key] || { label: key, status: 'waiting' };
                    const classes = asyncStatusClasses(step.status);
                    const statusText = (step.status || 'waiting').toUpperCase();
                    const execution = extractStepExecution(step);
                    const realFinishedAt = execution.requestFinishedAt || step.finished_at || '';
                    const timeText = realFinishedAt
                        ? 'Finished: ' + realFinishedAt
                        : (step.started_at ? 'Started: ' + step.started_at : '');
                    const summary = step.result_summary ? '<div class="text-xs text-slate-500 mt-2">' + escapeHtml(step.result_summary) + '</div>' : '';
                    const error = step.error ? '<div class="text-xs text-rose-700 mt-2">' + escapeHtml(step.error) + '</div>' : '';
                    const executionTiming = renderStepExecutionTiming(step);

                    return '' +
                        '<div class="border rounded-xl p-3 ' + classes.card + '">' +
                            '<div class="flex items-start justify-between gap-3">' +
                                '<div class="min-w-0">' +
                                    '<div class="font-semibold text-slate-900 truncate">' + escapeHtml(step.label || key) + '</div>' +
                                    '<div class="text-xs text-slate-500 mt-1">' + escapeHtml(timeText) + '</div>' +
                                '</div>' +
                                '<span class="shrink-0 inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-[10px] font-semibold ' + classes.pill + '">' +
                                    '<span class="w-1.5 h-1.5 rounded-full ' + classes.dot + '"></span>' + statusText +
                                '</span>' +
                            '</div>' + summary + error + executionTiming +
                        '</div>';
                }

                stepsContainer.innerHTML = asyncStepSections.map(function (section) {
                    return '' +
                        '<div class="md:col-span-2 xl:col-span-3 rounded-2xl border border-slate-200 bg-white/70 p-4">' +
                            '<div class="mb-3 flex flex-col md:flex-row md:items-end md:justify-between gap-1">' +
                                '<div>' +
                                    '<div class="text-sm font-bold text-slate-900">' + escapeHtml(section.title) + '</div>' +
                                    '<div class="text-xs text-slate-500 mt-0.5">' + escapeHtml(section.subtitle) + '</div>' +
                                '</div>' +
                                '<div class="text-[10px] uppercase tracking-wide text-slate-400">' + section.keys.length + ' steps</div>' +
                            '</div>' +
                            '<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">' +
                                section.keys.map(renderAsyncStepCard).join('') +
                            '</div>' +
                        '</div>';
                }).join('');
            }

            if (resultActions) {
                resultActions.classList.toggle('hidden', run.status !== 'done');
            }

            if (errorBox) {
                if (run.status === 'failed' || run.error) {
                    errorBox.classList.remove('hidden');
                    errorBox.textContent = run.error || 'Run failed.';
                } else {
                    errorBox.classList.add('hidden');
                    errorBox.textContent = '';
                }
            }
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        async function startAsyncAgentRun() {
            const button = document.getElementById('startAsyncRunButton');
            if (button) {
                button.disabled = true;
                button.textContent = 'Starting Agents...';
                button.classList.add('opacity-70', 'cursor-not-allowed');
            }

            if (asyncPollTimer) {
                clearTimeout(asyncPollTimer);
                asyncPollTimer = null;
            }

            showAsyncPanel();
            updateAsyncRunUI(buildInitialAsyncRunState('Starting a new multi-agent run...'));

            const resultActions = document.getElementById('asyncRunResultActions');
            const errorBox = document.getElementById('asyncRunError');
            if (resultActions) {
                resultActions.classList.add('hidden');
            }
            if (errorBox) {
                errorBox.classList.add('hidden');
                errorBox.textContent = '';
            }

            try {
                const response = await fetch('/api/ai-growth-doctor/analyze-async/start', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                updateAsyncRunUI(buildInitialAsyncRunState('Run initialized. Waiting for first progress update...'));
                const runIdLabel = document.getElementById('asyncRunId');
                if (runIdLabel && data.run_id) {
                    runIdLabel.textContent = 'Run ID: ' + data.run_id;
                }

                if (!response.ok) {
                    throw new Error(data.error || data.message || 'Failed to start async run.');
                }

                await pollAsyncRun(data.run_id);
            } catch (error) {
                const errorBox = document.getElementById('asyncRunError');
                if (errorBox) {
                    errorBox.classList.remove('hidden');
                    errorBox.textContent = error.message || 'Failed to start async run.';
                }
            } finally {
                if (button) {
                    button.disabled = false;
                    button.textContent = 'Run Live Agent Progress';
                    button.classList.remove('opacity-70', 'cursor-not-allowed');
                }
            }
        }

        async function pollAsyncRun(runId) {
            if (!runId) return;

            if (asyncPollTimer) {
                clearTimeout(asyncPollTimer);
                asyncPollTimer = null;
            }

            const response = await fetch('/api/ai-growth-doctor/runs/' + encodeURIComponent(runId), {
                headers: {
                    'Accept': 'application/json'
                }
            });

            const run = await response.json();

            if (!response.ok) {
                throw new Error(run.error || 'Failed to poll run status.');
            }

            updateAsyncRunUI(run);

            if (run.status !== 'done' && run.status !== 'failed') {
                asyncPollTimer = setTimeout(function () {
                    pollAsyncRun(runId).catch(function (error) {
                        const errorBox = document.getElementById('asyncRunError');
                        if (errorBox) {
                            errorBox.classList.remove('hidden');
                            errorBox.textContent = error.message || 'Polling failed.';
                        }
                    });
                }, 1200);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const button = document.getElementById('startAsyncRunButton');
            if (button) {
                button.addEventListener('click', startAsyncAgentRun);
            }
        });

        function showPageLoadingOverlay() {
            const overlay = document.getElementById('pageLoadingOverlay');
            if (!overlay) return;
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
        }

        function hidePageLoadingOverlay() {
            const overlay = document.getElementById('pageLoadingOverlay');
            if (!overlay) return;
            overlay.classList.add('hidden');
            overlay.classList.remove('flex');
        }

        let skipNextPageLoadingOverlay = false;

        window.addEventListener('pageshow', function () {
            skipNextPageLoadingOverlay = false;
            hidePageLoadingOverlay();
        });

        window.addEventListener('beforeunload', function () {
            if (skipNextPageLoadingOverlay) {
                hidePageLoadingOverlay();
                return;
            }

            showPageLoadingOverlay();
        });

        document.addEventListener('submit', function (event) {
            if (event.target && event.target.matches('[data-show-loading="true"]')) {
                showPageLoadingOverlay();
            }
        });

        document.addEventListener('click', function (event) {
            const skipLoadingTarget = event.target.closest('[data-skip-page-loading="true"]');
            if (skipLoadingTarget) {
                skipNextPageLoadingOverlay = true;
                hidePageLoadingOverlay();
                setTimeout(function () {
                    skipNextPageLoadingOverlay = false;
                }, 1500);
                return;
            }

            const target = event.target.closest('[data-show-loading="true"]');
            if (target) {
                showPageLoadingOverlay();
            }
        });

        const activationTrend = chartData.activation_trend || {};
        const retentionTrend = chartData.retention_trend || {};

        function numericSeries(values) {
            return (values || []).map(function (value) {
                const parsed = Number(value);
                return Number.isFinite(parsed) ? parsed : null;
            });
        }

        function compactDateLabels(labels) {
            return (labels || []).map(function (label) {
                if (!label || typeof label !== 'string') return label;
                return label.length >= 10 ? label.substring(5) : label;
            });
        }

        const activationLabels = compactDateLabels(activationTrend.labels || []);
        const sessionUsers = numericSeries(activationTrend.session_users || []);
        const workspaceUsers = numericSeries(activationTrend.workspace_users || []);
        const foodSuccessUsers = numericSeries(activationTrend.food_add_success_users || []);
        const foodSuccessRateFromSession = numericSeries(activationTrend.food_add_success_rate_from_session || []);
        const foodSuccessRateFromWorkspace = numericSeries(activationTrend.food_add_success_rate_from_workspace || []);

        const activationCanvas = document.getElementById('activationTrendChart');
        if (activationCanvas) {
            new Chart(activationCanvas, {
                data: {
                    labels: activationLabels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Session Users',
                            data: sessionUsers,
                            yAxisID: 'countAxis',
                            borderWidth: 1,
                            borderRadius: 6,
                        },
                        {
                            type: 'bar',
                            label: 'Workspace Users',
                            data: workspaceUsers,
                            yAxisID: 'countAxis',
                            borderWidth: 1,
                            borderRadius: 6,
                        },
                        {
                            type: 'bar',
                            label: 'Food Add Success Users',
                            data: foodSuccessUsers,
                            yAxisID: 'countAxis',
                            borderWidth: 1,
                            borderRadius: 6,
                        },
                        {
                            type: 'line',
                            label: 'Success / Session %',
                            data: foodSuccessRateFromSession,
                            yAxisID: 'rateAxis',
                            tension: 0.25,
                            pointRadius: 3,
                            borderWidth: 2,
                        },
                        {
                            type: 'line',
                            label: 'Success / Workspace %',
                            data: foodSuccessRateFromWorkspace,
                            yAxisID: 'rateAxis',
                            tension: 0.25,
                            pointRadius: 3,
                            borderWidth: 2,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.parsed.y;
                                    if ((context.dataset.yAxisID || '').includes('rate')) {
                                        return label + ': ' + value + '%';
                                    }
                                    return label + ': ' + new Intl.NumberFormat().format(value);
                                }
                            }
                        }
                    },
                    scales: {
                        countAxis: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Users / day'
                            },
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat().format(value);
                                }
                            }
                        },
                        rateAxis: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            suggestedMax: 100,
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Conversion %'
                            },
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 10
                            }
                        }
                    }
                }
            });
        }

        const retentionLabels = compactDateLabels(retentionTrend.labels || []);
        const d0Rate = numericSeries(retentionTrend.d0_logged_rate || []);
        const d1Rate = numericSeries(retentionTrend.d1_logged_rate || []);
        const habitRate = numericSeries(retentionTrend.habit_7d_rate || []);

        const retentionCanvas = document.getElementById('retentionTrendChart');
        if (retentionCanvas) {
            new Chart(retentionCanvas, {
                type: 'line',
                data: {
                    labels: retentionLabels,
                    datasets: [
                        {
                            label: 'D0 Logged Rate',
                            data: d0Rate,
                            tension: 0.25,
                            pointRadius: 3,
                            borderWidth: 2,
                        },
                        {
                            label: 'D1 Logged Rate',
                            data: d1Rate,
                            tension: 0.25,
                            pointRadius: 3,
                            borderWidth: 2,
                        },
                        {
                            label: 'Habit 7D Rate',
                            data: habitRate,
                            tension: 0.25,
                            pointRadius: 3,
                            borderWidth: 2,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return (context.dataset.label || '') + ': ' + context.parsed.y + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMax: 50,
                            title: {
                                display: true,
                                text: 'Cohort rate %'
                            },
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 10
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>

</html>
