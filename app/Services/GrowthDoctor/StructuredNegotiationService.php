<?php

namespace App\Services\GrowthDoctor;

class StructuredNegotiationService
{
    private const AGENT_META = [
        'ai_activation_agent' => ['agent_name' => 'Activation Agent', 'domain' => 'activation'],
        'ai_retention_agent' => ['agent_name' => 'Retention Agent', 'domain' => 'retention'],
        'ai_monetization_agent' => ['agent_name' => 'Monetization Agent', 'domain' => 'monetization'],
        'ai_version_agent' => ['agent_name' => 'Version Agent', 'domain' => 'version'],
        'ai_ads_agent' => ['agent_name' => 'Ads Agent', 'domain' => 'ads'],
        'ai_tomorrow_forecast_agent' => ['agent_name' => 'Tomorrow Forecast Agent', 'domain' => 'forecast'],
    ];

    public function run(array $context): array
    {
        $metricsContext = $context['metrics_context'] ?? [];
        $specialistOutputs = $context['specialist_outputs'] ?? $context['specialist_agents'] ?? [];
        $guardrailResult = $context['guardrail_result'] ?? $metricsContext['guardrail_policy'] ?? [];
        $forecastEvaluation = $context['forecast_evaluation'] ?? $context['forecast_evaluations'] ?? [];
        $calibrationMemory = $context['calibration_memory'] ?? $context['forecast_model_calibration'] ?? [];

        $standardOutputs = $this->standardizeSpecialistOutputs($specialistOutputs, $metricsContext, $guardrailResult);
        $signals = $this->extractSignals($metricsContext, $guardrailResult, $standardOutputs, $forecastEvaluation, $calibrationMemory);
        $agentResponses = $this->normalizeAgentResponses($this->buildAgentResponses($standardOutputs, $signals, $metricsContext));
        $conflicts = $this->buildConflicts($signals, $agentResponses, $metricsContext);

        if ($this->isMockProvider() && empty($conflicts)) {
            return $this->mockDemoNegotiation($standardOutputs, $metricsContext);
        }

        $summary = $this->summarizeNegotiation($agentResponses, $conflicts);
        $timeline = $this->buildNegotiationTimeline($agentResponses);

        return [
            'round' => 1,
            'negotiation_type' => 'single_round_structured_cross_examination',
            'execution' => $this->buildExecutionSummary($agentResponses, $conflicts, $summary),
            'rules' => [
                'max_rounds' => 1,
                'raw_chain_of_thought_allowed' => false,
                'evidence_required_for_objection' => true,
                'final_decision_owner' => 'FinalDecisionAgent',
            ],
            'specialist_output_summaries' => array_values($standardOutputs),
            'agent_responses' => $agentResponses,
            'negotiation_timeline' => $timeline,
            'conflicts' => $conflicts,
            'baseline_comparison' => $this->buildBaselineComparison($agentResponses, $conflicts),
            'decision_package' => [
                'conflict_matrix' => $conflicts,
                'total_conflict_count' => $summary['total_conflict_count'],
                'material_conflict_count' => $summary['material_conflict_count'],
                'critical_conflict_count' => $summary['critical_conflict_count'],
                'material_or_higher_conflict_count' => $summary['material_or_higher_conflict_count'],
                'material_responses' => array_values(array_filter($agentResponses, function (array $response) {
                    return in_array($response['severity'] ?? 'none', ['material', 'critical'], true);
                })),
                'safe_context_refs' => [
                    'guardrail_policy' => $this->safeKeys($guardrailResult, ['policy_version', 'winning_guardrail', 'triggered_guardrails', 'deterministic_decision']),
                    'forecast_evaluation' => $this->safeKeys($forecastEvaluation, ['status', 'actual_data_available_until', 'evaluated_count', 'pending_count']),
                    'calibration_memory' => $this->safeKeys($calibrationMemory, ['status', 'evaluations_used', 'overall_mature_hit_rate', 'trust_score', 'decision_instruction']),
                ],
            ],
            'summary' => $summary,
        ];
    }

    public function standardizeSpecialistOutputs(array $specialistOutputs, array $metricsContext = [], array $guardrailResult = []): array
    {
        $standard = [];

        foreach (self::AGENT_META as $key => $meta) {
            $agentOutput = $specialistOutputs[$key] ?? [];
            $result = $agentOutput['result'] ?? $agentOutput;
            $domain = $meta['domain'];

            $standard[$key] = [
                'agent_name' => $meta['agent_name'],
                'domain' => $domain,
                'finding' => $this->extractFinding($result, $agentOutput),
                'supporting_evidence' => $this->supportingEvidenceForDomain($domain, $metricsContext, $result),
                'confidence' => $this->confidenceLabel($result['confidence_score'] ?? $agentOutput['confidence_score'] ?? null),
                'recommendation' => $this->extractRecommendation($result),
                'blocked_action_awareness' => $this->blockedActionAwareness($domain, $guardrailResult),
                'cross_domain_consideration' => $this->crossDomainConsideration($domain, $metricsContext, $result),
                'caveat_or_risk' => $this->extractRisk($result),
            ];
        }

        return $standard;
    }

    private function buildAgentResponses(array $standardOutputs, array $signals, array $metricsContext): array
    {
        $responses = [];

        foreach ($standardOutputs as $output) {
            $domain = $output['domain'];
            $responses[] = $this->responseForDomain($domain, $output, $signals, $metricsContext);
        }

        return $responses;
    }

