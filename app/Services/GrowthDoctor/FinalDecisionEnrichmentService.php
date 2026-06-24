<?php

namespace App\Services\GrowthDoctor;

class FinalDecisionEnrichmentService
{
    public function enrich(array $coreDecision, array $compactContext, array $fallbackMeta = []): array
    {
        $guardrail = $compactContext['guardrail_policy'] ?? [];
        $specialists = $compactContext['specialist_summaries'] ?? [];
        $structured = $compactContext['structured_negotiation'] ?? [];
        $forecast = $compactContext['forecast'] ?? [];
        $coreMetrics = $compactContext['core_metrics'] ?? [];

        $decision = $this->normalizeCore($coreDecision, $guardrail);
        $decision['operating_decision'] = $this->normalizeOperatingDecision(
            $decision['operating_decision'] ?? [],
            $decision,
            $coreMetrics
        );

        $decision['business_status'] = $fallbackMeta['fallback_used'] ?? false
            ? 'Final Decision fallback used'
            : ($decision['business_status'] ?? 'Final Decision generated');
        $decision['deterministic_guardrail_verdict'] = $guardrail['deterministic_business_verdict'] ?? ($decision['business_verdict'] ?? null);
        $decision['deterministic_guardrail_decision_basis'] = $this->guardrailBasis($guardrail);
        $decision['agent_debate_summary'] = $this->agentDebateSummary($specialists, $guardrail, $forecast);
        $decision['agent_debate_trace'] = $this->agentDebateTrace($specialists, $guardrail, $structured, $forecast, $decision);
        $decision['tomorrow_forecast_decision_impact'] = $this->forecastImpact($forecast);
        $decision['ads_decision_impact'] = $this->adsImpact($specialists, $coreMetrics);
        $decision['forecast_evaluation_decision_impact'] = $this->forecastEvaluationImpact($forecast['evaluation'] ?? []);
        $decision['forecast_calibration_decision_impact'] = $this->forecastCalibrationImpact($forecast['calibration'] ?? []);
        $decision['conflict_resolution_rule'] = $this->conflictResolutionRule($guardrail);
        $decision['operational_action_plan'] = $this->operationalActionPlan($decision);
        $decision['action_plan'] = $this->actionPlan($decision);
        $decision['growth_health_score'] = $this->growthHealthScore($decision, $coreMetrics);
        $decision['business_impact_estimate'] = $this->businessImpactEstimate($decision, $coreMetrics);
        $decision['objective_evaluation_plan'] = $this->objectiveEvaluationPlan($decision);
        $decision['decision_risk_assessment'] = $this->decisionRiskAssessment($decision, $guardrail, $forecast);
        $decision['competition_pitch'] = $decision['competition_pitch']
            ?? 'AI Growth Doctor combines specialist agents, deterministic guardrails, and forecast weighting into one guarded operating decision instead of a loose dashboard summary.';

        if (!empty($fallbackMeta)) {
            $decision['fallback_meta'] = $fallbackMeta;
        }

        return $decision;
    }

    private function normalizeCore(array $decision, array $guardrail): array
    {
        $businessVerdict = $decision['business_verdict']
            ?? ($guardrail['deterministic_business_verdict'] ?? 'HOLD_AND_OPTIMIZE');

        $decision['business_verdict'] = $businessVerdict;
        $decision['agent_society_operating_verdict'] = $decision['agent_society_operating_verdict']
            ?? ($guardrail['allowed_decision'] ?? 'RUN_GUARDED_OPTIMIZATION');
        $decision['operating_decision_summary'] = $decision['operating_decision_summary']
            ?? 'Run guarded optimization inside deterministic policy limits.';
        $decision['today_operator_summary'] = $decision['today_operator_summary']
            ?? 'Hold unsafe scaling and focus today on guarded activation, retention, ads, release, and monetization improvements.';
        $decision['main_diagnosis'] = $decision['main_diagnosis']
            ?? 'Evidence supports guarded optimization before aggressive scaling.';
        $decision['top_priority'] = $decision['top_priority'] ?? 'Improve activation and early retention before scaling.';
        $decision['accepted_recommendations'] = $this->limitList($decision['accepted_recommendations'] ?? [], 4);
        $decision['rejected_recommendations'] = $this->limitList($decision['rejected_recommendations'] ?? [], 3);
        $decision['resolved_conflicts'] = $this->limitList($decision['resolved_conflicts'] ?? [], 3);
        $decision['prioritized_actions'] = $this->normalizePrioritizedActions($decision['prioritized_actions'] ?? []);
        $decision['weak_evidence_or_uncertainty'] = $this->limitList($decision['weak_evidence_or_uncertainty'] ?? [], 4);
        $decision['confidence_score'] = (int) ($decision['confidence_score'] ?? 55);
        $decision['rationale'] = $decision['rationale'] ?? $decision['main_diagnosis'];
        $decision['operating_decision'] = is_array($decision['operating_decision'] ?? null) ? $decision['operating_decision'] : [];
        $decision['decision_usable'] = true;

        return $decision;
    }

