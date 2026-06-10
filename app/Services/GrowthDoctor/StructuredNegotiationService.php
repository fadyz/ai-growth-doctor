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
        $maxRounds = min(max((int) ($context['max_rounds'] ?? 3), 1), 3);

        $standardOutputs = $this->standardizeSpecialistOutputs($specialistOutputs, $metricsContext, $guardrailResult);
        $signals = $this->extractSignals($metricsContext, $guardrailResult, $standardOutputs, $forecastEvaluation, $calibrationMemory);

        $turns = [];
        $roundSummaries = [];
        $conflictMatrix = [];
        $revisedRecommendations = [];
        $roundsCompleted = 0;
        $materialConflictCount = 0;
        $earlyExit = false;
        $earlyExitReason = null;

        for ($round = 1; $round <= $maxRounds; $round++) {
            $roundTurns = $this->buildRoundTurns($round, $standardOutputs, $guardrailResult, $turns, $conflictMatrix, $revisedRecommendations, $signals, $metricsContext);

            if (empty($roundTurns)) {
                $roundSummaries[] = $this->buildRoundSummary($round, 'skipped', [], $materialConflictCount, 'No turns generated');
                continue;
            }

            $turns = array_merge($turns, $roundTurns);
            $newRevisions = $this->extractRevisions($roundTurns);

            if (!empty($newRevisions)) {
                $revisedRecommendations = array_merge($revisedRecommendations, $newRevisions);
            }

            $conflictMatrix = $this->buildConflictMatrix($turns, $standardOutputs, $revisedRecommendations, $metricsContext);
            $materialConflictCount = $this->countUnresolvedMaterialOrHigher($conflictMatrix);
            $roundsCompleted = $round;
            $roundSummaries[] = $this->buildRoundSummary($round, 'completed', $roundTurns, $materialConflictCount, null);

            if ($materialConflictCount === 0) {
                $earlyExit = true;
                $earlyExitReason = 'no_material_conflicts_remaining';
                break;
            }

            if ($this->hasNoNewEvidence($roundTurns, $turns)) {
                $earlyExit = true;
                $earlyExitReason = 'no_new_evidence';
                break;
            }
        }

        for ($round = count($roundSummaries) + 1; $round <= $maxRounds; $round++) {
            $roundSummaries[] = $this->buildRoundSummary(
                $round,
                'skipped',
                [],
                $materialConflictCount,
                $earlyExit ? $this->earlyExitSkipReason($earlyExitReason, $materialConflictCount, $round) : 'Not executed'
            );
        }

        $agentResponses = $this->turnsToAgentResponses($turns);
        $summary = $this->summarizeNegotiation($agentResponses, $conflictMatrix);
        $summary['material_conflict_count'] = count(array_filter($conflictMatrix, function (array $conflict) {
            return ($conflict['severity'] ?? null) === 'material' && ($conflict['status'] ?? 'open') !== 'resolved';
        }));
        $summary['critical_conflict_count'] = count(array_filter($conflictMatrix, function (array $conflict) {
            return ($conflict['severity'] ?? null) === 'critical' && ($conflict['status'] ?? 'open') !== 'resolved';
        }));
        $summary['material_or_higher_conflict_count'] = $materialConflictCount;
        $timeline = $this->buildNegotiationTimeline($agentResponses);
        $execution = $this->buildExecutionSummary($agentResponses, $conflictMatrix, $summary, $maxRounds, $roundsCompleted, $earlyExit, $earlyExitReason);
        $safeContextRefs = [
            'app_profile' => $this->safeKeys($metricsContext['app_profile'] ?? [], ['app_id', 'app_name', 'app_category', 'core_action_name', 'core_action_success_label', 'monetization_model', 'data_mode']),
            'mapping_validation' => $this->safeKeys($metricsContext['mapping_validation'] ?? [], ['status', 'mapped_metric_count', 'required_metric_count', 'missing_required_metrics', 'missing_optional_metrics', 'low_sample_warnings']),
            'guardrail_policy' => $this->safeKeys($guardrailResult, ['policy_version', 'winning_guardrail', 'triggered_guardrails', 'deterministic_decision']),
            'forecast_evaluation' => $this->safeKeys($forecastEvaluation, ['status', 'actual_data_available_until', 'evaluated_count', 'pending_count']),
            'calibration_memory' => $this->safeKeys($calibrationMemory, ['status', 'evaluations_used', 'overall_mature_hit_rate', 'trust_score', 'decision_instruction']),
        ];

        return [
            'round' => $roundsCompleted,
            'rounds_completed' => $roundsCompleted,
            'negotiation_type' => 'adaptive_structured_cross_examination',
            'execution' => $execution,
            'rules' => [
                'max_rounds' => $maxRounds,
                'early_exit_enabled' => true,
                'raw_chain_of_thought_allowed' => false,
                'evidence_required_for_objection' => true,
                'evidence_bound_objections' => true,
                'no_free_form_debate' => true,
                'final_decision_owner' => 'FinalDecisionAgent',
            ],
            'specialist_output_summaries' => array_values($standardOutputs),
            'agent_responses' => $agentResponses,
            'negotiation_timeline' => $timeline,
            'round_summaries' => $roundSummaries,
            'negotiation_transcript' => $turns,
            'conflict_matrix' => $conflictMatrix,
            'conflicts' => $conflictMatrix,
            'revised_recommendations' => $revisedRecommendations,
            'graph' => $this->buildNegotiationGraph($roundSummaries, $turns, $execution),
            'baseline_comparison' => $this->buildBaselineComparison($agentResponses, $conflictMatrix),
            'decision_package' => [
                'conflict_matrix' => $conflictMatrix,
                'total_conflict_count' => $summary['total_conflict_count'],
                'material_conflict_count' => $summary['material_conflict_count'],
                'critical_conflict_count' => $summary['critical_conflict_count'],
                'material_or_higher_conflict_count' => $summary['material_or_higher_conflict_count'],
                'bounded_tension_count' => $summary['bounded_tension_count'] ?? 0,
                'resolved_bounded_tension_count' => $summary['resolved_bounded_tension_count'] ?? 0,
                'partial_concession_count' => $summary['partial_concession_count'] ?? 0,
                'safety_bounded_revision_count' => $summary['safety_bounded_revision_count'] ?? 0,
                'bounded_tensions' => array_values(array_filter($conflictMatrix, function (array $conflict) {
                    return ($conflict['type'] ?? null) === 'bounded_tension' || ($conflict['conflict_type'] ?? null) === 'bounded_tension';
                })),
                'material_responses' => array_values(array_filter($agentResponses, function (array $response) {
                    return in_array($response['severity'] ?? 'none', ['material', 'critical'], true);
                })),
                'safe_context_refs' => $safeContextRefs,
            ],
            'orchestrator_package' => [
                'specialist_outputs' => array_values($standardOutputs),
                'negotiation_transcript' => $turns,
                'conflict_matrix' => $conflictMatrix,
                'revised_recommendations' => $revisedRecommendations,
                'round_summaries' => $roundSummaries,
                'early_exit_reason' => $earlyExitReason,
                'rounds_completed' => $roundsCompleted,
                'material_or_higher_conflict_count' => $materialConflictCount,
                'bounded_tension_count' => $summary['bounded_tension_count'] ?? 0,
                'partial_concession_count' => $summary['partial_concession_count'] ?? 0,
                'safety_bounded_revision_count' => $summary['safety_bounded_revision_count'] ?? 0,
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
            $boundedReasoning = $this->boundedReasoningForDomain($domain, $result, $metricsContext, $guardrailResult);

            $standard[$key] = [
                'agent_id' => $this->agentIdFromName($meta['agent_name']),
                'agent_name' => $meta['agent_name'],
                'domain' => $domain,
                'finding' => $this->extractFinding($result, $agentOutput),
                'supporting_evidence' => $this->supportingEvidenceForDomain($domain, $metricsContext, $result),
                'confidence' => $this->confidenceLabel($result['confidence_score'] ?? $agentOutput['confidence_score'] ?? null),
                'recommendation' => $this->extractRecommendation($result),
                'blocked_action_awareness' => $this->blockedActionAwareness($domain, $guardrailResult),
                'cross_domain_consideration' => $this->crossDomainConsideration($domain, $metricsContext, $result),
                'caveat_or_risk' => $this->extractRisk($result),
                'domain_only_position' => $boundedReasoning['domain_only_position'],
                'bounded_system_position' => $boundedReasoning['bounded_system_position'],
                'constraint_acknowledgement' => $boundedReasoning['constraint_acknowledgement'],
                'negotiation_need' => $boundedReasoning['negotiation_need'],
                'residual_conflict' => $boundedReasoning['residual_conflict'],
                'residual_conflict_severity' => $boundedReasoning['residual_conflict_severity'],
                'why_no_further_negotiation_needed' => $boundedReasoning['why_no_further_negotiation_needed'],
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

    private function buildRoundTurns(
        int $round,
        array $specialistOutputs,
        array $guardrailContext,
        array $previousTurns,
        array $conflictMatrix,
        array $revisedRecommendations,
        array $signals,
        array $metricsContext
    ): array {
        if ($round === 1) {
            return $this->buildRoundOneTurns($specialistOutputs, $signals, $metricsContext);
        }

        if ($round === 2) {
            if ($this->countUnresolvedMaterialOrHigher($conflictMatrix) === 0) {
                return [];
            }

            return $this->buildRoundTwoTurns($specialistOutputs, $conflictMatrix, $metricsContext);
        }

        if ($round === 3) {
            if ($this->countUnresolvedMaterialOrHigher($conflictMatrix) === 0) {
                return [];
            }

            return $this->buildRoundThreeTurns($conflictMatrix, $metricsContext);
        }

        return [];
    }

    private function buildRoundOneTurns(array $standardOutputs, array $signals, array $metricsContext): array
    {
        $responses = $this->normalizeAgentResponses($this->buildAgentResponses($standardOutputs, $signals, $metricsContext));

        return $this->normalizeTurns(array_map(function (array $response) {
            return $this->turnFromResponse(1, $this->agentIdFromName($response['agent_name'] ?? null) ?? 'specialist_agent', $response);
        }, $responses));
    }

    private function buildRoundTwoTurns(array $standardOutputs, array $conflictMatrix, array $metricsContext): array
    {
        $turns = [];

        foreach ($conflictMatrix as $conflict) {
            if (!in_array($conflict['severity'] ?? 'none', ['material', 'critical'], true) || ($conflict['status'] ?? 'open') === 'resolved') {
                continue;
            }

            $revisionAgent = $this->revisionAgentForConflict($conflict, $standardOutputs);
            $resolution = $conflict['resolution_candidate'] ?? 'Revise the recommendation to respect the material evidence and guardrail constraints.';
            $turns[] = $this->normalizeTurn([
                'turn_id' => 'r2_' . $revisionAgent['id'] . '_revision_' . substr(sha1($conflict['conflict_id'] ?? $conflict['topic'] ?? 'conflict'), 0, 8),
                'round' => 2,
                'from_agent_id' => $revisionAgent['id'],
                'from_agent_name' => $revisionAgent['name'],
                'target_agent_id' => 'structured_negotiation',
                'target_agent_name' => 'Structured Negotiation Layer',
                'type' => 'revised_recommendation',
                'severity' => 'none',
                'claim' => 'Recommendation revised after material conflict review: ' . ($conflict['topic'] ?? $conflict['title'] ?? 'material conflict'),
                'evidence' => array_values(array_filter(array_merge(
                    ['structured_negotiation.conflict_matrix.' . ($conflict['conflict_id'] ?? 'unresolved')],
                    $conflict['evidence'] ?? [],
                    $conflict['evidence_summary'] ?? []
                ))),
                'requested_change' => $resolution,
                'revised_recommendation' => $resolution,
                'status' => 'resolved',
            ]);
        }

        return $turns;
    }

    private function buildRoundThreeTurns(array $conflictMatrix, array $metricsContext): array
    {
        $turns = [];

        foreach ($conflictMatrix as $conflict) {
            if (!in_array($conflict['severity'] ?? 'none', ['material', 'critical'], true) || ($conflict['status'] ?? 'open') === 'resolved') {
                continue;
            }

            $turns[] = $this->normalizeTurn([
                'turn_id' => 'r3_final_decision_escalation_' . substr(sha1($conflict['conflict_id'] ?? 'conflict'), 0, 8),
                'round' => 3,
                'from_agent_id' => 'structured_negotiation',
                'from_agent_name' => 'Structured Negotiation Layer',
                'target_agent_id' => 'final_decision_agent',
                'target_agent_name' => 'Final Decision Agent',
                'type' => 'escalation',
                'severity' => $conflict['severity'] ?? 'material',
                'claim' => 'Unresolved material conflict requires Final Decision Agent escalation: ' . ($conflict['title'] ?? $conflict['topic'] ?? 'conflict'),
                'evidence' => $conflict['evidence'] ?? ($conflict['evidence_summary'] ?? ['conflict_matrix.' . ($conflict['conflict_id'] ?? 'unknown')]),
                'requested_change' => 'Resolve conflict without violating deterministic guardrails.',
                'revised_recommendation' => null,
                'status' => 'open',
            ]);
        }

        return $turns;
    }

    private function turnFromResponse(int $round, string $fromAgentId, array $response, string $status = 'open'): array
    {
        $targetName = $response['target_agent'] ?? null;

        return $this->normalizeTurn([
            'turn_id' => 'r' . $round . '_' . $fromAgentId . '_' . ($response['response_type'] ?? 'response'),
            'round' => $round,
            'from_agent_id' => $fromAgentId,
            'from_agent_name' => $response['agent_name'] ?? 'Unknown Agent',
            'target_agent_id' => $this->agentIdFromName($targetName),
            'target_agent_name' => $targetName,
            'type' => $response['response_type'] ?? 'no_material_objection',
            'severity' => $response['severity'] ?? 'none',
            'claim' => $response['claim'] ?? '',
            'evidence' => $response['evidence_refs'] ?? [],
            'requested_change' => $response['revised_recommendation'] ?? null,
            'revised_recommendation' => $response['revised_recommendation'] ?? null,
            'status' => $status,
            'meta' => $response['meta'] ?? [],
            'domain_only_position' => $response['domain_only_position'] ?? null,
            'bounded_system_position' => $response['bounded_system_position'] ?? null,
            'constraint_acknowledgement' => $response['constraint_acknowledgement'] ?? [],
            'response_to_challenge' => $response['response_to_challenge'] ?? 'no_challenge',
            'concession_type' => $response['concession_type'] ?? 'none',
            'conflict_after_response' => $response['conflict_after_response'] ?? 'none',
            'residual_conflict' => $response['residual_conflict'] ?? 'none',
            'residual_conflict_severity' => $response['residual_conflict_severity'] ?? 'none',
            'why_no_further_negotiation_needed' => $response['why_no_further_negotiation_needed'] ?? null,
            'display_type' => $response['display_type'] ?? null,
            'ui_label' => $response['ui_label'] ?? null,
            'has_unresolved_material_objection' => $response['has_unresolved_material_objection'] ?? false,
        ]);
    }

    private function normalizeTurns(array $turns): array
    {
        return array_values(array_map(function (array $turn) {
            return $this->normalizeTurn($turn);
        }, $turns));
    }

    private function normalizeTurn(array $turn): array
    {
        $evidence = $turn['evidence'] ?? [];
        if (!is_array($evidence)) {
            $evidence = [$evidence];
        }

        $evidence = array_values(array_filter($evidence, function ($item) {
            return trim((string) $item) !== '';
        }));

        if (in_array($turn['type'] ?? '', ['objection', 'risk_warning', 'escalation', 'final_warning'], true) && empty($evidence)) {
            $turn['type'] = 'no_material_objection';
            $turn['severity'] = 'none';
            $turn['claim'] = 'No material evidence-based objection from this specialist output.';
        }

        $turn['evidence'] = empty($evidence) ? ['specialist_output_summary'] : $evidence;

        return $turn;
    }

    private function buildConflictMatrix(array $turns, array $specialistOutputs, array $revisedRecommendations, array $metricsContext = []): array
    {
        $responses = $this->turnsToAgentResponses($turns);
        $conflicts = $this->buildConflicts([], $responses, []);
        $boundedTensions = $this->buildBoundedTensions($responses, $metricsContext);

        $hardConflicts = array_values(array_map(function (array $conflict) use ($revisedRecommendations) {
            $resolvedBy = $this->resolvedByRevision($conflict, $revisedRecommendations);

            return $conflict + [
                'title' => $conflict['topic'] ?? $conflict['conflict_id'] ?? 'Material conflict',
                'evidence' => $conflict['evidence_summary'] ?? [],
                'status' => $resolvedBy ? 'resolved' : 'open',
                'resolved_by_round' => $resolvedBy,
                'is_unresolved_material_conflict' => !$resolvedBy && in_array($conflict['severity'] ?? 'none', ['material', 'critical'], true),
            ];
        }, $conflicts));

        return array_values(array_merge($hardConflicts, $boundedTensions));
    }

    private function countUnresolvedMaterialOrHigher(array $conflictMatrix): int
    {
        return count(array_filter($conflictMatrix, function (array $conflict) {
            return in_array($conflict['severity'] ?? 'none', ['material', 'critical'], true)
                && ($conflict['status'] ?? 'open') !== 'resolved';
        }));
    }

    private function buildRoundSummary(int $round, string $status, array $turns, int $materialConflictCount, ?string $skipReason): array
    {
        $labels = [
            1 => ['Round 1: Soft constraints, bounded tensions, and immediate alignment', 'Soft Constraints / Bounded Tensions / Immediate Alignment'],
            2 => ['Round 2: Revision / Rebuttal', 'Revision / Rebuttal'],
            3 => ['Round 3: Escalation Only', 'Escalation Only'],
        ];
        $boundedResolution = $this->boundedResolutionSummary($turns, $materialConflictCount);
        $minorTurnCount = count(array_filter($turns, function (array $turn) {
            return ($turn['severity'] ?? 'none') === 'minor';
        }));
        $materialTurnCount = count(array_filter($turns, function (array $turn) {
            return in_array($turn['severity'] ?? 'none', ['material', 'critical'], true);
        }));
        $boundedTensionCount = $this->roundBoundedTensionCount($round, $turns);

        return [
            'round' => $round,
            'label' => $labels[$round][0] ?? ('Round ' . $round),
            'purpose' => $labels[$round][1] ?? 'Structured negotiation',
            'status' => $status,
            'turn_count' => count($turns),
            'material_turn_count' => $materialTurnCount,
            'minor_turn_count' => $minorTurnCount,
            'bounded_tension_count' => $boundedTensionCount,
            'turn_ids' => array_values(array_map(function (array $turn) {
                return $turn['turn_id'] ?? null;
            }, $turns)),
            'material_or_higher_conflict_count_after_round' => $materialConflictCount,
            'unresolved_material_conflict_count_after_round' => $materialConflictCount,
            'resolved_bounded_tension_count_after_round' => $boundedTensionCount,
            'immediate_alignment_detected' => $round === 1 && $materialConflictCount === 0,
            'summary' => $this->roundNarrativeSummary($round, $status, $materialConflictCount, $boundedTensionCount),
            'skip_reason' => $skipReason,
            'why_skipped' => $this->whyRoundSkipped($round, $status),
            'bounded_context_note' => $boundedResolution['bounded_context_note'],
            'unsafe_part_rejected' => $boundedResolution['unsafe_part_rejected'],
            'safe_part_preserved' => $boundedResolution['safe_part_preserved'],
            'why_no_further_negotiation_needed' => $boundedResolution['why_no_further_negotiation_needed'],
        ];
    }

    private function extractRevisions(array $roundTurns): array
    {
        return array_values(array_filter($roundTurns, function (array $turn) {
            return ($turn['type'] ?? null) === 'revised_recommendation';
        }));
    }

    private function revisionAgentForConflict(array $conflict, array $standardOutputs): array
    {
        $text = strtolower(json_encode([
            $conflict['conflict_id'] ?? '',
            $conflict['topic'] ?? '',
            $conflict['agents_involved'] ?? [],
            $conflict['initial_position'] ?? '',
            $conflict['counter_position'] ?? '',
        ]));

        $preferred = [
            'ads' => ['id' => 'ads_agent', 'key' => 'ai_ads_agent', 'name' => 'Ads Agent'],
            'acquisition' => ['id' => 'ads_agent', 'key' => 'ai_ads_agent', 'name' => 'Ads Agent'],
            'budget' => ['id' => 'ads_agent', 'key' => 'ai_ads_agent', 'name' => 'Ads Agent'],
            'paywall' => ['id' => 'monetization_agent', 'key' => 'ai_monetization_agent', 'name' => 'Monetization Agent'],
            'monetization' => ['id' => 'monetization_agent', 'key' => 'ai_monetization_agent', 'name' => 'Monetization Agent'],
            'forecast' => ['id' => 'tomorrow_forecast_agent', 'key' => 'ai_tomorrow_forecast_agent', 'name' => 'Tomorrow Forecast Agent'],
            'rollout' => ['id' => 'version_agent', 'key' => 'ai_version_agent', 'name' => 'Version Agent'],
            'release' => ['id' => 'version_agent', 'key' => 'ai_version_agent', 'name' => 'Version Agent'],
        ];

        foreach ($preferred as $needle => $agent) {
            if (strpos($text, $needle) !== false && isset($standardOutputs[$agent['key']])) {
                return ['id' => $agent['id'], 'name' => $standardOutputs[$agent['key']]['agent_name'] ?? $agent['name']];
            }
        }

        return ['id' => 'structured_negotiation', 'name' => 'Structured Negotiation Layer'];
    }

    private function resolvedByRevision(array $conflict, array $revisedRecommendations): ?int
    {
        if (empty($revisedRecommendations)) {
            return null;
        }

        $conflictText = strtolower(json_encode([
            $conflict['conflict_id'] ?? '',
            $conflict['topic'] ?? '',
            $conflict['agents_involved'] ?? [],
            $conflict['resolution_candidate'] ?? '',
        ]));

        foreach ($revisedRecommendations as $revision) {
            $revisionText = strtolower(json_encode([
                $revision['from_agent_name'] ?? '',
                $revision['claim'] ?? '',
                $revision['revised_recommendation'] ?? '',
                $revision['evidence'] ?? [],
            ]));

            if ($this->revisionAddressesConflict($conflictText, $revisionText)) {
                return (int) ($revision['round'] ?? 2);
            }
        }

        return null;
    }

    private function revisionAddressesConflict(string $conflictText, string $revisionText): bool
    {
        $domains = [
            ['ads', 'acquisition', 'budget', 'scale', 'scaling'],
            ['paywall', 'monetization', 'purchase', 'revenue'],
            ['forecast', 'calibration', 'directional'],
            ['rollout', 'release', 'version'],
            ['activation', 'retention', 'habit'],
        ];

        foreach ($domains as $domainTerms) {
            if ($this->containsAny($conflictText, $domainTerms) && $this->containsAny($revisionText, $domainTerms)) {
                return true;
            }
        }

        return $this->containsAny($revisionText, ['revise', 'revised', 'hold', 'avoid', 'defer', 'wait', 'segment', 'guardrail', 'resolve']);
    }

    private function turnsToAgentResponses(array $turns): array
    {
        return array_values(array_map(function (array $turn) {
            return [
                'agent_name' => $turn['from_agent_name'] ?? 'Unknown Agent',
                'target_agent' => $turn['target_agent_name'] ?? 'Final Decision Agent',
                'response_type' => $turn['type'] ?? 'no_material_objection',
                'severity' => $turn['severity'] ?? 'none',
                'claim' => $turn['claim'] ?? '',
                'evidence_refs' => $turn['evidence'] ?? [],
                'revised_recommendation' => $turn['revised_recommendation'] ?? null,
                'confidence' => 'medium',
                'round' => $turn['round'] ?? null,
                'turn_id' => $turn['turn_id'] ?? null,
                'status' => $turn['status'] ?? 'open',
                'meta' => $turn['meta'] ?? [],
                'domain_only_position' => $turn['domain_only_position'] ?? null,
                'bounded_system_position' => $turn['bounded_system_position'] ?? null,
                'constraint_acknowledgement' => $turn['constraint_acknowledgement'] ?? [],
                'response_to_challenge' => $turn['response_to_challenge'] ?? 'no_challenge',
                'concession_type' => $turn['concession_type'] ?? 'none',
                'conflict_after_response' => $turn['conflict_after_response'] ?? 'none',
                'residual_conflict' => $turn['residual_conflict'] ?? 'none',
                'residual_conflict_severity' => $turn['residual_conflict_severity'] ?? 'none',
                'why_no_further_negotiation_needed' => $turn['why_no_further_negotiation_needed'] ?? null,
                'display_type' => $turn['display_type'] ?? null,
                'ui_label' => $turn['ui_label'] ?? null,
                'has_unresolved_material_objection' => $turn['has_unresolved_material_objection'] ?? false,
            ];
        }, $turns));
    }

    private function hasNoNewEvidence(array $roundTurns, array $allTurns): bool
    {
        if (empty($roundTurns)) {
            return true;
        }

        return false;
    }

    private function agentIdFromName(?string $agentName): ?string
    {
        $map = [
            'Activation Agent' => 'activation_agent',
            'Retention Agent' => 'retention_agent',
            'Monetization Agent' => 'monetization_agent',
            'Version Agent' => 'version_agent',
            'Ads Agent' => 'ads_agent',
            'Tomorrow Forecast Agent' => 'tomorrow_forecast_agent',
            'Final Decision Agent' => 'final_decision_agent',
            'Structured Negotiation Layer' => 'structured_negotiation',
        ];

        return $map[$agentName] ?? null;
    }

    private function buildNegotiationGraph(array $roundSummaries, array $turns, array $execution): array
    {
        return [
            'meta' => [
                'max_rounds' => $execution['max_rounds'] ?? 3,
                'rounds_completed' => $execution['rounds_completed'] ?? 0,
                'early_exit' => $execution['early_exit'] ?? false,
                'early_exit_reason' => $execution['early_exit_reason'] ?? null,
                'early_exit_interpretation' => $execution['early_exit_interpretation'] ?? null,
                'material_or_higher_conflict_count' => $execution['material_or_higher_conflict_count'] ?? 0,
                'bounded_tension_count' => $execution['bounded_tension_count'] ?? 0,
                'resolved_bounded_tension_count' => $execution['resolved_bounded_tension_count'] ?? 0,
                'partial_concession_count' => $execution['partial_concession_count'] ?? 0,
                'safety_bounded_revision_count' => $execution['safety_bounded_revision_count'] ?? 0,
                'execution_mode' => $execution['mode'] ?? 'deterministic_adaptive_bounded_negotiation',
            ],
            'nodes' => array_merge([
                [
                    'id' => 'negotiation_layer',
                    'type' => 'stage',
                    'label' => 'Structured Negotiation Layer',
                    'subtitle' => 'Adaptive Cross-Examination, Max 3 Rounds with Early Exit',
                    'status' => 'completed',
                ],
            ], array_map(function (array $round) {
                return [
                    'id' => 'negotiation_round_' . $round['round'],
                    'type' => 'negotiation_round',
                    'label' => $round['label'],
                    'status' => $round['status'],
                    'turn_count' => $round['turn_count'],
                    'material_conflicts_after_round' => $round['material_or_higher_conflict_count_after_round'],
                    'skip_reason' => $round['skip_reason'],
                ];
            }, $roundSummaries)),
            'edges' => $this->buildNegotiationGraphEdges($turns),
        ];
    }

    private function buildNegotiationGraphEdges(array $turns): array
    {
        $edges = [[
            'source' => 'negotiation_layer',
            'target' => 'orchestrator_evidence_assembly',
            'label' => 'negotiation package',
        ]];

        foreach ($turns as $turn) {
            $edges[] = [
                'source' => $turn['from_agent_id'] ?? 'specialist_agent',
                'target' => 'negotiation_round_' . ($turn['round'] ?? 1),
                'label' => str_replace('_', ' ', $turn['type'] ?? 'response'),
                'severity' => $turn['severity'] ?? 'none',
            ];
        }

        return $edges;
    }

    private function responseForDomain(string $domain, array $output, array $signals, array $metricsContext): array
    {
        if ($domain === 'activation' && $signals['ads_aggressive_scaling_pressure'] && $signals['activation_weak']) {
            return $this->response($output, 'Ads Agent', 'objection', 'material', 'Scaling ads is unsafe while activation quality is weak.', $this->evidenceRefs($metricsContext, [
                'metrics_context.generic_metrics_context.activation.core_action_success_rate_from_entry',
                'metrics_context.generic_metrics_context.activation.core_action_success_rate_from_workspace',
                'metrics_context.source_metric_refs.activation.core_action_success_users.source_path',
                'metrics_context.activation_metrics.metrics_7d.food_add_success_rate_from_session',
                'metrics_context.activation_metrics.metrics_7d.food_add_success_rate_from_workspace',
                'metrics_context.guardrail_policy.deterministic_decision.blocked_actions',
            ], ['activation_health', 'workspace_or_food_success_rate', 'blocked_actions']), 'Hold acquisition scaling until activation recovers.', $output['confidence']);
        }

        if ($domain === 'activation' && $signals['activation_weak']) {
            return $this->response($output, 'Ads Agent', 'soft_operating_constraint', 'minor', 'Activation does not create an unresolved material objection because Ads already avoids aggressive scaling, but session-to-core-action conversion remains a soft operating constraint.', $this->evidenceRefs($metricsContext, [
                'metrics_context.generic_metrics_context.activation.core_action_success_rate_from_entry',
                'metrics_context.generic_metrics_context.activation.core_action_success_rate_from_workspace',
                'metrics_context.source_metric_refs.activation.core_action_success_users.source_path',
                'metrics_context.activation_metrics.metrics_7d.food_add_success_rate_from_session',
                'metrics_context.activation_metrics.metrics_7d.food_add_success_rate_from_workspace',
            ], ['activation_health', 'workspace_or_food_success_rate']), 'Keep acquisition conservative until the first-core-action path improves.', $output['confidence'], [
                'response_to_challenge' => 'warn',
                'conflict_after_response' => 'bounded',
                'residual_conflict' => 'minor',
                'ui_label' => 'Soft Operating Constraint - already bounded',
                'display_type' => 'soft_operating_constraint',
            ]);
        }

        if ($domain === 'retention' && $signals['ads_aggressive_scaling_pressure'] && $signals['retention_weak']) {
            return $this->response($output, 'Ads Agent', 'risk_warning', 'material', 'Ads scaling should remain constrained because retention or early habit evidence is weak.', $this->evidenceRefs($metricsContext, [
                'metrics_context.generic_metrics_context.retention.d1_rate',
                'metrics_context.generic_metrics_context.retention.habit_7d_rate',
                'metrics_context.generic_metrics_context.retention.avg_active_days_7d',
                'metrics_context.retention_metrics.metrics_7d_avg.d1_logged_rate',
                'metrics_context.retention_metrics.metrics_7d_avg.habit_7d_rate',
                'metrics_context.guardrail_policy.triggered_guardrails.retention_guardrail',
            ], ['d1_retention_rate', 'habit_7d_rate', 'retention_health']), 'Prioritize first value and habit formation before increasing traffic volume.', $output['confidence']);
        }

        if ($domain === 'retention' && $signals['retention_weak']) {
            return $this->response($output, 'Ads Agent', 'soft_operating_constraint', 'minor', 'Retention does not create an unresolved material objection because acquisition is already conservative, but D1 habit remains a soft operating constraint.', $this->evidenceRefs($metricsContext, [
                'metrics_context.generic_metrics_context.retention.d1_rate',
                'metrics_context.generic_metrics_context.retention.habit_7d_rate',
                'metrics_context.generic_metrics_context.retention.avg_active_days_7d',
                'metrics_context.retention_metrics.metrics_7d_avg.d1_logged_rate',
                'metrics_context.retention_metrics.metrics_7d_avg.habit_7d_rate',
            ], ['d1_retention_rate', 'habit_7d_rate', 'retention_health']), 'Prioritize D1 habit repair before acquisition or monetization pressure.', $output['confidence'], [
                'response_to_challenge' => 'warn',
                'conflict_after_response' => 'bounded',
                'residual_conflict' => 'minor',
                'ui_label' => 'Soft Operating Constraint - already bounded',
                'display_type' => 'soft_operating_constraint',
            ]);
        }

        if ($domain === 'monetization' && $signals['retention_weak']) {
            return $this->response($output, 'Retention Agent', 'risk_warning', 'minor', 'Monetization pressure should stay segmented while retention evidence is weak.', $this->evidenceRefs($metricsContext, [
                'metrics_context.generic_metrics_context.monetization.purchase_success_users',
                'metrics_context.generic_metrics_context.monetization.purchase_success_rate_from_exposure',
                'metrics_context.generic_metrics_context.retention.d1_rate',
                'metrics_context.monetization_metrics.metrics_7d.purchase_success_users',
                'metrics_context.monetization_metrics.metrics_7d.purchase_success_rate_from_paywall',
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

            return $this->response($output, 'Retention Agent', 'partial_concession', 'minor', 'Ads Agent made a safety-bounded partial concession: aggressive scaling is rejected, but the safe cautious controlled reset-campaign test or monitor-reset posture is preserved.', $this->evidenceRefs($metricsContext, [
                'metrics_context.generic_metrics_context.ads.cost_per_conversion',
                'metrics_context.generic_metrics_context.ads.conversion_rate',
                'metrics_context.generic_metrics_context.retention.d1_rate',
                $campaignPath . '.recent_vs_previous.cost_per_install_change_pct',
                $campaignPath . '.recent_vs_previous.conversion_change_pct',
                'metrics_context.guardrail_policy.deterministic_decision.blocked_actions',
            ], ['ads_quality', 'activation_health', 'retention_health', 'blocked_actions']), 'Reject aggressive scaling; preserve a cautious controlled test or monitor-reset campaign with stable budget and downstream activation/retention checks.', $output['confidence'], [
                'bounded_safe_context_received' => true,
                'concession_type' => 'safety_bounded_partial_concession',
                'blind_concession' => false,
                'unsafe_part_rejected' => 'aggressive ads scaling or broad budget increase while activation/retention quality is weak',
                'safe_part_preserved' => 'cautious controlled test / monitor reset campaign with stable budget and downstream checks',
                'why_no_further_negotiation_needed' => 'Ads Agent already incorporated activation and retention constraints into its bounded-system position, so no unresolved material conflict remains.',
            ]);
        }

        if ($domain === 'forecast' && ($signals['forecast_cautions_scaling'] || $signals['evidence_immature'])) {
            $type = $signals['forecast_cautions_scaling'] ? 'risk_warning' : 'request_evidence';
            $severity = 'minor';

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
                'evidence_summary' => $this->evidenceSummaryFromResponse($response, $metricsContext),
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
                'evidence_summary' => $this->evidenceSummaryFromResponse($response, $metricsContext),
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
                'evidence_summary' => $this->evidenceSummaryFromResponse($response, $metricsContext),
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
            'evidence_summary' => $this->evidenceSummaryFromResponse($response, $metricsContext),
            'resolution_candidate' => $resolution,
        ];
    }

    private function buildBoundedTensions(array $agentResponses, array $metricsContext): array
    {
        $responsesByAgent = [];
        foreach ($agentResponses as $response) {
            $responsesByAgent[$response['agent_name'] ?? 'Unknown Agent'] = $response;
        }

        $tensions = [];
        if (isset($responsesByAgent['Ads Agent']) || isset($responsesByAgent['Activation Agent']) || isset($responsesByAgent['Retention Agent'])) {
            $campaignPath = $this->adsCampaignPathPrefix($metricsContext);
            $tensions[] = $this->boundedTension([
                'conflict_id' => 'tension_ads_efficiency_vs_activation_retention_constraints',
                'title' => 'Ads efficiency vs activation/retention constraints',
                'status' => 'resolved_in_round_1',
                'resolution_mode' => 'safety_bounded_partial_concession',
                'domain_only_tension' => 'Ads efficiency supports monitoring the reset successor campaign and possibly running a cautious controlled test.',
                'bounded_system_resolution' => 'Reject aggressive scaling; preserve only cautious controlled testing with stable budget and downstream quality checks.',
                'supporting_agents' => ['Activation Agent', 'Retention Agent', 'Ads Agent'],
                'evidence_refs' => [
                    'metrics_context.generic_metrics_context.activation.core_action_success_rate_from_entry',
                    'metrics_context.generic_metrics_context.retention.d1_rate',
                    'metrics_context.generic_metrics_context.ads.conversion_rate',
                    $campaignPath . '.recent_vs_previous.cost_per_install_change_pct',
                ],
            ]);
        }

        if (isset($responsesByAgent['Monetization Agent'])) {
            $tensions[] = $this->boundedTension([
                'conflict_id' => 'tension_monetization_signal_vs_low_sample',
                'title' => 'Monetization active signal vs low purchase sample',
                'status' => 'bounded_in_round_1',
                'resolution_mode' => 'value_gated_monetization',
                'domain_only_tension' => 'Paywall is being reached and purchase signal exists.',
                'bounded_system_resolution' => 'Do not increase broad paywall pressure; keep monetization value-gated because purchase_success_users is below minimum sample threshold.',
                'supporting_agents' => ['Monetization Agent', 'Activation Agent', 'Retention Agent'],
                'evidence_refs' => [
                    'metrics_context.generic_metrics_context.monetization.purchase_success_users',
                    'metrics_context.generic_metrics_context.monetization.purchase_success_rate_from_exposure',
                ],
            ]);
        }

        if (isset($responsesByAgent['Tomorrow Forecast Agent'])) {
            $tensions[] = $this->boundedTension([
                'conflict_id' => 'tension_forecast_watch_vs_decision_authority',
                'title' => 'Forecast watch signal vs final decision authority',
                'status' => 'bounded_in_round_1',
                'resolution_mode' => 'forecast_as_supporting_guardrail',
                'domain_only_tension' => 'Forecast can strengthen caution because trust is medium-high.',
                'bounded_system_resolution' => 'Use forecast as weighted evidence only; do not let it override deterministic guardrail and mature actuals.',
                'supporting_agents' => ['Tomorrow Forecast Agent', 'Final Decision Agent'],
                'evidence_refs' => [
                    'metrics_context.tomorrow_forecast_metrics.risk_flags',
                    'metrics_context.forecast_model_calibration.trust_score.updated_score',
                    'metrics_context.forecast_model_calibration.decision_instruction.forecast_role',
                ],
            ]);
        }

        return $tensions;
    }

    private function boundedTension(array $input): array
    {
        return [
            'conflict_id' => $input['conflict_id'],
            'type' => 'bounded_tension',
            'conflict_type' => 'bounded_tension',
            'topic' => $input['title'],
            'title' => $input['title'],
            'severity' => 'minor',
            'status' => $input['status'],
            'detected_in_round' => 1,
            'resolved_in_round' => 1,
            'resolved_by_round' => 1,
            'resolution_mode' => $input['resolution_mode'],
            'domain_only_tension' => $input['domain_only_tension'],
            'bounded_system_resolution' => $input['bounded_system_resolution'],
            'initial_position' => $input['domain_only_tension'],
            'counter_position' => $input['bounded_system_resolution'],
            'resolution_candidate' => $input['bounded_system_resolution'],
            'supporting_agents' => $input['supporting_agents'],
            'agents_involved' => $input['supporting_agents'],
            'evidence_refs' => $input['evidence_refs'],
            'evidence_summary' => array_map(function (string $ref) {
                return 'Evidence ref: ' . $ref;
            }, $input['evidence_refs']),
            'evidence' => $input['evidence_refs'],
            'is_unresolved_material_conflict' => false,
        ];
    }

    private function evidenceSummaryFromResponse(array $response, array $metricsContext = []): array
    {
        $summary = [];
        foreach (array_slice($response['evidence_refs'] ?? [], 0, 5) as $ref) {
            if (strpos((string) $ref, 'metrics_context.generic_metrics_context.') === 0) {
                $relativePath = substr((string) $ref, strlen('metrics_context.'));
                $genericPath = substr($relativePath, strlen('generic_metrics_context.'));
                $value = $this->valueByPath($metricsContext['generic_metrics_context'] ?? [], $genericPath);
                $summary[] = 'Generic metric: ' . str_replace('generic_metrics_context.', '', $relativePath) . ' = ' . $this->formatEvidenceValue((string) $ref, $value);
                continue;
            }

            if (strpos((string) $ref, 'metrics_context.source_metric_refs.') === 0) {
                $summary[] = 'App-specific mapping: ' . $ref;
                continue;
            }

            if (strpos((string) $ref, 'metrics_context.guardrail_policy') === 0) {
                $summary[] = 'Guardrail evidence: ' . $ref;
                continue;
            }

            $summary[] = 'Evidence ref: ' . $ref;
        }

        $profile = $metricsContext['app_profile'] ?? [];
        if (!empty($profile['core_action_success_label'])) {
            $summary[] = 'App-specific mapping: core_action_success = ' . $profile['core_action_success_label'];
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
            return ($conflict['severity'] ?? null) === 'material' && ($conflict['status'] ?? 'open') !== 'resolved';
        }));
        $criticalConflictCount = count(array_filter($conflicts, function (array $conflict) {
            return ($conflict['severity'] ?? null) === 'critical' && ($conflict['status'] ?? 'open') !== 'resolved';
        }));
        $boundedTensionCount = count(array_filter($conflicts, function (array $conflict) {
            return ($conflict['type'] ?? null) === 'bounded_tension' || ($conflict['conflict_type'] ?? null) === 'bounded_tension';
        }));
        $resolvedBoundedTensionCount = count(array_filter($conflicts, function (array $conflict) {
            return (($conflict['type'] ?? null) === 'bounded_tension' || ($conflict['conflict_type'] ?? null) === 'bounded_tension')
                && in_array($conflict['status'] ?? '', ['resolved_in_round_1', 'bounded_in_round_1', 'resolved'], true);
        }));

        return [
            'total_conflict_count' => $materialConflictCount + $criticalConflictCount,
            'conflict_matrix_row_count' => count($conflicts),
            'total_bounded_tension_count' => $boundedTensionCount,
            'soft_operating_tension_count' => $boundedTensionCount,
            'material_conflict_count' => $materialConflictCount,
            'critical_conflict_count' => $criticalConflictCount,
            'material_or_higher_conflict_count' => $materialConflictCount + $criticalConflictCount,
            'unresolved_material_conflict_count' => $materialConflictCount + $criticalConflictCount,
            'resolved_material_conflict_count' => count(array_filter($conflicts, function (array $conflict) {
                return in_array($conflict['severity'] ?? 'none', ['material', 'critical'], true)
                    && in_array($conflict['status'] ?? '', ['resolved', 'resolved_in_round_1'], true);
            })),
            'bounded_tension_count' => $boundedTensionCount,
            'resolved_bounded_tension_count' => $resolvedBoundedTensionCount,
            'material_turn_count' => count(array_filter($agentResponses, function (array $response) {
                return in_array($response['severity'] ?? 'none', ['material', 'critical'], true);
            })),
            'minor_turn_count' => count(array_filter($agentResponses, function (array $response) {
                return ($response['severity'] ?? 'none') === 'minor';
            })),
            'revised_recommendation_count' => count(array_filter($agentResponses, function (array $response) {
                return ($response['response_type'] ?? null) === 'revised_recommendation';
            })),
            'partial_concession_count' => count(array_filter($agentResponses, function (array $response) {
                return ($response['response_type'] ?? null) === 'partial_concession';
            })),
            'safety_bounded_revision_count' => count(array_filter($agentResponses, function (array $response) {
                return ($response['concession_type'] ?? null) === 'safety_bounded_revision';
            })),
            'bounded_resolution_count' => $resolvedBoundedTensionCount,
            'soft_operating_constraint_count' => count(array_filter($agentResponses, function (array $response) {
                return ($response['response_type'] ?? null) === 'soft_operating_constraint';
            })),
            'risk_warning_count' => count(array_filter($agentResponses, function (array $response) {
                return ($response['response_type'] ?? null) === 'risk_warning';
            })),
            'support_count' => count(array_filter($agentResponses, function (array $response) {
                return ($response['response_type'] ?? null) === 'support';
            })),
            'count_semantics' => [
                'total_conflict_count' => 'Unresolved material or critical conflicts only; excludes bounded soft tensions.',
                'revised_recommendation_count' => 'Strict explicit revised_recommendation turns only; excludes safety-bounded partial concessions.',
                'partial_concession_count' => 'Turns where an agent rejects unsafe interpretation while preserving safe action.',
                'bounded_resolution_count' => 'Soft tensions resolved or bounded without requiring Round 2.',
            ],
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
                'domain_only_position' => $response['domain_only_position'] ?? null,
                'bounded_system_position' => $response['bounded_system_position'] ?? null,
                'constraint_acknowledgement' => $response['constraint_acknowledgement'] ?? [],
                'response_to_challenge' => $response['response_to_challenge'] ?? 'no_challenge',
                'concession_type' => $response['concession_type'] ?? 'none',
                'conflict_after_response' => $response['conflict_after_response'] ?? 'none',
                'residual_conflict' => $response['residual_conflict'] ?? 'none',
                'residual_conflict_severity' => $response['residual_conflict_severity'] ?? 'none',
                'why_no_further_negotiation_needed' => $response['why_no_further_negotiation_needed'] ?? null,
                'ui_label' => $response['ui_label'] ?? null,
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
            'soft_operating_constraint' => 'sets a bounded soft constraint for',
            'bounded_constraint_warning' => 'sets a bounded constraint for',
            'request_evidence' => 'requests evidence from',
            'revised_recommendation' => 'revises recommendation toward',
            'partial_concession' => 'partially concedes to',
            'no_material_objection' => 'has no material objection to',
        ][$type] ?? 'responds to';

        return trim($from . ' ' . $verb . ' ' . $to);
    }

    private function buildBaselineComparison(array $agentResponses, array $conflicts): array
    {
        $materialConflictCount = count(array_filter($conflicts, function (array $conflict) {
            return in_array($conflict['severity'] ?? 'none', ['material', 'critical'], true);
        }));
        $boundedTensionCount = count(array_filter($conflicts, function (array $conflict) {
            return ($conflict['type'] ?? null) === 'bounded_tension' || ($conflict['conflict_type'] ?? null) === 'bounded_tension';
        }));
        $unsafePreventionBasis = $this->unsafePreventionBasis($agentResponses, $conflicts);

        $responseCount = max(1, count($agentResponses));
        $evidenceBackedResponses = count(array_filter($agentResponses, function (array $response) {
            return !empty($response['evidence_refs'] ?? []);
        }));
        $caveatResponses = count(array_filter($agentResponses, function (array $response) {
            return in_array($response['response_type'] ?? 'none', ['objection', 'risk_warning', 'request_evidence', 'revised_recommendation', 'partial_concession', 'soft_operating_constraint'], true);
        }));

        return [
            'single_agent_baseline' => [
                'recommendation' => 'Use the strongest individual agent recommendation without cross-agent conflict screening.',
                'missed_conflicts' => $materialConflictCount,
                'missed_soft_tensions' => $boundedTensionCount,
                'unsafe_recommendation_detected' => false,
                'evidence_coverage_score' => min(100, max(40, (int) round(($evidenceBackedResponses / $responseCount) * 70))),
                'caveat_coverage_score' => min(100, max(30, (int) round(($caveatResponses / $responseCount) * 60))),
            ],
            'agent_society' => [
                'recommendation' => 'Use structured negotiation and conflict matrix before the Final Decision Agent resolves the operating verdict.',
                'conflicts_detected' => $materialConflictCount,
                'soft_tensions_detected' => $boundedTensionCount,
                'bounded_tension_count' => $boundedTensionCount,
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
            $meta = $response['meta'] ?? [];
            if (($meta['concession_type'] ?? null) === 'safety_bounded_partial_concession') {
                $basis[] = 'Ads Agent rejected aggressive scaling while preserving the cautious controlled reset-campaign test.';
            }

            $text = strtolower(($response['claim'] ?? '') . ' ' . ($response['revised_recommendation'] ?? '') . ' ' . json_encode($response['evidence_refs'] ?? []));
            if (($response['response_type'] ?? null) === 'revised_recommendation' && $this->containsAny($text, ['blocked_actions', 'aggressive', 'scale', 'paywall pressure'])) {
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
        $roundOneTurns = $this->normalizeTurns([
            $this->turnFromResponse(1, 'activation_agent', $this->response($standardOutputs['ai_activation_agent'], 'Ads Agent', 'objection', 'material', 'Scaling ads is unsafe while first core action activation is below the safe threshold.', $this->evidenceRefs($metricsContext, [
                'metrics_context.activation_metrics.metrics_7d.food_add_success_rate_from_session',
                'metrics_context.activation_metrics.metrics_7d.food_add_success_rate_from_workspace',
                'metrics_context.guardrail_policy.deterministic_decision.blocked_actions',
            ], ['activation_health', 'first_core_action_rate', 'blocked_actions']), 'Hold acquisition scaling until first action activation recovers.', 'high')),
            $this->turnFromResponse(1, 'retention_agent', $this->response($standardOutputs['ai_retention_agent'], 'Activation Agent', 'support', 'material', 'I support Activation Agent because early retention is also weak.', $this->evidenceRefs($metricsContext, [
                'metrics_context.retention_metrics.metrics_7d_avg.d1_logged_rate',
                'metrics_context.retention_metrics.metrics_7d_avg.habit_7d_rate',
                'metrics_context.retention_metrics.metrics_7d_avg.avg_log_days_7d',
            ], ['d1_retention_rate', 'retention_health']), 'Prioritize first value moment before increasing traffic volume.', 'medium')),
            $this->turnFromResponse(1, 'tomorrow_forecast_agent', $this->response($standardOutputs['ai_tomorrow_forecast_agent'], 'Ads Agent', 'risk_warning', 'material', 'Forecast risk increases if acquisition scales before activation and retention recover.', $this->evidenceRefs($metricsContext, [
                'metrics_context.tomorrow_forecast_metrics.risk_flags.activation_risk',
                'metrics_context.tomorrow_forecast_metrics.risk_flags.retention_risk',
                'metrics_context.tomorrow_forecast_metrics.risk_flags.habit_risk',
            ], ['forecast_risk_flags']), 'Use forecast as caution evidence before scaling.', 'medium')),
            $this->turnFromResponse(1, 'monetization_agent', $this->response($standardOutputs['ai_monetization_agent'], 'Final Decision Agent', 'risk_warning', 'minor', 'Do not increase paywall pressure while activation is weak.', $this->evidenceRefs($metricsContext, [
                'metrics_context.monetization_metrics.metrics_7d.purchase_success_rate_from_paywall',
                'metrics_context.retention_metrics.metrics_7d_avg.d1_logged_rate',
            ], ['monetization_recommendation', 'retention_health']), 'Keep paywall pressure segmented to users who reached the value moment.', 'medium')),
        ]);
        $openConflicts = $this->buildConflictMatrix($roundOneTurns, $standardOutputs, [], $metricsContext);
        $roundTwoTurns = $this->buildRoundTwoTurns($standardOutputs, $openConflicts, $metricsContext);
        $turns = array_merge($roundOneTurns, $roundTwoTurns);
        $revisions = $this->extractRevisions($roundTwoTurns);
        $conflictMatrix = $this->buildConflictMatrix($turns, $standardOutputs, $revisions, $metricsContext);
        $agentResponses = $this->turnsToAgentResponses($turns);
        $summary = $this->summarizeNegotiation($agentResponses, $conflictMatrix);
        $summary['material_conflict_count'] = 0;
        $summary['critical_conflict_count'] = 0;
        $summary['material_or_higher_conflict_count'] = 0;
        $roundSummaries = [
            $this->buildRoundSummary(1, 'completed', $roundOneTurns, 1, null),
            $this->buildRoundSummary(2, 'completed', $roundTwoTurns, 0, null),
            $this->buildRoundSummary(3, 'skipped', [], 0, 'Early Exit: material_or_higher_conflict_count = 0'),
        ];
        $execution = $this->buildExecutionSummary($agentResponses, $conflictMatrix, $summary, 3, 2, true, 'no_material_conflicts_remaining');

        return [
            'round' => 2,
            'rounds_completed' => 2,
            'negotiation_type' => 'adaptive_structured_cross_examination',
            'execution' => $execution,
            'rules' => [
                'max_rounds' => 3,
                'early_exit_enabled' => true,
                'raw_chain_of_thought_allowed' => false,
                'evidence_required_for_objection' => true,
                'evidence_bound_objections' => true,
                'no_free_form_debate' => true,
                'final_decision_owner' => 'FinalDecisionAgent',
            ],
            'specialist_output_summaries' => array_values($standardOutputs),
            'agent_responses' => $agentResponses,
            'negotiation_timeline' => $this->buildNegotiationTimeline($agentResponses),
            'round_summaries' => $roundSummaries,
            'negotiation_transcript' => $turns,
            'conflict_matrix' => $conflictMatrix,
            'conflicts' => $conflictMatrix,
            'revised_recommendations' => $revisions,
            'graph' => $this->buildNegotiationGraph($roundSummaries, $turns, $execution),
            'baseline_comparison' => $this->buildBaselineComparison($agentResponses, $conflictMatrix),
            'decision_package' => [
                'conflict_matrix' => $conflictMatrix,
                'total_conflict_count' => $summary['total_conflict_count'],
                'material_conflict_count' => 0,
                'critical_conflict_count' => 0,
                'material_or_higher_conflict_count' => 0,
                'material_responses' => array_values(array_filter($agentResponses, function (array $response) {
                    return in_array($response['severity'] ?? 'none', ['material', 'critical'], true);
                })),
                'safe_context_refs' => [
                    'guardrail_policy' => [],
                    'forecast_evaluation' => [],
                    'calibration_memory' => [],
                ],
            ],
            'orchestrator_package' => [
                'specialist_outputs' => array_values($standardOutputs),
                'negotiation_transcript' => $turns,
                'conflict_matrix' => $conflictMatrix,
                'revised_recommendations' => $revisions,
                'round_summaries' => $roundSummaries,
                'early_exit_reason' => 'no_material_conflicts_remaining',
                'rounds_completed' => 2,
                'material_or_higher_conflict_count' => 0,
            ],
            'summary' => $summary,
        ];
    }

    private function buildExecutionSummary(
        array $agentResponses,
        array $conflicts,
        array $summary,
        int $maxRounds = 3,
        int $roundsCompleted = 0,
        bool $earlyExit = false,
        ?string $earlyExitReason = null
    ): array
    {
        return [
            'mode' => 'deterministic_adaptive_bounded_negotiation',
            'max_rounds' => $maxRounds,
            'rounds_completed' => $roundsCompleted,
            'early_exit' => $earlyExit,
            'early_exit_reason' => $earlyExitReason,
            'early_exit_interpretation' => $this->earlyExitInterpretation($agentResponses, $earlyExit, $earlyExitReason),
            'agent_response_count' => count($agentResponses),
            'conflict_count' => count($conflicts),
            'total_conflict_count' => $summary['total_conflict_count'] ?? count($conflicts),
            'material_conflict_count' => $summary['material_conflict_count'] ?? 0,
            'critical_conflict_count' => $summary['critical_conflict_count'] ?? 0,
            'material_or_higher_conflict_count' => $summary['material_or_higher_conflict_count'] ?? 0,
            'unresolved_material_conflict_count' => $summary['unresolved_material_conflict_count'] ?? 0,
            'bounded_tension_count' => $summary['bounded_tension_count'] ?? 0,
            'total_bounded_tension_count' => $summary['total_bounded_tension_count'] ?? 0,
            'resolved_bounded_tension_count' => $summary['resolved_bounded_tension_count'] ?? 0,
            'soft_operating_tension_count' => $summary['soft_operating_tension_count'] ?? 0,
            'partial_concession_count' => $summary['partial_concession_count'] ?? 0,
            'safety_bounded_revision_count' => $summary['safety_bounded_revision_count'] ?? 0,
            'bounded_resolution_count' => $summary['bounded_resolution_count'] ?? 0,
            'soft_operating_constraint_count' => $summary['soft_operating_constraint_count'] ?? 0,
            'minor_turn_count' => $summary['minor_turn_count'] ?? 0,
            'material_turn_count' => $summary['material_turn_count'] ?? 0,
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
            'activation_weak' => $this->isWeakOutput($activationOutput) || $this->metricBelowAny($metricsContext['generic_metrics_context']['activation'] ?? [], ['core_action_success_rate_from_entry'], 30) || $this->metricBelowAny($metricsContext['activation_metrics'] ?? [], ['workspace_rate', 'food_add_success_rate_from_session', 'activation_rate'], 30),
            'retention_weak' => $this->isWeakOutput($retentionOutput) || $this->metricBelowAny($metricsContext['generic_metrics_context']['retention'] ?? [], ['d1_rate', 'habit_7d_rate'], 25) || $this->metricBelowAny($metricsContext['retention_metrics'] ?? [], ['d1_logged_rate', 'habit_7d_rate'], 25),
            'ads_scaling_pressure' => $this->containsAny($adsFinding, ['scale', 'scaling', 'increase_budget', 'cautious_test', 'controlled test', 'evaluate_reset_campaign', 'shift_to_reset_campaign']),
            'ads_aggressive_scaling_pressure' => $this->containsAny($adsFinding, ['aggressive scale', 'scale aggressively', 'increase budget aggressively', 'broad scale', 'increase_budget_aggressive'])
                && !$this->containsAny($adsFinding, ['do not scale aggressively', 'avoid aggressive scaling', 'no aggressive scale']),
            'ads_safe_controlled_test' => $this->containsAny($adsFinding, ['cautious_test', 'controlled test', 'evaluate_reset_campaign', 'shift_to_reset_campaign', 'monitor reset']),
            'ads_efficiency_signal' => $this->containsAny($adsFinding, ['efficient', 'healthy', 'recovery', 'scale', 'cpi']),
            'monetization_pressure' => $this->containsAny($monetizationFinding, ['increase', 'paywall', 'revenue', 'purchase', 'monetization']),
            'guardrail_blocks_scaling' => $this->containsAny(strtolower(json_encode($blockedActions) . ' ' . $blockedDecision . ' ' . $winningGuardrail), ['scale', 'aggressive', 'ads']),
            'forecast_cautions_scaling' => $this->containsAny($forecastFinding . ' ' . strtolower(json_encode($metricsContext['tomorrow_forecast_metrics']['risk_flags'] ?? [])), ['scaling_caution', 'block_scaling', 'warning', 'critical', 'caution']),
            'evidence_immature' => $this->containsAny(strtolower(json_encode($forecastEvaluation) . ' ' . json_encode($calibrationMemory)), ['pending_maturity', 'not_enough_data', 'directional_signal_only']),
        ];
    }

    private function response(array $source, string $targetAgent, string $type, string $severity, string $claim, array $evidenceRefs, ?string $revisedRecommendation, string $confidence, array $meta = []): array
    {
        $responseToChallenge = $meta['response_to_challenge'] ?? $this->defaultResponseToChallenge($type);
        $concessionType = $meta['concession_type'] ?? ($type === 'partial_concession' ? 'safety_bounded_revision' : 'none');
        if ($concessionType === 'safety_bounded_partial_concession') {
            $concessionType = 'safety_bounded_revision';
        }
        $conflictAfterResponse = $meta['conflict_after_response'] ?? $this->defaultConflictAfterResponse($type, $severity);
        $residualConflict = $meta['residual_conflict'] ?? ($severity === 'minor' ? 'minor' : (in_array($severity, ['material', 'critical'], true) ? $severity : 'none'));

        return [
            'agent_name' => $source['agent_name'],
            'target_agent' => $targetAgent,
            'response_type' => $type,
            'severity' => $severity,
            'claim' => $claim,
            'evidence_refs' => $evidenceRefs,
            'revised_recommendation' => $revisedRecommendation,
            'confidence' => $confidence,
            'meta' => $meta,
            'domain_only_position' => $source['domain_only_position'] ?? null,
            'bounded_system_position' => $source['bounded_system_position'] ?? null,
            'constraint_acknowledgement' => $source['constraint_acknowledgement'] ?? [],
            'response_to_challenge' => $responseToChallenge,
            'concession_type' => $concessionType,
            'conflict_after_response' => $conflictAfterResponse,
            'residual_conflict' => $residualConflict,
            'residual_conflict_severity' => $residualConflict,
            'why_no_further_negotiation_needed' => $meta['why_no_further_negotiation_needed'] ?? ($source['why_no_further_negotiation_needed'] ?? null),
            'display_type' => $meta['display_type'] ?? null,
            'ui_label' => $meta['ui_label'] ?? null,
            'has_unresolved_material_objection' => in_array($severity, ['material', 'critical'], true) && $conflictAfterResponse !== 'resolved',
        ];
    }

    private function defaultResponseToChallenge(string $type): string
    {
        $map = [
            'support' => 'support',
            'objection' => 'warn',
            'risk_warning' => 'warn',
            'request_evidence' => 'warn',
            'soft_operating_constraint' => 'warn',
            'bounded_constraint_warning' => 'warn',
            'revised_recommendation' => 'partially_concede',
            'partial_concession' => 'partially_concede',
            'no_material_objection' => 'no_challenge',
        ];

        return $map[$type] ?? 'no_challenge';
    }

    private function defaultConflictAfterResponse(string $type, string $severity): string
    {
        if ($type === 'partial_concession' || $type === 'revised_recommendation') {
            return 'resolved';
        }

        if (in_array($type, ['soft_operating_constraint', 'bounded_constraint_warning', 'risk_warning', 'request_evidence'], true) && $severity === 'minor') {
            return 'bounded';
        }

        if (in_array($severity, ['material', 'critical'], true)) {
            return 'open';
        }

        return 'none';
    }

    private function boundedReasoningForDomain(string $domain, array $result, array $metricsContext, array $guardrailResult): array
    {
        $defaults = [
            'activation' => [
                'domain_only_position' => 'Session-to-core-action conversion is the largest activation bottleneck.',
                'bounded_system_position' => 'Activation should constrain acquisition scaling until first-core-action flow improves.',
                'constraint_acknowledgement' => ['session_to_core_action_gap', 'ads_scaling_risk', 'monetization_downstream_dependency'],
                'negotiation_need' => 'medium',
                'residual_conflict' => 'none',
                'why_no_further_negotiation_needed' => 'Activation constraint is already represented in the bounded acquisition posture, so no unresolved material conflict remains unless Ads still recommends aggressive scaling.',
            ],
            'retention' => [
                'domain_only_position' => 'D0 to D1 drop indicates weak early habit formation.',
                'bounded_system_position' => 'Prioritize D1 habit repair before increasing acquisition or paywall pressure.',
                'constraint_acknowledgement' => ['d1_drop', 'habit_7d_weakness', 'ads_downstream_quality_constraint'],
                'negotiation_need' => 'medium',
                'residual_conflict' => 'none',
                'why_no_further_negotiation_needed' => 'Retention caution is already applied to acquisition and monetization posture, so only unresolved aggressive scaling would need another round.',
            ],
            'monetization' => [
                'domain_only_position' => 'Paywall-to-purchase conversion is weak and purchase sample is low.',
                'bounded_system_position' => 'Keep monetization segmented and value-gated. Do not increase broad paywall pressure while activation/retention depth is weak.',
                'constraint_acknowledgement' => ['purchase_success_low_sample', 'activation_depth_dependency', 'retention_dependency'],
                'negotiation_need' => 'low',
                'residual_conflict' => 'none',
                'why_no_further_negotiation_needed' => 'Monetization already limits pressure to safer segments, so no broad paywall conflict remains.',
            ],
            'version' => [
                'domain_only_position' => 'Version evidence can explain whether release or instrumentation changes are affecting observed metrics.',
                'bounded_system_position' => 'Use version evidence as supporting context unless current release risk is material.',
                'constraint_acknowledgement' => ['release_context', 'instrumentation_compatibility', 'current_version_scope'],
                'negotiation_need' => 'low',
                'residual_conflict' => 'none',
                'why_no_further_negotiation_needed' => 'Version evidence is supporting context and does not create an unresolved material operating conflict.',
            ],
            'ads' => [
                'domain_only_position' => 'Ads efficiency signals support monitoring the reset successor campaign and possibly running a cautious controlled test because conversion rate improved and CPI moved slightly better.',
                'bounded_system_position' => 'Do not scale aggressively. Keep budget stable and only run a cautious controlled test because activation and D1 habit constraints limit traffic quality.',
                'constraint_acknowledgement' => ['activation_core_action_gap', 'd1_retention_weakness', 'monetization_low_sample', 'blocked_aggressive_scaling_or_no_aggressive_scale'],
                'negotiation_need' => 'low',
                'residual_conflict' => 'none',
                'why_no_further_negotiation_needed' => 'Ads Agent already incorporated activation and retention constraints into its bounded-system position, so no unresolved material conflict remains.',
            ],
            'forecast' => [
                'domain_only_position' => 'Tomorrow forecast risk flags can warn when activation, retention, or habit metrics may deteriorate.',
                'bounded_system_position' => 'Use forecast as weighted caution evidence, not as deterministic decision ownership or an override of guardrail policy.',
                'constraint_acknowledgement' => ['forecast_supporting_guardrail', 'calibration_trust_weighting', 'pending_maturity'],
                'negotiation_need' => 'low',
                'residual_conflict' => 'none',
                'why_no_further_negotiation_needed' => 'Forecast evidence is bounded to caution weighting, so it does not need a further rebuttal round unless it claims final decision authority.',
            ],
        ];

        $reasoning = $defaults[$domain] ?? [
            'domain_only_position' => $this->extractFinding($result, []),
            'bounded_system_position' => $this->extractRecommendation($result),
            'constraint_acknowledgement' => [],
            'negotiation_need' => 'low',
            'residual_conflict' => 'none',
            'why_no_further_negotiation_needed' => 'No unresolved material cross-domain conflict remains after bounded context is applied.',
        ];

        foreach (['domain_only_position', 'bounded_system_position', 'constraint_acknowledgement', 'negotiation_need', 'residual_conflict', 'why_no_further_negotiation_needed'] as $key) {
            if (array_key_exists($key, $result) && $result[$key] !== null && $result[$key] !== '') {
                $reasoning[$key] = $result[$key];
            }
        }

        if (!is_array($reasoning['constraint_acknowledgement'])) {
            $reasoning['constraint_acknowledgement'] = [$this->stringify($reasoning['constraint_acknowledgement'])];
        }

        $blockedActions = $guardrailResult['blocked_actions']
            ?? ($guardrailResult['deterministic_decision']['blocked_actions'] ?? []);
        if ($domain === 'ads' && !empty($blockedActions)) {
            $reasoning['constraint_acknowledgement'][] = 'guardrail_blocked_actions_seen';
        }

        if ($domain === 'ads' && $this->adsStillRecommendsAggressiveScaling($result)) {
            $reasoning['negotiation_need'] = 'high';
            $reasoning['residual_conflict'] = 'material';
            $reasoning['why_no_further_negotiation_needed'] = null;
        }

        $reasoning['constraint_acknowledgement'] = array_values(array_unique(array_filter($reasoning['constraint_acknowledgement'])));
        $reasoning['residual_conflict_severity'] = $reasoning['residual_conflict'] ?? 'none';

        return $reasoning;
    }

    private function adsStillRecommendsAggressiveScaling(array $result): bool
    {
        $text = strtolower($this->stringify([
            $result['recommendation'] ?? null,
            $result['recommended_actions'] ?? null,
            $result['budget_decision'] ?? null,
            $result['ads_verdict'] ?? null,
            $result['impact_on_final_decision'] ?? null,
        ]));

        return $this->containsAny($text, ['aggressive scale', 'scale aggressively', 'increase budget aggressively', 'broad scale'])
            && !$this->containsAny($text, ['do not scale aggressively', 'avoid aggressive scaling', 'no aggressive scale']);
    }

    private function boundedResolutionSummary(array $turns, int $materialConflictCount): array
    {
        $summary = [
            'bounded_context_note' => 'Specialist agents receive bounded safe context before structured negotiation, so Round 1 can resolve unsafe domain-only pressure without forcing artificial debate.',
            'unsafe_part_rejected' => 'Aggressive ads scaling and broad monetization pressure.',
            'safe_part_preserved' => 'Cautious controlled reset-campaign evaluation, rollout monitoring, and value-gated monetization.',
            'why_no_further_negotiation_needed' => $materialConflictCount === 0
                ? 'No unresolved material or critical conflict remains after bounded Round 1 review.'
                : null,
        ];

        foreach ($turns as $turn) {
            $meta = $turn['meta'] ?? [];
            if (($meta['concession_type'] ?? null) !== 'safety_bounded_partial_concession') {
                continue;
            }

            $summary['unsafe_part_rejected'] = $meta['unsafe_part_rejected'] ?? $summary['unsafe_part_rejected'];
            $summary['safe_part_preserved'] = $meta['safe_part_preserved'] ?? $summary['safe_part_preserved'];
            if ($materialConflictCount === 0) {
                $summary['why_no_further_negotiation_needed'] = $meta['why_no_further_negotiation_needed'] ?? $summary['why_no_further_negotiation_needed'];
            }
        }

        return $summary;
    }

    private function roundBoundedTensionCount(int $round, array $turns): int
    {
        if ($round !== 1 || empty($turns)) {
            return 0;
        }

        $count = 0;
        $types = array_map(function (array $turn) {
            return $turn['type'] ?? null;
        }, $turns);

        if (in_array('partial_concession', $types, true) || in_array('soft_operating_constraint', $types, true)) {
            $count++;
        }

        if (in_array('risk_warning', $types, true)) {
            $count++;
        }

        $hasForecast = count(array_filter($turns, function (array $turn) {
            return ($turn['from_agent_name'] ?? null) === 'Tomorrow Forecast Agent';
        })) > 0;
        if ($hasForecast) {
            $count++;
        }

        return max(0, min(3, $count));
    }

    private function roundNarrativeSummary(int $round, string $status, int $materialConflictCount, int $boundedTensionCount): ?string
    {
        if ($round === 1 && $status === 'completed') {
            return 'Agents surfaced soft cross-domain tensions. Because the challenged recommendations were already bounded by safe context, no unresolved material conflict remained.';
        }

        if ($status === 'skipped' && $round === 2) {
            return 'Revision/rebuttal was skipped because only bounded soft tensions remained after Round 1.';
        }

        if ($status === 'skipped' && $round === 3) {
            return 'Escalation was skipped because no unresolved material or critical conflict existed.';
        }

        return $boundedTensionCount > 0 || $materialConflictCount === 0 ? 'No unresolved material or critical conflict remained.' : null;
    }

    private function whyRoundSkipped(int $round, string $status): ?string
    {
        if ($status !== 'skipped') {
            return null;
        }

        if ($round === 2) {
            return 'Only bounded soft tensions remained; no revision/rebuttal round was required.';
        }

        if ($round === 3) {
            return 'No unresolved material or critical conflict existed.';
        }

        return null;
    }

    private function earlyExitInterpretation(array $agentResponses, bool $earlyExit, ?string $earlyExitReason): ?string
    {
        if (!$earlyExit || $earlyExitReason !== 'no_material_conflicts_remaining') {
            return null;
        }

        foreach ($agentResponses as $response) {
            $meta = $response['meta'] ?? [];
            if (($meta['concession_type'] ?? null) === 'safety_bounded_partial_concession') {
                return 'Round 1 ended early because agents already had bounded safe context: Ads did not blindly concede; it rejected aggressive scaling, preserved the cautious controlled reset-campaign test, and left no unresolved material conflict for Round 2.';
            }
        }

        return 'Round 1 ended early because bounded specialist positions left no unresolved material or critical conflict for Round 2.';
    }

    private function earlyExitSkipReason(?string $earlyExitReason, int $materialConflictCount, ?int $round = null): string
    {
        if ($earlyExitReason === 'no_material_conflicts_remaining') {
            if ($round === 2) {
                return 'Early Exit: no unresolved material or critical conflicts remained after Round 1.';
            }

            if ($round === 3) {
                return 'Early Exit: escalation is only used for unresolved material or critical conflicts.';
            }

            return 'Early exit: bounded Round 1 review left material_or_higher_conflict_count = ' . $materialConflictCount . ', so no Round 2 was needed.';
        }

        if ($earlyExitReason === 'no_new_evidence') {
            return 'Early exit: no new evidence was introduced, so another structured round would not change the decision package.';
        }

        return 'Early exit: material_or_higher_conflict_count = ' . $materialConflictCount;
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

    private function valueByPath(array $input, string $path)
    {
        $segments = explode('.', $path);
        $current = $input;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
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