    private function responseForDomain(string $domain, array $output, array $signals, array $metricsContext): array
    {
        if ($domain === 'activation' && $signals['ads_scaling_pressure'] && $signals['activation_weak']) {
            return $this->response($output, 'Ads Agent', 'objection', 'material', 'Scaling ads is unsafe while activation quality is weak.', $this->evidenceRefs($metricsContext, [
                'metrics_context.activation_metrics.metrics_7d.food_add_success_rate_from_session',
                'metrics_context.activation_metrics.metrics_7d.food_add_success_rate_from_workspace',
                'metrics_context.guardrail_policy.deterministic_decision.blocked_actions',
            ], ['activation_health', 'workspace_or_food_success_rate', 'blocked_actions']), 'Hold acquisition scaling until activation recovers.', $output['confidence']);
        }

        if ($domain === 'retention' && $signals['ads_scaling_pressure'] && $signals['retention_weak']) {
            return $this->response($output, 'Ads Agent', 'risk_warning', 'material', 'Ads scaling should remain constrained because retention or early habit evidence is weak.', $this->evidenceRefs($metricsContext, [
                'metrics_context.retention_metrics.metrics_7d_avg.d1_logged_rate',
                'metrics_context.retention_metrics.metrics_7d_avg.habit_7d_rate',
                'metrics_context.retention_metrics.metrics_7d_avg.avg_log_days_7d',
                'metrics_context.guardrail_policy.triggered_guardrails.retention_guardrail',
            ], ['d1_retention_rate', 'habit_7d_rate', 'retention_health']), 'Prioritize first value and habit formation before increasing traffic volume.', $output['confidence']);
        }

        if ($domain === 'monetization' && $signals['retention_weak']) {
            return $this->response($output, 'Retention Agent', 'risk_warning', 'minor', 'Monetization pressure should stay segmented while retention evidence is weak.', $this->evidenceRefs($metricsContext, [
                'metrics_context.monetization_metrics.metrics_7d.purchase_success_users',
                'metrics_context.monetization_metrics.metrics_7d.purchase_success_rate_from_paywall',
                'metrics_context.retention_metrics.status',
                'metrics_context.guardrail_policy.deterministic_decision.blocked_actions',
            ], ['retention_health', 'monetization_recommendation']), 'Keep paywall pressure targeted to users who reached the value moment.', $output['confidence']);
        }

        if ($domain === 'version') {
            return $this->response($output, 'Final Decision Agent', 'support', 'minor', 'Release evidence should be used as supporting context unless version risk is material.', $this->evidenceRefs($metricsContext, [
                'metrics_context.version_metrics.top_versions',
                'metrics_context.version_metrics.versions',
            ], ['version_health', 'release_risk']), $output['recommendation'], $output['confidence']);
        }

        if ($domain === 'ads' && ($signals['activation_weak'] || $signals['retention_weak'] || $signals['guardrail_blocks_scaling'])) {
            $campaignPath = $this->adsCampaignPathPrefix($metricsContext);

            return $this->response($output, 'Retention Agent', 'revised_recommendation', 'material', 'Given weak downstream quality or blocked actions, ads should not scale aggressively today.', $this->evidenceRefs($metricsContext, [
                $campaignPath . '.recent_vs_previous.cost_per_install_change_pct',
                $campaignPath . '.recent_vs_previous.conversion_change_pct',
                'metrics_context.retention_metrics.status',
                'metrics_context.guardrail_policy.deterministic_decision.blocked_actions',
            ], ['ads_quality', 'activation_health', 'retention_health', 'blocked_actions']), 'Keep budget stable, test higher-intent creative, and avoid aggressive scaling.', $output['confidence']);
        }

        if ($domain === 'forecast' && ($signals['forecast_cautions_scaling'] || $signals['evidence_immature'])) {
            $type = $signals['forecast_cautions_scaling'] ? 'risk_warning' : 'request_evidence';
            $severity = $signals['forecast_cautions_scaling'] ? 'material' : 'minor';

            return $this->response($output, 'Final Decision Agent', $type, $severity, 'Forecast evidence should be treated as a caution signal, not a deterministic decision owner.', $this->evidenceRefs($metricsContext, [
                'metrics_context.tomorrow_forecast_metrics.risk_flags.activation_risk',
                'metrics_context.tomorrow_forecast_metrics.risk_flags.retention_risk',
                'metrics_context.tomorrow_forecast_metrics.risk_flags.habit_risk',
                'metrics_context.forecast_model_calibration.trust_score.updated_score',
                'metrics_context.forecast_model_calibration.decision_instruction.forecast_role',
            ], ['forecast_risk_flags', 'forecast_evaluation', 'calibration_memory']), 'Use forecast as weighted evidence and verify mature actuals before stronger action.', $output['confidence']);
        }

        return $this->response($output, 'Final Decision Agent', 'no_material_objection', 'none', 'No material evidence-based objection from this specialist output.', [
            'structured_negotiation.specialist_output_summaries.' . $domain,
            'specialist_output_summary',
        ], null, $output['confidence']);
    }

    private function buildConflicts(array $signals, array $agentResponses, array $metricsContext): array
    {
        $conflicts = [];

        foreach ($agentResponses as $response) {
            if (!$this->shouldCreateConflictFromAgentResponse($response, $metricsContext)) {
                continue;
            }

            $conflict = $this->buildConflictFromAgentResponse($response, $metricsContext);
            $conflicts[$conflict['conflict_id']] = $conflict;
        }

        return array_values($conflicts);
    }

    private function shouldCreateConflictFromAgentResponse(array $response, array $metricsContext): bool
    {
        $severity = $response['severity'] ?? 'none';
        $type = $response['response_type'] ?? 'no_material_objection';

        if (!in_array($severity, ['material', 'critical'], true)) {
            return false;
        }

        if (!in_array($type, ['objection', 'risk_warning', 'revised_recommendation'], true)) {
            return false;
        }

        $evidenceRefs = $response['evidence_refs'] ?? [];
        if (!is_array($evidenceRefs) || empty($evidenceRefs)) {
            return false;
        }

        $evidenceText = strtolower(json_encode($evidenceRefs));
        $claimText = strtolower(($response['claim'] ?? '') . ' ' . ($response['revised_recommendation'] ?? ''));

        $hasConcreteRiskEvidence = $this->containsAny($evidenceText, [
            'guardrail',
            'blocked_actions',
            'retention_metrics.status',
            'activation_metrics',
            'risk_flags',
            'forecast_model_calibration',
            'low_trust',
            'trust_score',
        ]);

        if (!$hasConcreteRiskEvidence) {
            return false;
        }

        $hasActionConflict = ($response['target_agent'] ?? 'Final Decision Agent') !== 'Final Decision Agent'
            || $this->containsAny($claimText, [
                'scale',
                'scaling',
                'budget',
                'blocked action',
                'paywall',
                'monetization pressure',
                'broad',
                'aggressive',
                'rollout',
                'override',
                'decision owner',
                'deterministic decision',
            ]);

        return $hasActionConflict;
    }