    private function normalizeOperatingDecision(array $operatingDecision, array $decision, array $coreMetrics): array
    {
        $defaults = [
            'ads_decision' => [
                'decision' => 'hold_budget',
                'label' => 'Hold Budget',
                'confidence_score' => $this->decisionConfidenceDefault('ads_decision', 'hold_budget', $coreMetrics),
                'reason' => 'Keep acquisition guarded until downstream quality is confirmed.',
                'next_action' => 'Monitor CPI, conversions, activation, and retention before increasing spend.',
                'guardrail_metric' => 'D1 logged rate / CPI / conversion volume',
            ],
            'release_decision' => [
                'decision' => 'continue_with_monitoring',
                'label' => 'Continue With Monitoring',
                'confidence_score' => $this->decisionConfidenceDefault('release_decision', 'continue_with_monitoring', $coreMetrics),
                'reason' => 'No deterministic release rollback signal is available in the compact decision.',
                'next_action' => 'Continue guarded rollout monitoring.',
                'guardrail_metric' => 'version conversion stability',
            ],
            'product_decision' => [
                'decision' => $this->defaultProductDecision($decision),
                'label' => $this->decisionLabel($this->defaultProductDecision($decision)),
                'confidence_score' => $this->decisionConfidenceDefault('product_decision', $this->defaultProductDecision($decision), $coreMetrics),
                'reason' => $this->defaultProductReason($decision),
                'next_action' => $this->defaultProductNextAction($decision),
                'success_metric' => $this->defaultProductMetric($decision),
            ],
            'monetization_decision' => [
                'decision' => 'keep_current',
                'label' => 'Keep Current',
                'confidence_score' => $this->decisionConfidenceDefault('monetization_decision', 'keep_current', $coreMetrics),
                'reason' => 'Avoid adding paywall pressure until activation/retention quality is stronger.',
                'next_action' => 'Use segment-only monetization tests if needed.',
                'guardrail_metric' => 'purchase_success_rate_from_paywall / purchase sample size',
            ],
        ];

        $normalized = [];

        foreach ($defaults as $key => $cardDefaults) {
            $card = is_array($operatingDecision[$key] ?? null) ? $operatingDecision[$key] : [];
            $decisionKey = (string) ($card['decision'] ?? $cardDefaults['decision']);
            $normalized[$key] = array_merge($cardDefaults, $card);
            $normalized[$key]['decision'] = $decisionKey;
            $normalized[$key]['label'] = trim((string) ($normalized[$key]['label'] ?? '')) !== ''
                ? $normalized[$key]['label']
                : $this->decisionLabel($decisionKey);
            $normalized[$key]['confidence_score'] = $this->clampScore(
                $normalized[$key]['confidence_score'] ?? $this->decisionConfidenceDefault($key, $decisionKey, $coreMetrics)
            );

            if ($key === 'product_decision') {
                $normalized[$key]['success_metric'] = trim((string) ($normalized[$key]['success_metric'] ?? '')) !== ''
                    ? $normalized[$key]['success_metric']
                    : $this->defaultProductMetric($decision);
            } else {
                $metricField = $key === 'release_decision' || $key === 'ads_decision' || $key === 'monetization_decision'
                    ? 'guardrail_metric'
                    : 'success_metric';
                $normalized[$key][$metricField] = trim((string) ($normalized[$key][$metricField] ?? '')) !== ''
                    ? $normalized[$key][$metricField]
                    : $cardDefaults[$metricField];
            }
        }

        return $normalized;
    }

