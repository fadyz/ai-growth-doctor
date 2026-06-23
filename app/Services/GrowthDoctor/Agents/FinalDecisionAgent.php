<?php

namespace App\Services\GrowthDoctor\Agents;

class FinalDecisionAgent
{
    private $client;

    public function __construct(AiAgentClient $client)
    {
        $this->client = $client;
    }

    public function run(array $context, array $requestMeta = []): array
    {
        $language = $this->client->outputLanguage();
        $isRepair = !empty($requestMeta['json_repair_attempt']);

        return $this->client->call(
            'AI Final Decision Agent',
            $isRepair ? $this->repairPrompt($language) : $this->systemPrompt($language),
            $this->coreExpectedSchema(),
            $isRepair ? $this->repairContext($context, $requestMeta) : $this->compactContext($context),
            array_merge($requestMeta, ['skip_generic_output_fields' => true])
        );
    }

    private function systemPrompt(string $language): string
    {
        return 'You are the final coordinator for AI Growth Doctor, a multi-agent business doctor for a mobile calorie tracking app. You receive compact evidence only. Make one practical operating decision for today. Return ONLY valid JSON in ' . $language . '. Do not include markdown, code fences, or prose outside JSON. Returning {}, [], or an empty object is forbidden.

Use this evidence order: guardrail_policy, specialist_summaries, structured_negotiation, forecast risk/evaluation/calibration, baseline_comparison, core_metrics.

Hard rules:
- Respect guardrail_policy.deterministic_business_verdict, blocked_decision, allowed_decision, blocked_actions, and allowed_actions.
- If guardrail_policy.winning_guardrail is null or triggered_guardrails_count is 0, do not frame the decision as a guardrail veto. Use normal operating caution language.
- Forecast risk flags are caution signals only, not deterministic guardrails, unless guardrail_policy explicitly says so.
- Do not claim statistical significance unless compact evidence explicitly reports a statistical test.
- If evidence is weak, say so and choose a conservative HOLD_AND_OPTIMIZE posture.
- Decide ads, release, product, and monetization separately inside operating_decision.
- Keep strings concise. accepted_recommendations max 4. rejected_recommendations max 3. resolved_conflicts max 3. prioritized_actions max 4. weak_evidence_or_uncertainty max 4.
- Fill every required field. If uncertain, use HOLD_AND_OPTIMIZE and RUN_GUARDED_OPTIMIZATION.

Return a small core decision only. Do not add dashboard enrichment fields such as growth_health_score, business_impact_estimate, agent_debate_trace, operational_action_plan, objective_evaluation_plan, decision_risk_assessment, or competition_pitch.';
    }

    private function repairPrompt(string $language): string
    {
        return 'You are repairing a failed Final Decision Agent response. Return ONLY valid non-empty JSON in ' . $language . '. Returning {}, [], null, markdown, or prose outside JSON is forbidden.

Use only the required field list, compact core schema, and invalid raw response metadata provided. If the previous response was empty or unusable, create a conservative HOLD_AND_OPTIMIZE decision with RUN_GUARDED_OPTIMIZATION. Keep arrays short and fill every required field.';
    }