    private function buildConflictFromAgentResponse(array $response, array $metricsContext): array
    {
        $text = strtolower(($response['agent_name'] ?? '') . ' ' . ($response['claim'] ?? '') . ' ' . ($response['revised_recommendation'] ?? ''));
        $severity = $response['severity'] ?? 'material';
        $resolution = $response['revised_recommendation'] ?? 'Send to Final Decision Agent for guardrail-aware resolution.';

        if (($response['agent_name'] ?? '') === 'Ads Agent' || $this->containsAny($text, ['ads', 'acquisition', 'budget'])) {
            return [
                'conflict_id' => 'conflict_ads_scale_vs_retention',
                'topic' => 'Should acquisition budget scale today?',
                'agents_involved' => array_values(array_unique([$response['agent_name'] ?? 'Ads Agent', 'Ads Agent', 'Retention Agent', 'Activation Agent'])),
                'conflict_type' => $this->containsAny(strtolower(json_encode($response['evidence_refs'] ?? [])), ['blocked_actions', 'guardrail']) ? 'guardrail_adjustment_conflict' : 'execution_conflict',
                'severity' => $severity,
                'initial_position' => 'Ads performance may support cautious acquisition testing.',
                'counter_position' => 'Retention/activation guardrails make aggressive scaling risky.',
                'evidence_summary' => $this->evidenceSummaryFromResponse($response),
                'resolution_candidate' => $resolution,
            ];
        }

        if (($response['agent_name'] ?? '') === 'Monetization Agent' || $this->containsAny($text, ['paywall', 'monetization pressure', 'monetization', 'purchase'])) {
            return [
                'conflict_id' => 'conflict_paywall_pressure_vs_retention',
                'topic' => 'Should monetization pressure increase today?',
                'agents_involved' => array_values(array_unique([$response['agent_name'] ?? 'Monetization Agent', 'Monetization Agent', 'Retention Agent', 'Activation Agent'])),
                'conflict_type' => 'timing_conflict',
                'severity' => $severity,
                'initial_position' => 'Monetization signal suggests revenue opportunity.',
                'counter_position' => 'Retention or activation weakness makes broad paywall pressure risky.',
                'evidence_summary' => $this->evidenceSummaryFromResponse($response),
                'resolution_candidate' => $resolution,
            ];
        }

        if (($response['agent_name'] ?? '') === 'Tomorrow Forecast Agent' || $this->containsAny($text, ['forecast', 'directional', 'decision owner', 'low-trust', 'low trust', 'calibration'])) {
            return [
                'conflict_id' => 'conflict_forecast_weighting_vs_decision_authority',
                'topic' => 'How much should forecast evidence influence today\'s decision?',
                'agents_involved' => ['Tomorrow Forecast Agent', 'Final Decision Agent'],
                'conflict_type' => 'forecast_weighting_conflict',
                'severity' => $severity,
                'initial_position' => 'Forecast provides directional risk signal.',
                'counter_position' => 'Forecast calibration is low-trust and should not override deterministic guardrails.',
                'evidence_summary' => $this->evidenceSummaryFromResponse($response),
                'resolution_candidate' => $resolution,
            ];
        }

        return [
            'conflict_id' => 'conflict_recommendation_guardrail_review_' . substr(sha1(($response['agent_name'] ?? '') . ($response['claim'] ?? '')), 0, 8),
            'topic' => 'Should this recommendation be accepted without additional guardrail review?',
            'agents_involved' => array_values(array_unique([$response['agent_name'] ?? 'Specialist Agent', $response['target_agent'] ?? 'Final Decision Agent'])),
            'conflict_type' => 'execution_conflict',
            'severity' => $severity,
            'initial_position' => 'Specialist recommendation may imply a business action.',
            'counter_position' => 'Evidence refs indicate guardrail or cross-domain constraints need review.',
            'evidence_summary' => $this->evidenceSummaryFromResponse($response),
            'resolution_candidate' => $resolution,
        ];
    }

    private function evidenceSummaryFromResponse(array $response): array
    {
        $summary = [];
        foreach (array_slice($response['evidence_refs'] ?? [], 0, 5) as $ref) {
            $summary[] = 'Evidence ref: ' . $ref;
        }

        return $summary;
    }

    private function normalizeAgentResponses(array $responses): array
    {
        $normalized = [];
        $seenAgents = [];

        foreach ($responses as $response) {
            $agentName = (string) ($response['agent_name'] ?? 'Unknown Agent');

            if (isset($seenAgents[$agentName])) {
                continue;
            }

            $seenAgents[$agentName] = true;
            $evidenceRefs = $response['evidence_refs'] ?? [];

            if (!is_array($evidenceRefs)) {
                $evidenceRefs = [$evidenceRefs];
            }

            $evidenceRefs = array_values(array_filter($evidenceRefs, function ($ref) {
                return trim((string) $ref) !== '';
            }));

            if (($response['response_type'] ?? null) === 'objection' && empty($evidenceRefs)) {
                $response['response_type'] = 'no_material_objection';
                $response['severity'] = 'none';
                $response['claim'] = 'No material evidence-based objection from this specialist output.';
                $response['revised_recommendation'] = null;
            }

            $response['evidence_refs'] = empty($evidenceRefs) ? ['specialist_output_summary'] : $evidenceRefs;
            $normalized[] = $response;
        }

        return $normalized;
    }