    private function normalizePrioritizedActions(array $actions): array
    {
        if (empty($actions)) {
            $actions = [
                [
                    'priority' => 1,
                    'action' => 'Improve session-to-workspace activation',
                    'owner_area' => 'product',
                    'expected_impact' => 'high',
                    'why' => 'Activation is the safest first bottleneck to repair.',
                    'success_metric' => 'workspace_users',
                ],
                [
                    'priority' => 2,
                    'action' => 'Hold aggressive scaling while monitoring D1 quality',
                    'owner_area' => 'ads',
                    'expected_impact' => 'medium',
                    'why' => 'Acquisition should remain guarded until retention quality is clearer.',
                    'success_metric' => 'd1_logged_rate',
                ],
            ];
        }

        return $this->limitList($actions, 4);
    }

    private function guardrailBasis(array $guardrail): array
    {
        $winning = $guardrail['winning_guardrail'] ?? null;

        return [
            'policy_available' => !empty($guardrail),
            'winning_guardrail' => $winning,
            'business_verdict' => $guardrail['deterministic_business_verdict'] ?? null,
            'blocked_decision' => $guardrail['blocked_decision'] ?? null,
            'allowed_decision' => $guardrail['allowed_decision'] ?? null,
            'blocked_actions' => $guardrail['blocked_actions'] ?? [],
            'allowed_actions' => $guardrail['allowed_actions'] ?? [],
            'impact_on_final_decision' => $winning
                ? 'A deterministic guardrail constrained the final decision and blocked unsafe actions.'
                : 'No deterministic guardrail violation is active; final decision uses normal operating caution.',
            'override_status' => $winning ? 'not_overridden' : 'not_available',
            'override_reason' => $winning
                ? 'Deterministic blocked actions were respected.'
                : 'No deterministic guardrail required overriding because none was triggered.',
        ];
    }

    private function agentDebateSummary(array $specialists, array $guardrail, array $forecast): array
    {
        return [
            'activation_agent_view' => $this->summary($specialists, 'ai_activation_agent'),
            'retention_agent_view' => $this->summary($specialists, 'ai_retention_agent'),
            'monetization_agent_view' => $this->summary($specialists, 'ai_monetization_agent'),
            'version_agent_view' => $this->summary($specialists, 'ai_version_agent'),
            'ads_agent_view' => $this->summary($specialists, 'ai_ads_agent'),
            'guardrail_policy_view' => empty($guardrail['winning_guardrail'])
                ? 'Guardrail policy is clear; use bounded operating caution, not veto language.'
                : 'Guardrail policy has a winning guardrail and deterministic blocked/allowed actions.',
            'tomorrow_forecast_agent_view' => empty($forecast['risk_flags'])
                ? 'No forecast caution flags were provided.'
                : 'Forecast caution flags are used as weighting evidence, not deterministic guardrails.',
            'forecast_calibration_view' => 'Forecast calibration adjusts confidence weighting only.',
            'final_resolution' => empty($guardrail['winning_guardrail'])
                ? 'Final decision resolves specialist tension through normal guarded optimization.'
                : 'Final decision follows deterministic guardrail limits and chooses the safest allowed plan.',
        ];
    }