    private function coreExpectedSchema(): array
    {
        return [
            'business_verdict' => 'HOLD_AND_OPTIMIZE | CONTINUE_MONITORING | SCALE_CAREFULLY | ROLLBACK_RISK',
            'agent_society_operating_verdict' => 'RUN_GUARDED_OPTIMIZATION | CONTINUE_MONITORING_WITH_BOUNDED_EXPERIMENTS | HOLD_FOR_SAFETY | SCALE_WITH_LIMITS',
            'operating_decision_summary' => 'one short summary of today operating posture',
            'today_operator_summary' => 'one sentence: what to do today and what not to scale yet',
            'main_diagnosis' => 'main business diagnosis',
            'top_priority' => 'single most important priority',
            'accepted_recommendations' => [
                [
                    'source' => 'agent or policy source',
                    'recommendation' => 'accepted recommendation',
                    'why_accepted' => 'short reason',
                ],
            ],
            'rejected_recommendations' => [
                [
                    'source' => 'agent source',
                    'recommendation' => 'rejected or deferred recommendation',
                    'why_rejected' => 'short reason',
                ],
            ],
            'resolved_conflicts' => [
                [
                    'conflict_id' => 'id if available',
                    'resolution' => 'short resolution',
                    'accepted_side' => 'winning position',
                    'rejected_side' => 'deferred position',
                    'guardrail_consistency' => 'how policy limits were respected',
                ],
            ],
            'operating_decision' => [
                'ads_decision' => [
                    'decision' => 'hold_budget | increase_budget | reduce_budget | pause | not_enough_data',
                    'reason' => 'short reason',
                    'next_action' => 'specific next action',
                ],
                'release_decision' => [
                    'decision' => 'continue_rollout | continue_with_monitoring | hold_rollout | rollback | not_enough_data',
                    'reason' => 'short reason',
                    'next_action' => 'specific next action',
                ],
                'product_decision' => [
                    'decision' => 'prioritize_activation | prioritize_retention | prioritize_monetization | prioritize_release_quality | not_enough_data',
                    'reason' => 'short reason',
                    'next_action' => 'specific next action',
                    'success_metric' => 'metric name',
                ],
                'monetization_decision' => [
                    'decision' => 'keep_current | segment_only | reduce_pressure | increase_paywall_pressure | not_enough_data',
                    'reason' => 'short reason',
                    'next_action' => 'specific next action',
                ],
            ],
            'prioritized_actions' => [
                [
                    'priority' => 1,
                    'action' => 'action text',
                    'owner_area' => 'product | marketing | ads | release | monetization | data',
                    'expected_impact' => 'high | medium | low',
                    'why' => 'short reason',
                    'success_metric' => 'metric to evaluate',
                ],
            ],
            'confidence_score' => '0-100 integer',
            'weak_evidence_or_uncertainty' => ['uncertainty'],
            'rationale' => 'short rationale for verdict and conflict resolution',
        ];
    }

    private function compactContext(array $context): array
    {
        $compact = $context['final_decision_compact_context'] ?? $context;

        return [
            'final_decision_compact_context' => $compact,
            'final_decision_context_mode' => $context['final_decision_context_mode'] ?? 'compact',
            'compact_context_payload_bytes' => $context['compact_context_payload_bytes'] ?? null,
        ];
    }

    private function repairContext(array $context, array $requestMeta): array
    {
        $raw = $requestMeta['invalid_raw_response'] ?? null;
        if (is_string($raw) && strlen($raw) > 2000) {
            $raw = substr($raw, 0, 2000) . '...';
        }

        return [
            'repair_task' => 'Previous Final Decision response failed JSON/schema validation. Produce a non-empty core final decision JSON.',
            'invalid_response' => [
                'status' => $requestMeta['invalid_status'] ?? null,
                'response_status' => $requestMeta['invalid_response_status'] ?? null,
                'raw_response' => $raw,
                'raw_response_type' => $requestMeta['invalid_raw_response_type'] ?? null,
                'missing_required_fields' => $requestMeta['missing_required_fields'] ?? [],
            ],
            'required_fields' => [
                'business_verdict',
                'today_operator_summary',
                'main_diagnosis',
                'action_plan_or_prioritized_actions',
                'operating_decision_or_agent_society_operating_verdict',
            ],
            'safe_default' => [
                'business_verdict' => 'HOLD_AND_OPTIMIZE',
                'agent_society_operating_verdict' => 'RUN_GUARDED_OPTIMIZATION',
                'today_operator_summary' => 'Hold unsafe scaling and run guarded optimization until stronger evidence is available.',
                'main_diagnosis' => 'The previous response was empty or incomplete, so use conservative guardrail-aligned optimization.',
            ],
            'compact_core_schema' => $this->coreExpectedSchema(),
        ];
    }
}