    private function summarizeNegotiation(array $agentResponses, array $conflicts): array
    {
        $materialConflictCount = count(array_filter($conflicts, function (array $conflict) {
            return ($conflict['severity'] ?? null) === 'material';
        }));
        $criticalConflictCount = count(array_filter($conflicts, function (array $conflict) {
            return ($conflict['severity'] ?? null) === 'critical';
        }));

        return [
            'total_conflict_count' => count($conflicts),
            'material_conflict_count' => $materialConflictCount,
            'critical_conflict_count' => $criticalConflictCount,
            'material_or_higher_conflict_count' => $materialConflictCount + $criticalConflictCount,
            'revised_recommendation_count' => count(array_filter($agentResponses, function (array $response) {
                return ($response['response_type'] ?? null) === 'revised_recommendation';
            })),
            'recommended_next_step' => 'Send conflict matrix to orchestrator evidence assembly.',
        ];
    }

    private function buildNegotiationTimeline(array $agentResponses): array
    {
        $timeline = [];

        foreach (array_values($agentResponses) as $index => $response) {
            $from = (string) ($response['agent_name'] ?? 'Unknown Agent');
            $to = (string) ($response['target_agent'] ?? 'Final Decision Agent');
            $type = (string) ($response['response_type'] ?? 'no_material_objection');

            $timeline[] = [
                'sequence' => $index + 1,
                'from' => $from,
                'to' => $to,
                'type' => $type,
                'severity' => $response['severity'] ?? 'none',
                'claim' => $response['claim'] ?? '',
                'evidence_refs' => $response['evidence_refs'] ?? [],
                'revised_recommendation' => $response['revised_recommendation'] ?? null,
                'display_label' => $this->timelineDisplayLabel($from, $to, $type),
            ];
        }

        return $timeline;
    }

    private function timelineDisplayLabel(string $from, string $to, string $type): string
    {
        $verb = [
            'support' => 'supports',
            'objection' => 'objects to',
            'risk_warning' => 'warns',
            'request_evidence' => 'requests evidence from',
            'revised_recommendation' => 'revises recommendation toward',
            'no_material_objection' => 'has no material objection to',
        ][$type] ?? 'responds to';

        return trim($from . ' ' . $verb . ' ' . $to);
    }

    private function buildBaselineComparison(array $agentResponses, array $conflicts): array
    {
        $materialConflictCount = count(array_filter($conflicts, function (array $conflict) {
            return in_array($conflict['severity'] ?? 'none', ['material', 'critical'], true);
        }));
        $unsafePreventionBasis = $this->unsafePreventionBasis($agentResponses, $conflicts);

        $responseCount = max(1, count($agentResponses));
        $evidenceBackedResponses = count(array_filter($agentResponses, function (array $response) {
            return !empty($response['evidence_refs'] ?? []);
        }));
        $caveatResponses = count(array_filter($agentResponses, function (array $response) {
            return in_array($response['response_type'] ?? 'none', ['objection', 'risk_warning', 'request_evidence', 'revised_recommendation'], true);
        }));

        return [
            'single_agent_baseline' => [
                'recommendation' => 'Use the strongest individual agent recommendation without cross-agent conflict screening.',
                'missed_conflicts' => $materialConflictCount,
                'unsafe_recommendation_detected' => false,
                'evidence_coverage_score' => min(100, max(40, (int) round(($evidenceBackedResponses / $responseCount) * 70))),
                'caveat_coverage_score' => min(100, max(30, (int) round(($caveatResponses / $responseCount) * 60))),
            ],
            'agent_society' => [
                'recommendation' => 'Use structured negotiation and conflict matrix before the Final Decision Agent resolves the operating verdict.',
                'conflicts_detected' => $materialConflictCount,
                'unsafe_recommendation_prevented' => !empty($unsafePreventionBasis),
                'unsafe_prevention_basis' => $unsafePreventionBasis,
                'evidence_coverage_score' => min(100, max(70, (int) round(($evidenceBackedResponses / $responseCount) * 100))),
                'caveat_coverage_score' => min(100, max(65, (int) round(($caveatResponses / $responseCount) * 100))),
            ],
        ];
    }

    private function unsafePreventionBasis(array $agentResponses, array $conflicts): array
    {
        $basis = [];

        foreach ($conflicts as $conflict) {
            if (!in_array($conflict['severity'] ?? 'none', ['material', 'critical'], true)) {
                continue;
            }

            $text = strtolower(($conflict['topic'] ?? '') . ' ' . ($conflict['initial_position'] ?? '') . ' ' . ($conflict['counter_position'] ?? '') . ' ' . ($conflict['resolution_candidate'] ?? '') . ' ' . json_encode($conflict['evidence_summary'] ?? []));

            if ($this->containsAny($text, ['scale ads aggressively', 'aggressive scaling', 'aggressive_ads_scale', 'acquisition budget scale'])) {
                $basis[] = 'Guardrail/cross-domain evidence prevented aggressive ads scaling.';
            }

            if ($this->containsAny($text, ['increase paywall pressure', 'broad paywall', 'paywall pressure', 'monetization pressure'])) {
                $basis[] = 'Retention or activation evidence kept monetization pressure segmented.';
            }

            if ($this->containsAny($text, ['aggressive rollout', 'rollback', 'release risk'])) {
                $basis[] = 'Release evidence prevented aggressive rollout action.';
            }

            if ($this->containsAny($text, ['ignore low-trust forecast', 'low-trust', 'low trust', 'directional'])) {
                $basis[] = 'Forecast calibration prevented treating low-trust forecast as primary truth.';
            }
        }

        foreach ($agentResponses as $response) {
            if (($response['response_type'] ?? null) !== 'revised_recommendation') {
                continue;
            }

            $text = strtolower(($response['claim'] ?? '') . ' ' . ($response['revised_recommendation'] ?? '') . ' ' . json_encode($response['evidence_refs'] ?? []));
            if ($this->containsAny($text, ['blocked_actions', 'aggressive', 'scale', 'paywall pressure'])) {
                $basis[] = 'Specialist revised recommendation away from a blocked or risky action.';
            }
        }

        return array_values(array_unique($basis));
    }