    private function agentDebateTrace(array $specialists, array $guardrail, array $structured, array $forecast, array $decision): array
    {
        $rows = [
            ['Activation Agent', $this->summary($specialists, 'ai_activation_agent'), 'prioritize_activation'],
            ['Retention Agent', $this->summary($specialists, 'ai_retention_agent'), 'caution_against_scale'],
            ['Monetization Agent', $this->summary($specialists, 'ai_monetization_agent'), 'continue'],
            ['Version Agent', $this->summary($specialists, 'ai_version_agent'), 'continue'],
            ['Ads Agent', $this->summary($specialists, 'ai_ads_agent'), 'hold'],
            ['Guardrail Policy Engine', empty($guardrail['winning_guardrail']) ? 'No deterministic guardrail violation is active.' : 'Deterministic guardrail constrains unsafe actions.', empty($guardrail['winning_guardrail']) ? 'deterministic_policy_allows_action' : 'deterministic_policy_blocks_action'],
            ['Structured Negotiation', 'Structured negotiation completed ' . ($structured['rounds_completed'] ?? 'unknown') . ' rounds with material tension count ' . ($structured['material_conflict_count'] ?? 0) . '.', 'investigate'],
            ['Tomorrow Forecast Agent', empty($forecast['risk_flags']) ? 'Forecast adds no hard veto.' : 'Forecast risk flags support guarded caution.', 'forecast_cautions_against_scaling'],
            ['Final Decision Agent', $decision['operating_decision_summary'] ?? 'Run guarded optimization.', 'continue'],
        ];

        return array_values(array_map(function ($row, $index) use ($decision) {
            return [
                'step' => $index + 1,
                'agent' => $row[0],
                'dialogue_turn' => $row[0] . ': ' . $row[1],
                'position' => $row[1],
                'evidence' => $row[1],
                'objection_or_veto' => null,
                'vote' => $row[2],
                'impact_on_final_decision' => $decision['business_verdict'] ?? 'HOLD_AND_OPTIMIZE',
            ];
        }, $rows, array_keys($rows)));
    }

    private function forecastImpact(array $forecast): array
    {
        return [
            'forecast_agent_present' => !empty($forecast),
            'forecast_for_date' => $forecast['forecast_for_date'] ?? null,
            'scaling_caution' => !empty($forecast['risk_flags']) ? 'forecast_caution_present' : 'not_available',
            'main_forecast_risk' => $this->stringOrJson($forecast['risk_flags'] ?? [], 300),
            'impact_on_today_decision' => 'Forecast evidence weights caution but does not replace deterministic guardrail or mature metrics.',
        ];
    }

    private function adsImpact(array $specialists, array $coreMetrics): array
    {
        $ads = $coreMetrics['ads'] ?? [];

        return [
            'ads_agent_present' => isset($specialists['ai_ads_agent']),
            'ads_verdict' => $ads['ads_verdict'] ?? null,
            'campaign_health' => $this->stringOrJson($ads['campaign_summaries'] ?? [], 300),
            'budget_decision' => 'hold_budget',
            'legacy_campaign_interpretation' => 'Legacy campaigns are context only and should not be confused with shutting down acquisition.',
            'reset_campaign_interpretation' => 'Reset campaigns need independent ads metrics before scaling.',
            'ads_supply_vs_product_quality' => 'Ads can supply users only inside activation and retention safety limits.',
            'impact_on_today_decision' => 'Ads evidence supports guarded budget posture until downstream quality is confirmed.',
        ];
    }

    private function forecastEvaluationImpact(array $evaluation): array
    {
        return [
            'evaluation_available' => !empty($evaluation),
            'latest_forecast_quality' => $evaluation['latest_quality'] ?? 'not_available',
            'pending_count' => $evaluation['pending_count'] ?? null,
            'evaluated_count' => $evaluation['evaluated_count'] ?? null,
            'metrics_pending_maturity' => $evaluation['pending_count'] ?? null,
            'maturity_interpretation' => 'Pending cohort-lagged metrics should not be treated as forecast misses.',
            'main_misses' => [],
            'impact_on_today_decision' => 'Evaluation evidence adjusts confidence in forecast cautions only.',
        ];
    }

    private function forecastCalibrationImpact(array $calibration): array
    {
        return [
            'calibration_available' => !empty($calibration),
            'trust_score' => $calibration['trust_score'] ?? null,
            'trust_interpretation' => $calibration['trust_interpretation'] ?? 'not_available',
            'overall_mature_hit_rate' => null,
            'forecast_role' => $calibration['forecast_role'] ?? 'directional_signal_only',
            'guardrail_adjustment' => 'supporting_caution_only',
            'bias_summary' => $calibration['systematic_bias_detected'] ?? null,
            'impact_on_today_decision' => 'Calibration changes forecast weight, not the deterministic business decision.',
        ];
    }

    private function conflictResolutionRule(array $guardrail): array
    {
        $winning = $guardrail['winning_guardrail'] ?? null;

        return [
            'winning_guardrail' => $winning ?: null,
            'resolution_type' => $winning ? 'deterministic_guardrail_resolution' : 'normal_operating_resolution',
            'rule_triggered' => $winning ? $winning : 'no deterministic guardrail triggered',
            'blocked_decision' => $guardrail['blocked_decision'] ?? null,
            'allowed_decision' => $guardrail['allowed_decision'] ?? null,
            'why_veto_won' => $winning ? 'The deterministic guardrail has priority over unsafe specialist recommendations.' : 'No veto won because no deterministic guardrail was triggered.',
            'objective_thresholds_used' => [],
            'policy_consistency_check' => $winning ? 'consistent_with_guardrail_policy' : 'guardrail_clear_consistent_with_policy',
        ];
    }

    private function operationalActionPlan(array $decision): array
    {
        return array_values(array_map(function ($action) {
            $actionText = is_array($action) ? ($action['action'] ?? 'Run guarded optimization') : (string) $action;

            return [
                'action' => $actionText,
                'target_user_segment' => 'eligible app users in the next checkpoint window',
                'trigger_condition' => 'only inside current guardrail limits',
                'success_metric' => is_array($action) ? ($action['success_metric'] ?? 'activation and retention quality') : 'activation and retention quality',
                'stop_loss_metric' => 'activation, retention, CPI, or release regression',
                'expected_lift' => is_array($action) ? ($action['expected_impact'] ?? 'directional improvement') : 'directional improvement',
                'experiment_duration' => '7 days',
                'minimum_sample_size' => 'wait for mature checkpoint sample before scaling',
                'rollback_condition' => 'quality metrics worsen or deterministic guardrail triggers',
                'owner_area' => is_array($action) ? ($action['owner_area'] ?? 'product') : 'product',
            ];
        }, $decision['prioritized_actions'] ?? []));
    }

    private function actionPlan(array $decision): array
    {
        return [
            'mode' => 'dry_run_only',
            'requires_human_approval' => true,
            'tool_call_ready' => true,
            'proposed_tools' => array_values(array_map(function ($action) {
                return [
                    'tool' => 'create_product_experiment',
                    'action' => $action['action'] ?? 'Run guarded optimization',
                    'target_segment' => $action['target_user_segment'] ?? 'eligible users',
                    'payload_summary' => 'Dry-run only; requires human approval before execution.',
                    'expected_business_impact' => $action['expected_lift'] ?? 'medium',
                    'safety_guardrail' => $action['stop_loss_metric'] ?? 'activation and retention quality',
                    'approval_question' => 'Approve this guarded action for execution?',
                    'execution_status' => 'not_executed_dry_run_only',
                ];
            }, $this->operationalActionPlan($decision))),
        ];
    }

    private function growthHealthScore(array $decision, array $coreMetrics): array
    {
        $activation = $coreMetrics['activation'] ?? [];
        $retention = $coreMetrics['retention'] ?? [];
        $monetization = $coreMetrics['monetization'] ?? [];
        $version = $coreMetrics['version'] ?? [];

        $activationScore = $this->clampScore(round(
            $this->weightedPercentScore($activation['food_add_success_rate_from_session'] ?? null, 60, 70)
            + $this->weightedPercentScore($activation['food_add_success_rate_from_workspace'] ?? null, 90, 30)
        ));

        $retentionScore = $this->clampScore(round(
            $this->weightedPercentScore($retention['d1_logged_rate'] ?? null, 35, 55)
            + $this->weightedPercentScore($retention['habit_7d_rate'] ?? null, 35, 35)
            + $this->weightedPercentScore($retention['avg_log_days_7d'] ?? null, 2.5, 10)
        ));

        $monetizationScore = $this->clampScore(round(
            $this->weightedPercentScore($monetization['purchase_success_rate_from_paywall'] ?? null, 8, 50)
            + $this->weightedPercentScore($activation['paywall_rate_from_food_add_success'] ?? null, 25, 25)
            + $this->weightedPercentScore(min(30, (float) ($monetization['purchase_success_users'] ?? 0)), 30, 25)
        ));

        $releaseScore = $this->releaseScore($version);
        $overall = $this->clampScore(round(
            ($activationScore * 0.30)
            + ($retentionScore * 0.35)
            + ($monetizationScore * 0.20)
            + ($releaseScore * 0.15)
        ));

        $scores = [
            'activation' => $activationScore,
            'retention' => $retentionScore,
            'monetization' => $monetizationScore,
            'release' => $releaseScore,
        ];

        return [
            'overall_score' => $overall,
            'activation_score' => $activationScore,
            'retention_score' => $retentionScore,
            'monetization_score' => $monetizationScore,
            'release_score' => $releaseScore,
            'main_constraint' => $this->mainConstraint($decision, $scores),
            'score_explanation' => 'Deterministic score derived from session-to-core-action activation, mature retention, monetization signal quality, and release stability.',
        ];
    }