    private function isMockProvider(): bool
    {
        return strtolower((string) env('LLM_PROVIDER', '')) === 'mock';
    }

    private function mockDemoNegotiation(array $standardOutputs, array $metricsContext): array
    {
        $agentResponses = $this->normalizeAgentResponses([
            $this->response($standardOutputs['ai_ads_agent'], 'Activation Agent', 'revised_recommendation', 'material', 'Ads performance looks efficient, but downstream activation is weak, so aggressive scaling should be held.', $this->evidenceRefs($metricsContext, [
                $this->adsCampaignPathPrefix($metricsContext) . '.recent_vs_previous.cost_per_install_change_pct',
                $this->adsCampaignPathPrefix($metricsContext) . '.recent_vs_previous.conversion_change_pct',
                'metrics_context.retention_metrics.status',
                'metrics_context.guardrail_policy.deterministic_decision.blocked_actions',
            ], ['ads_quality', 'activation_health', 'blocked_actions']), 'Keep budget stable and test higher-intent creative before scaling.', 'medium'),
            $this->response($standardOutputs['ai_activation_agent'], 'Ads Agent', 'objection', 'material', 'Scaling ads is unsafe while first core action activation is below the safe threshold.', $this->evidenceRefs($metricsContext, [
                'metrics_context.activation_metrics.metrics_7d.food_add_success_rate_from_session',
                'metrics_context.activation_metrics.metrics_7d.food_add_success_rate_from_workspace',
                'metrics_context.guardrail_policy.deterministic_decision.blocked_actions',
            ], ['activation_health', 'first_core_action_rate', 'blocked_actions']), 'Hold acquisition scaling until first action activation recovers.', 'high'),
            $this->response($standardOutputs['ai_retention_agent'], 'Ads Agent', 'support', 'material', 'I support Activation Agent because early retention is also weak.', $this->evidenceRefs($metricsContext, [
                'metrics_context.retention_metrics.metrics_7d_avg.d1_logged_rate',
                'metrics_context.retention_metrics.metrics_7d_avg.habit_7d_rate',
                'metrics_context.retention_metrics.metrics_7d_avg.avg_log_days_7d',
            ], ['d1_retention_rate', 'retention_health']), 'Prioritize first value moment before increasing traffic volume.', 'medium'),
            $this->response($standardOutputs['ai_tomorrow_forecast_agent'], 'Ads Agent', 'risk_warning', 'material', 'Tomorrow forecast should caution against scaling until activation and retention actuals improve.', $this->evidenceRefs($metricsContext, [
                'metrics_context.tomorrow_forecast_metrics.risk_flags.activation_risk',
                'metrics_context.tomorrow_forecast_metrics.risk_flags.retention_risk',
                'metrics_context.tomorrow_forecast_metrics.risk_flags.habit_risk',
                'metrics_context.forecast_model_calibration.trust_score.updated_score',
                'metrics_context.forecast_model_calibration.decision_instruction.forecast_role',
            ], ['forecast_risk_flags', 'forecast_evaluation', 'calibration_memory']), 'Use forecast as caution evidence and verify mature actuals before stronger action.', 'medium'),
            $this->response($standardOutputs['ai_monetization_agent'], 'Final Decision Agent', 'no_material_objection', 'none', 'No material evidence-based objection from monetization in this demo scenario.', ['specialist_output_summary'], null, 'medium'),
            $this->response($standardOutputs['ai_version_agent'], 'Final Decision Agent', 'no_material_objection', 'none', 'No material evidence-based objection from version risk in this demo scenario.', ['specialist_output_summary'], null, 'medium'),
        ]);

        $conflicts = [
            [
                'conflict_id' => 'conflict_ads_scale_vs_activation',
                'topic' => 'Should ads budget be scaled today?',
                'agents_involved' => ['Ads Agent', 'Activation Agent', 'Retention Agent', 'Tomorrow Forecast Agent'],
                'conflict_type' => 'execution_conflict',
                'severity' => 'material',
                'initial_position' => 'Ads performance suggests scaling may be possible.',
                'counter_position' => 'Activation, retention, and forecast caution indicate scaling is unsafe.',
                'evidence_summary' => [
                    'Ads performance looks efficient.',
                    'First core action activation is weak.',
                    'D1 retention is weak.',
                    'Guardrail or forecast signals caution against aggressive scaling.',
                ],
                'resolution_candidate' => 'Hold budget, improve activation, test higher-intent creative, and scale only after downstream quality recovers.',
            ],
        ];

        return [
            'round' => 1,
            'negotiation_type' => 'single_round_structured_cross_examination',
            'execution' => $this->buildExecutionSummary($agentResponses, $conflicts, $this->summarizeNegotiation($agentResponses, $conflicts)),
            'rules' => [
                'max_rounds' => 1,
                'raw_chain_of_thought_allowed' => false,
                'evidence_required_for_objection' => true,
                'final_decision_owner' => 'FinalDecisionAgent',
            ],
            'specialist_output_summaries' => array_values($standardOutputs),
            'agent_responses' => $agentResponses,
            'negotiation_timeline' => $this->buildNegotiationTimeline($agentResponses),
            'conflicts' => $conflicts,
            'baseline_comparison' => $this->buildBaselineComparison($agentResponses, $conflicts),
            'decision_package' => [
                'conflict_matrix' => $conflicts,
                'total_conflict_count' => $this->summarizeNegotiation($agentResponses, $conflicts)['total_conflict_count'],
                'material_conflict_count' => $this->summarizeNegotiation($agentResponses, $conflicts)['material_conflict_count'],
                'critical_conflict_count' => $this->summarizeNegotiation($agentResponses, $conflicts)['critical_conflict_count'],
                'material_or_higher_conflict_count' => $this->summarizeNegotiation($agentResponses, $conflicts)['material_or_higher_conflict_count'],
                'material_responses' => array_values(array_filter($agentResponses, function (array $response) {
                    return in_array($response['severity'] ?? 'none', ['material', 'critical'], true);
                })),
                'safe_context_refs' => [
                    'guardrail_policy' => [],
                    'forecast_evaluation' => [],
                    'calibration_memory' => [],
                ],
            ],
            'summary' => $this->summarizeNegotiation($agentResponses, $conflicts),
        ];
    }