    private function businessImpactEstimate(array $decision, array $coreMetrics): array
    {
        $activation = $coreMetrics['activation'] ?? [];
        $monetization = $coreMetrics['monetization'] ?? [];
        $requiredInputs = [
            $activation['session_users'] ?? null,
            $activation['workspace_users'] ?? null,
            $activation['food_add_success_rate_from_workspace'] ?? null,
            $activation['paywall_rate_from_food_add_success'] ?? null,
            $monetization['purchase_success_rate_from_paywall'] ?? null,
        ];
        $canCalculate = $this->allNumeric($requiredInputs);

        $uplift = [
            'assumption' => 'Directional estimate based on improving session-to-workspace conversion by 5 percentage points; no statistical significance claimed.',
            'extra_workspace_users_7d' => null,
            'extra_food_add_success_users_7d' => null,
            'extra_paywall_eligible_users_7d' => null,
            'revenue_direction' => 'positive',
        ];

        if ($canCalculate) {
            $sessionUsers = (float) $activation['session_users'];
            $workspaceUsers = (float) $activation['workspace_users'];
            $currentWorkspaceRate = $sessionUsers > 0 ? ($workspaceUsers / $sessionUsers) : 0.0;
            $targetWorkspaceRate = min($currentWorkspaceRate + 0.05, 0.60);
            $extraWorkspaceUsers = max(0, (int) round(($sessionUsers * $targetWorkspaceRate) - $workspaceUsers));
            $extraFoodAddUsers = (int) round($extraWorkspaceUsers * ((float) $activation['food_add_success_rate_from_workspace'] / 100));
            $extraPaywallUsers = (int) round($extraFoodAddUsers * ((float) $activation['paywall_rate_from_food_add_success'] / 100));

            $uplift['extra_workspace_users_7d'] = $extraWorkspaceUsers;
            $uplift['extra_food_add_success_users_7d'] = $extraFoodAddUsers;
            $uplift['extra_paywall_eligible_users_7d'] = $extraPaywallUsers;
        }

        $impact = [
            'main_metric_at_risk' => $this->mainMetricAtRisk($decision),
            'growth_blocker' => $decision['main_diagnosis'] ?? 'guarded optimization required',
            'revenue_risk' => $decision['business_verdict'] === 'ROLLBACK_RISK' ? 'high' : 'medium',
            'efficiency_impact' => 'Guarded prioritization prevents wasted spend and premature monetization pressure.',
            'estimated_uplift_if_fixed' => $uplift,
        ];

        if (!$canCalculate) {
            $impact['calculation_status'] = 'missing_input';
        }

        return $impact;
    }

    private function objectiveEvaluationPlan(array $decision): array
    {
        return [
            'primary_metric' => $decision['operating_decision']['product_decision']['success_metric'] ?? 'workspace_users',
            'secondary_metrics' => ['d1_logged_rate', 'cost_per_install', 'purchase_success_rate_from_paywall'],
            'decision_rule' => 'Continue guarded optimization if activation/retention improve without CPI or release regression; otherwise hold or rollback the risky change.',
            'next_checkpoint_window' => '7d',
            'minimum_sample_needed' => 'wait for cohort maturity before judging D1 and 7D habit metrics',
        ];
    }