    private function buildExecutionSummary(array $agentResponses, array $conflicts, array $summary): array
    {
        return [
            'mode' => 'deterministic_single_round',
            'max_rounds' => 1,
            'agent_response_count' => count($agentResponses),
            'conflict_count' => count($conflicts),
            'total_conflict_count' => $summary['total_conflict_count'] ?? count($conflicts),
            'material_conflict_count' => $summary['material_conflict_count'] ?? 0,
            'critical_conflict_count' => $summary['critical_conflict_count'] ?? 0,
            'material_or_higher_conflict_count' => $summary['material_or_higher_conflict_count'] ?? 0,
        ];
    }

    private function extractSignals(array $metricsContext, array $guardrailResult, array $standardOutputs, array $forecastEvaluation, array $calibrationMemory): array
    {
        $adsOutput = $standardOutputs['ai_ads_agent'] ?? [];
        $activationOutput = $standardOutputs['ai_activation_agent'] ?? [];
        $retentionOutput = $standardOutputs['ai_retention_agent'] ?? [];
        $monetizationOutput = $standardOutputs['ai_monetization_agent'] ?? [];
        $forecastOutput = $standardOutputs['ai_tomorrow_forecast_agent'] ?? [];

        $blockedActions = $guardrailResult['blocked_actions']
            ?? ($guardrailResult['deterministic_decision']['blocked_actions'] ?? []);
        $blockedDecision = strtolower((string) ($guardrailResult['deterministic_decision']['blocked_decision'] ?? ''));
        $winningGuardrail = strtolower((string) ($guardrailResult['winning_guardrail'] ?? ''));

        $adsFinding = strtolower((string) (($adsOutput['finding'] ?? '') . ' ' . ($adsOutput['recommendation'] ?? '')));
        $monetizationFinding = strtolower((string) (($monetizationOutput['finding'] ?? '') . ' ' . ($monetizationOutput['recommendation'] ?? '')));
        $forecastFinding = strtolower((string) (($forecastOutput['finding'] ?? '') . ' ' . ($forecastOutput['recommendation'] ?? '')));

        return [
            'activation_weak' => $this->isWeakOutput($activationOutput) || $this->metricBelowAny($metricsContext['activation_metrics'] ?? [], ['workspace_rate', 'food_add_success_rate_from_session', 'activation_rate'], 30),
            'retention_weak' => $this->isWeakOutput($retentionOutput) || $this->metricBelowAny($metricsContext['retention_metrics'] ?? [], ['d1_logged_rate', 'habit_7d_rate'], 25),
            'ads_scaling_pressure' => $this->containsAny($adsFinding, ['scale', 'increase_budget', 'cautious_test', 'evaluate_reset_campaign', 'shift_to_reset_campaign']),
            'ads_efficiency_signal' => $this->containsAny($adsFinding, ['efficient', 'healthy', 'recovery', 'scale', 'cpi']),
            'monetization_pressure' => $this->containsAny($monetizationFinding, ['increase', 'paywall', 'revenue', 'purchase', 'monetization']),
            'guardrail_blocks_scaling' => $this->containsAny(strtolower(json_encode($blockedActions) . ' ' . $blockedDecision . ' ' . $winningGuardrail), ['scale', 'aggressive', 'ads']),
            'forecast_cautions_scaling' => $this->containsAny($forecastFinding . ' ' . strtolower(json_encode($metricsContext['tomorrow_forecast_metrics']['risk_flags'] ?? [])), ['scaling_caution', 'block_scaling', 'warning', 'critical', 'caution']),
            'evidence_immature' => $this->containsAny(strtolower(json_encode($forecastEvaluation) . ' ' . json_encode($calibrationMemory)), ['pending_maturity', 'not_enough_data', 'directional_signal_only']),
        ];
    }

    private function response(array $source, string $targetAgent, string $type, string $severity, string $claim, array $evidenceRefs, ?string $revisedRecommendation, string $confidence): array
    {
        return [
            'agent_name' => $source['agent_name'],
            'target_agent' => $targetAgent,
            'response_type' => $type,
            'severity' => $severity,
            'claim' => $claim,
            'evidence_refs' => $evidenceRefs,
            'revised_recommendation' => $revisedRecommendation,
            'confidence' => $confidence,
        ];
    }

    private function extractFinding(array $result, array $agentOutput): string
    {
        $finding = $result['finding']
            ?? $result['main_diagnosis']
            ?? $result['diagnosis']
            ?? $result['executive_summary']
            ?? $result['main_predicted_risk']
            ?? $result['summary']
            ?? $agentOutput['summary']
            ?? $agentOutput['error']
            ?? 'No finding available.';

        return $this->stringify($finding);
    }

    private function extractRecommendation(array $result): string
    {
        $recommendation = $result['recommendation']
            ?? $result['recommended_actions']
            ?? $result['recommended_experiment']
            ?? $result['budget_decision']['reason']
            ?? $result['decision_impact_today']
            ?? $result['impact_on_final_decision']
            ?? 'No recommendation available.';

        return $this->stringify($recommendation);
    }

    private function extractRisk(array $result): string
    {
        $risk = $result['caveat_or_risk']
            ?? $result['risk']
            ?? $result['risks']
            ?? $result['release_risks']
            ?? $result['limitations']
            ?? $result['activation_risk']
            ?? $result['habit_risk']
            ?? 'No caveat or risk stated.';

        return $this->stringify($risk);
    }

    private function supportingEvidenceForDomain(string $domain, array $metricsContext, array $result): array
    {
        if ($domain === 'ads') {
            return $this->adsSupportingEvidence($metricsContext);
        }

        if ($domain === 'forecast') {
            return $this->forecastSupportingEvidence($metricsContext);
        }

        $metricGroups = [
            'activation' => $metricsContext['activation_metrics'] ?? [],
            'retention' => $metricsContext['retention_metrics'] ?? [],
            'monetization' => $metricsContext['monetization_metrics'] ?? [],
            'version' => $metricsContext['version_metrics'] ?? [],
            'ads' => $metricsContext['ads_metrics'] ?? [],
            'forecast' => $metricsContext['tomorrow_forecast_metrics'] ?? [],
        ];

        $facts = $result['metric_facts'] ?? [];
        $evidence = [];

        foreach (array_slice(is_array($facts) ? $facts : [$facts], 0, 3) as $index => $fact) {
            $evidence[] = [
                'metric' => 'agent_metric_fact_' . ($index + 1),
                'value' => $this->stringify($fact),
                'interpretation' => 'Specialist-provided metric fact.',
            ];
        }

        if (!empty($evidence)) {
            return $evidence;
        }

        foreach ($this->flattenMetrics($metricGroups[$domain] ?? []) as $metric => $value) {
            $evidence[] = [
                'metric' => $metric,
                'value' => $value,
                'interpretation' => 'Deterministic metric from shared context.',
            ];

            if (count($evidence) >= 3) {
                break;
            }
        }

        return $evidence;
    }

    private function adsSupportingEvidence(array $metricsContext): array
    {
        $campaignName = $this->selectAdsCampaignName($metricsContext);
        $campaign = $campaignName !== null ? ($metricsContext['ads_metrics']['campaigns'][$campaignName] ?? []) : [];
        $comparison = $campaign['recent_vs_previous'] ?? [];
        $recent = $comparison['recent_3d'] ?? ($campaign['recent_3d'] ?? []);
        $previous = $comparison['previous_7d'] ?? ($campaign['previous_7d'] ?? []);
        $retentionStatus = $metricsContext['retention_metrics']['status'] ?? null;

        return $this->evidenceItems([
            [
                'metric' => 'reset_successor_conversion_rate_recent_3d',
                'value' => $recent['conversion_rate'] ?? null,
                'interpretation' => 'Recent 3-day conversion rate for reset successor campaign.',
            ],
            [
                'metric' => 'reset_successor_conversion_rate_previous_7d',
                'value' => $previous['conversion_rate'] ?? null,
                'interpretation' => 'Previous 7-day conversion rate for comparison.',
            ],
            [
                'metric' => 'reset_successor_cost_per_install_change_pct',
                'value' => $comparison['cost_per_install_change_pct'] ?? null,
                'interpretation' => 'CPI movement for the selected reset successor campaign.',
            ],
            [
                'metric' => 'reset_successor_conversion_change_pct',
                'value' => $comparison['conversion_change_pct'] ?? null,
                'interpretation' => 'Conversion volume movement for the selected reset successor campaign.',
            ],
            [
                'metric' => 'reset_successor_cost_change_pct',
                'value' => $comparison['cost_change_pct'] ?? null,
                'interpretation' => 'Spend movement that helps explain conversion volume changes.',
            ],
            [
                'metric' => 'retention_status',
                'value' => $retentionStatus,
                'interpretation' => 'Downstream retention status constrains aggressive ads scaling.',
            ],
        ]);
    }

    private function forecastSupportingEvidence(array $metricsContext): array
    {
        $riskFlags = $metricsContext['tomorrow_forecast_metrics']['risk_flags'] ?? [];
        $calibration = $metricsContext['forecast_model_calibration'] ?? ($metricsContext['evaluations']['forecast_model_calibration'] ?? []);

        return $this->evidenceItems([
            [
                'metric' => 'activation_risk',
                'value' => $riskFlags['activation_risk'] ?? null,
                'interpretation' => 'Forecast activation risk flag.',
            ],
            [
                'metric' => 'retention_risk',
                'value' => $riskFlags['retention_risk'] ?? null,
                'interpretation' => 'Forecast retention risk flag.',
            ],
            [
                'metric' => 'habit_risk',
                'value' => $riskFlags['habit_risk'] ?? null,
                'interpretation' => 'Forecast habit risk flag.',
            ],
            [
                'metric' => 'monetization_sample',
                'value' => $riskFlags['monetization_sample'] ?? null,
                'interpretation' => 'Monetization sample quality from forecast risk flags.',
            ],
            [
                'metric' => 'forecast_trust_score',
                'value' => $calibration['trust_score']['updated_score'] ?? null,
                'interpretation' => 'Calibration memory trust score for forecast weighting.',
            ],
            [
                'metric' => 'forecast_role',
                'value' => $calibration['decision_instruction']['forecast_role'] ?? null,
                'interpretation' => 'How Final Decision should weight forecast evidence.',
            ],
        ]);
    }

    private function evidenceItems(array $items): array
    {
        return array_values(array_map(function (array $item) {
            $value = $item['value'] ?? null;
            $metric = (string) ($item['metric'] ?? '');

            return [
                'metric' => $metric,
                'value' => $this->formatEvidenceValue($metric, $value),
                'interpretation' => $item['interpretation'],
            ];
        }, $items));
    }

    private function formatEvidenceValue(string $metric, $value): string
    {
        if ($value === null || $value === '') {
            return 'unknown';
        }

        if (is_numeric($value) && (strpos($metric, '_rate') !== false || substr($metric, -4) === '_pct')) {
            return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') . '%';
        }

        return $this->stringify($value);
    }