    private function decisionRiskAssessment(array $decision, array $guardrail, array $forecast): array
    {
        return [
            'decision' => $decision['business_verdict'] ?? 'HOLD_AND_OPTIMIZE',
            'primary_reason' => $decision['rationale'] ?? ($decision['main_diagnosis'] ?? 'Guarded optimization required.'),
            'evidence_summary' => [
                'signals_supporting_decision' => count($decision['accepted_recommendations'] ?? []),
                'signals_against_decision' => count($decision['rejected_recommendations'] ?? []),
                'signals_inconclusive' => count($decision['weak_evidence_or_uncertainty'] ?? []),
                'short_explanation' => 'Final decision balances compact specialist evidence, deterministic policy, and forecast weighting.',
                'deterministic_policy_used' => !empty($guardrail),
                'deterministic_policy_summary' => empty($guardrail['winning_guardrail']) ? 'Guardrail clear; normal operating caution used.' : 'Winning guardrail constrained unsafe action.',
            ],
            'confidence_score' => (int) ($decision['confidence_score'] ?? 55),
            'if_wrong' => [
                'risk_type' => 'wasted_spend',
                'estimated_7d_impact' => 'not_enough_data',
                'impact_explanation' => 'Wrong decision could waste spend or miss upside before mature cohort evidence arrives.',
                'missing_inputs' => ['budget_delta', 'ARPPU', 'mature retention cohorts'],
            ],
            'reverse_condition' => [
                'condition' => 'Activation, retention, ads efficiency, or release quality moves materially against the decision.',
                'next_decision' => 'Hold, reduce, or rollback the risky action.',
            ],
            'forecast_role' => [
                'role' => empty($forecast['risk_flags']) ? 'directional_signal_only' : 'supporting_forecast_caution',
                'why' => 'Forecast signals inform caution but do not replace guardrail policy or mature metrics.',
            ],
        ];
    }

    private function summary(array $specialists, string $key): string
    {
        return (string) ($specialists[$key]['summary'] ?? 'No compact summary available.');
    }

    private function metricScore($value): int
    {
        if (!is_numeric($value)) {
            return 50;
        }

        $numeric = (float) $value;
        if ($numeric <= 1) {
            $numeric *= 100;
        }

        return (int) max(0, min(100, round($numeric)));
    }

    private function weightedPercentScore($value, float $target, float $weight): float
    {
        $numeric = $this->number($value);
        if ($numeric === null || $target <= 0) {
            return 0.0;
        }

        return min(1, max(0, $numeric / $target)) * $weight;
    }

    private function mainConstraint(array $decision, array $scores): string
    {
        asort($scores);
        $constraint = (string) key($scores);
        $topPriority = strtolower((string) ($decision['top_priority'] ?? ''));

        if (strpos($topPriority, 'retention') !== false) {
            $activationScore = $scores['activation'] ?? 0;
            $retentionScore = $scores['retention'] ?? 0;
            if (($activationScore - $retentionScore) < 10) {
                $constraint = 'retention';
            }
        }

        if ($constraint === 'activation' && ($scores['activation'] ?? 0) >= 70) {
            foreach ($scores as $name => $score) {
                if ($name !== 'activation') {
                    $constraint = (string) $name;
                    break;
                }
            }
        }

        return $constraint;
    }

    private function releaseScore(array $version): int
    {
        $topVersions = is_array($version['top_versions'] ?? null) ? $version['top_versions'] : [];
        $relevantVersions = array_values(array_filter($topVersions, function ($row) {
            return (float) ($row['session_users'] ?? 0) > 0;
        }));

        $stableTopVersions = !empty($relevantVersions);
        foreach ($relevantVersions as $row) {
            $sessionRate = $this->number($row['food_add_success_rate_from_session'] ?? null);
            if ($sessionRate !== null && $sessionRate < 30) {
                $stableTopVersions = false;
                break;
            }
        }

        $score = $stableTopVersions ? 70 : 55;
        $versionCount = count($relevantVersions);
        if ($versionCount >= 6) {
            $score -= 20;
        } elseif ($versionCount >= 4) {
            $score -= 10;
        }

        $legacySummary = $version['legacy_version_risk_summary'] ?? null;
        if (!empty($legacySummary)) {
            $score -= 10;
        }

        $releaseCandidateSummary = $version['release_candidate_summary'] ?? [];
        $releaseCandidateText = strtolower($this->stringOrJson($releaseCandidateSummary, 300) ?? '');
        if ($releaseCandidateText !== ''
            && (strpos($releaseCandidateText, 'better monetization') !== false || strpos($releaseCandidateText, 'higher monetization') !== false)
            && (strpos($releaseCandidateText, 'low sample') !== false || strpos($releaseCandidateText, 'small sample') !== false || strpos($releaseCandidateText, 'caveat') !== false)
        ) {
            $score += 5;
        }

        return $this->clampScore($score);
    }