    private function evidenceRefs(array $metricsContext, array $paths, array $fallbacks): array
    {
        $refs = [];

        foreach ($paths as $path) {
            if ($this->metricPathExists($metricsContext, $path)) {
                $refs[] = $path;
                continue;
            }

            $refs[] = $path . '_missing_path_fallback';
        }

        if (!empty($refs)) {
            return $refs;
        }

        return array_map(function (string $fallback) {
            return $fallback . '_missing_path_fallback';
        }, $fallbacks);
    }

    private function metricPathExists(array $metricsContext, string $path): bool
    {
        $prefix = 'metrics_context.';

        if (strpos($path, $prefix) !== 0) {
            return false;
        }

        $relativePath = substr($path, strlen($prefix));

        return $this->arrayPathExists($metricsContext, $relativePath);
    }

    private function arrayPathExists(array $input, string $path): bool
    {
        $segments = explode('.', $path);
        $current = $input;

        while (!empty($segments)) {
            if (!is_array($current)) {
                return false;
            }

            $matched = false;

            for ($length = count($segments); $length >= 1; $length--) {
                $candidate = implode('.', array_slice($segments, 0, $length));

                if (array_key_exists($candidate, $current)) {
                    $current = $current[$candidate];
                    $segments = array_slice($segments, $length);
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private function adsCampaignPathPrefix(array $metricsContext): string
    {
        $campaignName = $this->selectAdsCampaignName($metricsContext);

        if ($campaignName === null) {
            return 'metrics_context.ads_metrics.campaigns.unknown_campaign_missing_path_fallback';
        }

        return 'metrics_context.ads_metrics.campaigns.' . $campaignName;
    }

    private function selectAdsCampaignName(array $metricsContext): ?string
    {
        $campaigns = $metricsContext['ads_metrics']['campaigns'] ?? [];

        if (!is_array($campaigns) || empty($campaigns)) {
            return null;
        }

        foreach ($campaigns as $name => $campaign) {
            if (($campaign['lifecycle_context']['lifecycle_status'] ?? null) === 'reset_successor') {
                return (string) $name;
            }
        }

        $first = array_key_first($campaigns);

        return $first !== null ? (string) $first : null;
    }

    private function blockedActionAwareness(string $domain, array $guardrailResult): array
    {
        $blockedActions = $guardrailResult['blocked_actions']
            ?? ($guardrailResult['deterministic_decision']['blocked_actions'] ?? []);

        if (!is_array($blockedActions)) {
            return [];
        }

        $domainTerms = [
            'ads' => ['ads', 'scale', 'budget', 'campaign'],
            'activation' => ['activation', 'onboarding', 'workspace'],
            'retention' => ['retention', 'habit', 'd1'],
            'monetization' => ['paywall', 'purchase', 'monetization'],
            'version' => ['release', 'rollback', 'version'],
            'forecast' => ['forecast', 'scaling', 'risk'],
        ];

        $terms = $domainTerms[$domain] ?? [];

        return array_values(array_filter($blockedActions, function ($action) use ($terms) {
            $text = strtolower((string) $action);
            foreach ($terms as $term) {
                if (strpos($text, $term) !== false) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function crossDomainConsideration(string $domain, array $metricsContext, array $result): string
    {
        $consideration = $result['cross_domain_consideration']
            ?? $result['impact_on_final_decision']
            ?? $result['decision_impact_today']
            ?? null;

        if ($consideration !== null) {
            return $this->stringify($consideration);
        }

        $defaults = [
            'activation' => 'Activation quality constrains ads, retention, and monetization decisions.',
            'retention' => 'Retention quality constrains scaling and monetization pressure.',
            'monetization' => 'Monetization upside must be weighed against activation and retention risk.',
            'version' => 'Release risk can constrain product and monetization interpretation.',
            'ads' => 'Ads supply must be validated by downstream activation and retention quality.',
            'forecast' => 'Forecast signals are caution evidence, not final decision ownership.',
        ];

        return $defaults[$domain] ?? 'Cross-domain consideration unavailable.';
    }

    private function confidenceLabel($score): string
    {
        if (is_numeric($score)) {
            $score = (int) $score;

            if ($score >= 75) {
                return 'high';
            }

            if ($score >= 45) {
                return 'medium';
            }

            return 'low';
        }

        $text = strtolower((string) $score);

        return in_array($text, ['low', 'medium', 'high'], true) ? $text : 'medium';
    }

    private function isWeakOutput(array $output): bool
    {
        $text = strtolower(($output['finding'] ?? '') . ' ' . ($output['caveat_or_risk'] ?? '') . ' ' . ($output['recommendation'] ?? ''));

        return $this->containsAny($text, ['weak', 'risk', 'critical', 'warning', 'below', 'leak', 'hold']);
    }

    private function metricBelowAny(array $metrics, array $keys, float $threshold): bool
    {
        $flat = $this->flattenMetrics($metrics);

        foreach ($flat as $key => $value) {
            foreach ($keys as $needle) {
                if (strpos((string) $key, $needle) !== false && is_numeric($value) && (float) $value < $threshold) {
                    return true;
                }
            }
        }

        return false;
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (strpos($text, strtolower($needle)) !== false) {
                return true;
            }
        }

        return false;
    }

    private function flattenMetrics(array $input, string $prefix = ''): array
    {
        $flat = [];

        foreach ($input as $key => $value) {
            $metricKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                foreach ($this->flattenMetrics($value, $metricKey) as $childKey => $childValue) {
                    $flat[$childKey] = $childValue;
                }
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $flat[$metricKey] = $value;
            }
        }

        return $flat;
    }

    private function safeKeys(array $input, array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $result[$key] = $input[$key];
            }
        }

        return $result;
    }

    private function stringify($value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