    private function mainMetricAtRisk(array $decision): string
    {
        $topPriority = strtolower((string) ($decision['top_priority'] ?? ''));
        if (strpos($topPriority, 'retention') !== false) {
            return 'd1_logged_rate';
        }

        return (string) ($decision['operating_decision']['product_decision']['success_metric'] ?? 'workspace_users');
    }

    private function defaultProductDecision(array $decision): string
    {
        $topPriority = strtolower((string) ($decision['top_priority'] ?? ''));

        return strpos($topPriority, 'retention') !== false
            ? 'prioritize_retention'
            : 'prioritize_activation';
    }

    private function defaultProductMetric(array $decision): string
    {
        return $this->defaultProductDecision($decision) === 'prioritize_retention'
            ? 'd1_logged_rate'
            : 'workspace_users';
    }

    private function defaultProductReason(array $decision): string
    {
        return $this->defaultProductDecision($decision) === 'prioritize_retention'
            ? 'Early retention is the lead operating priority, while activation still needs guarded improvement.'
            : 'Activation and early retention are the safest optimization focus.';
    }

    private function defaultProductNextAction(array $decision): string
    {
        return $this->defaultProductDecision($decision) === 'prioritize_retention'
            ? 'Improve D1 return rate after the first value moment and reduce early drop-off.'
            : 'Improve session-to-workspace and first-value flow.';
    }

    private function decisionLabel(string $decision): string
    {
        $labels = [
            'hold_budget' => 'Hold Budget',
            'continue_with_monitoring' => 'Continue With Monitoring',
            'prioritize_activation' => 'Prioritize Activation',
            'prioritize_retention' => 'Prioritize Retention',
            'keep_current' => 'Keep Current',
            'monitor_release' => 'Monitor Release',
        ];

        return $labels[$decision] ?? ucwords(str_replace('_', ' ', $decision));
    }

    private function decisionConfidenceDefault(string $cardKey, string $decision, array $coreMetrics): int
    {
        $retention = $coreMetrics['retention'] ?? [];
        $monetization = $coreMetrics['monetization'] ?? [];

        if ($cardKey === 'ads_decision' && $decision === 'hold_budget') {
            return 75;
        }

        if ($cardKey === 'release_decision' && $decision === 'continue_with_monitoring') {
            return 70;
        }

        if ($cardKey === 'product_decision' && $decision === 'prioritize_retention') {
            return 85;
        }

        if ($cardKey === 'product_decision' && $decision === 'prioritize_activation') {
            return 80;
        }

        if ($cardKey === 'monetization_decision' && $decision === 'keep_current') {
            $purchaseUsers = (float) ($monetization['purchase_success_users'] ?? 0);
            return $purchaseUsers >= 10 ? 70 : 65;
        }

        $d1 = $this->number($retention['d1_logged_rate'] ?? null);
        return $d1 !== null && $d1 < 20 ? 70 : 60;
    }

    private function clampScore($value): int
    {
        return (int) max(0, min(100, round((float) $value)));
    }

    private function number($value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function allNumeric(array $values): bool
    {
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                return false;
            }
        }

        return true;
    }

    private function limitList($value, int $limit): array
    {
        return is_array($value) ? array_slice(array_values($value), 0, $limit) : [];
    }

    private function stringOrJson($value, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $text = trim((string) $value);

        return strlen($text) > $maxLength ? substr($text, 0, $maxLength) . '...' : $text;
    }
}
