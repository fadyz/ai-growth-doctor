<?php

namespace App\Services\GrowthDoctor\Agents;

use App\Services\GrowthDoctor\Agents\AiAgentClient;

class FinalDecisionAgent
{
    private $client;

    public function __construct(AiAgentClient $client)
    {
        $this->client = $client;
    }

    public function run(array $context, array $requestMeta = []): array
    {
        $context = $this->compactContextForFinalDecision($context);
        $language = $this->client->outputLanguage();

        return $this->client->call(
            'AI Final Decision Agent',
            'You are the final coordinator in an AI multi-agent business doctor system for a mobile calorie tracking app. You are receiving compact evidence, not raw logs. Do not ask for omitted raw data. Use final_decision_compact_context, compact metrics, specialist summaries, guardrail policy, structured negotiation summary, forecast calibration, and baseline comparison to produce the final operating decision. Do not claim statistical significance unless the compact evidence explicitly says a statistical test was performed. Act like an executive Growth War Room lead. Read the compact metric context, deterministic guardrail policy, specialist AI summaries, structured negotiation summary, conflict/tension summary, forecast evaluation results, and forecast calibration memory. Resolve material and critical conflicts from compact evidence, identify consensus, challenge weak evidence, estimate business impact, create measurable scores, prioritize business impact, and produce one final practical operating decision. Do not blindly average the agents. The structured negotiation layer is not the final decision owner; it is an adaptive bounded evidence package with up to three structured rounds and early exit. It may include resolved_material_tension rows: these are material to the operating decision, but already bounded in Round 1 and must not be treated as unresolved conflicts. You must explain which specialist or negotiation recommendations were accepted or rejected and why. Respect deterministic guardrail blocked_actions and blocked_decision; do not override them unless data_quality_guardrail is triggered or policy inputs are objectively missing. If final_decision_compact_context.guardrail_policy is available, first read guardrail_policy.triggered_guardrails_count and guardrail_policy.winning_guardrail as the source of truth for deterministic guardrail status. If triggered_guardrails_count is 0 and winning_guardrail is null, the guardrail policy is clear: do not say that guardrail wins, blocks, overrides, dominates, or vetoes the final decision. In that clear-guardrail case, use guardrail_policy.deterministic_business_verdict only as compatible operating guidance, not as a violation or override. If winning_guardrail is non-null, treat guardrail_policy as the primary deterministic operating decision basis. Use the specialist summaries and structured negotiation to explain, challenge, and contextualize the policy result, but do not override guardrail_policy.blocked_actions or blocked_decision unless the data_quality_guardrail is triggered or the policy is clearly missing required inputs. If samples are weak, say so clearly. If forecast.evaluation is available, use it to judge whether the previous forecast/decision was accurate. If forecast.calibration is available, use it only to weight forecast evidence, not to replace the business decision. The final output must remain a business operating decision such as hold budget, scale carefully, prioritize activation, segment monetization, shift attention to reset campaign, or continue rollout. If business_verdict remains CONTINUE_MONITORING, still set agent_society_operating_verdict and operating_decision_summary to an active posture such as CONTINUE_MONITORING_WITH_BOUNDED_EXPERIMENTS or RUN_GUARDED_OPTIMIZATION when resolved material tensions justify guarded experiments. However, if metrics_pending_maturity is greater than 0 or metric quality is pending_maturity, do not treat those cohort-lagged metrics as forecast misses. D1 requires forecast_for_date + 1 day actual data, while habit_7d_rate and avg_log_days_7d require forecast_for_date + 6 days actual data. Separate mature forecast accuracy from pending cohort-lagged metrics. For version evidence, use compact version top_versions, release_candidate_summary, and legacy_version_risk_summary; do not overinterpret legacy or instrumentation-incompatible versions as current rollout veto evidence. Separate facts, hypotheses, uncertainty, and final decision. The most important output is an operating_decision panel that tells the business what to do today across ads, release, product, monetization, and campaigns. Also expose the collaboration process: create an agent_debate_trace with at least 6 debate steps when ai_tomorrow_forecast_agent is present in specialist_summaries, showing a realistic multi-agent disagreement and resolution: Activation Agent argues from activation evidence, Retention Agent may caution against aggressive scale if D1/habit is weak, and may only veto scale when a deterministic guardrail is actually triggered, Monetization Agent argues revenue upside or paywall risk, Version Agent argues rollout/release safety using compact version evidence, Ads Agent argues from acquisition cost/campaign lifecycle evidence, Guardrail Policy Engine provides deterministic blocked/allowed actions and winning guardrail, Structured Negotiation contributes adaptive summary and key tensions, Tomorrow Forecast Agent argues from quantitative next-day forecast evidence and may raise or relax forecast-based risk cautions, and Final Decision Agent resolves the conflict into one operating decision without violating deterministic blocked actions; when winning_guardrail is null, this must be presented as normal operating resolution, not a guardrail victory. The recommended evidence order is Activation Agent -> Retention Agent -> Monetization Agent -> Version Agent -> Ads Agent -> Guardrail Policy Engine -> Structured Negotiation -> Tomorrow Forecast Agent -> Final Decision Agent. If ai_tomorrow_forecast_agent exists, include Tomorrow Forecast Agent as its own evidence signal. If ai_ads_agent exists, include Ads Agent as its own evidence signal. If structured_negotiation exists, include unresolved material/critical conflicts, resolved material tensions, bounded tensions, and revised recommendations as reviewable evidence. Do not present the evidence as a strict chronological process. Tomorrow Forecast Agent evidence must reference forecast_for_date, predicted D1/habit/activation risk, and forecast risk flags from compact forecast evidence. Do not call Tomorrow Forecast Agent risk flags Guardrail Policy Engine guardrails. Forecast fields such as risk_flags, deprecated guardrails, scaling_caution, or old scaling_guardrail are forecast caution signals only, not deterministic guardrail_policy triggers, not direct contact with Guardrail Policy Engine, and not vetoes unless guardrail_policy.winning_guardrail is non-null. Ads Agent evidence must reference ads_verdict, campaign_health, budget_decision, campaign lifecycle interpretation, guardrails, and impact_on_final_decision when present in compact evidence. For Ads Agent, lifecycle context wins only campaign identity and interpretation, ads metrics win budget intensity, and downstream activation/retention/guardrails win safety limits. If Ads Agent says Volume Stabil is degraded_legacy and Volume Install Reset is reset_successor, do not interpret reducing or pausing Volume Stabil as shutting down acquisition; interpret it as a campaign recovery/reset strategy. Do not treat reset_successor lifecycle as proof that the reset campaign is performing well; use ads compact metrics for CPI, conversion rate, conversion volume, spend, sample quality, and budget intensity. Ads evidence may allow a small controlled test only when downstream activation/retention guardrails are safe; ads evidence must not override weak retention by itself. Guardrail Policy Engine evidence must reference winning_guardrail, triggered_guardrails_count, deterministic_business_verdict, blocked_decision, allowed_decision, blocked_actions, and allowed_actions. If winning_guardrail is null and triggered_guardrails_count is 0, describe the Guardrail Policy Engine view as no deterministic guardrail violation / guardrail clear; blocked_actions are narrow forbidden actions only and must not be interpreted as the whole decision being blocked. If guardrail_policy exists, the conflict_resolution_rule must be consistent with guardrail_policy.winning_guardrail. If guardrail_policy.winning_guardrail is null, conflict_resolution_rule.winning_guardrail must be null or "none", conflict_resolution_rule.resolution_type must be "normal_operating_resolution", rule_triggered must say no deterministic guardrail triggered, why_veto_won must say no veto won, and final_resolution must not be framed as guardrail-led. In this case do not use the word veto for specialist cautions; say "retention caution", "ads caution", "forecast caution", or "business caution" instead of "retention veto", "ads veto", or "forecast veto". Include Forecast Evaluation as decision evidence when available: mention latest_quality, pending_count, evaluated_count, and whether mature parts of the previous forecast support or weaken today\'s forecast-based risk caution. If many important retention metrics are pending_maturity, say evaluation is partial instead of calling the forecast wrong. Include Forecast Calibration as weighting evidence when available: mention trust_score, trust_interpretation, forecast_role, and systematic_bias_detected, but describe calibration as forecast confidence weighting, not as a deterministic guardrail trigger. Do not make the final decision about whether to trust the forecast; use calibration only to decide how much weight forecast evidence receives. Return valid JSON only in ' . $language . '. The response must be a JSON object and must not contain markdown, prose outside JSON, or code fences.',
            [
                'business_verdict' => 'CONTINUE_MONITORING | HOLD_AND_OPTIMIZE | SCALE_CAREFULLY | ROLLBACK_RISK',
                'deterministic_guardrail_verdict' => 'guardrail_policy.deterministic_decision.business_verdict if available, e.g. CONTINUE_MONITORING',
                'agent_society_operating_verdict' => 'e.g. CONTINUE_MONITORING_WITH_BOUNDED_EXPERIMENTS',
                'operating_decision_summary' => 'e.g. RUN_GUARDED_OPTIMIZATION',
                'verdict_delta_explanation' => 'explain how resolved material tensions change the operating posture without contradicting deterministic guardrails',
                'top_priority' => 'single most important priority before scaling or changing monetization/release posture',
                'accepted_recommendations' => [
                    [
                        'source' => 'Activation Agent | Retention Agent | Monetization Agent | Version Agent | Ads Agent | Tomorrow Forecast Agent | Structured Negotiation | Guardrail Policy Engine',
                        'recommendation' => 'accepted recommendation',
                        'why_accepted' => 'evidence and conflict resolution reason'
                    ]
                ],
                'rejected_recommendations' => [
                    [
                        'source' => 'Activation Agent | Retention Agent | Monetization Agent | Version Agent | Ads Agent | Tomorrow Forecast Agent | Structured Negotiation',
                        'recommendation' => 'rejected or deferred recommendation',
                        'why_rejected' => 'guardrail, conflict matrix, missing evidence, or weaker impact reason'
                    ]
                ],
                'resolved_conflicts' => [
                    [
                        'conflict_id' => 'conflict id from conflict_matrix if available',
                        'resolution' => 'how the final decision resolves this conflict',
                        'accepted_side' => 'which position wins',
                        'rejected_side' => 'which position is rejected or deferred',
                        'guardrail_consistency' => 'how deterministic blocked actions were respected'
                    ]
                ],
                'action_plan_24_72h' => ['human-reviewable action for the next 24-72 hours'],
                'human_approval_required' => true,
                'rationale' => 'short explanation of the final verdict, accepted/rejected recommendations, and conflict resolution',
                'confidence_score' => '0-100 integer; confidence in final verdict',
                'business_status' => 'short status',
                'operating_decision' => [
                    'ads_decision' => [
                        'decision' => 'increase_budget | hold_budget | reduce_budget | pause | not_enough_data',
                        'label' => 'short human label for dashboard, e.g. Hold Budget',
                        'confidence_score' => '0-100 integer',
                        'reason' => 'why this ads decision is recommended',
                        'next_action' => 'specific next action for ads today',
                        'guardrail_metric' => 'metric that must be monitored before changing ads budget'
                    ],
                    'release_decision' => [
                        'decision' => 'continue_rollout | continue_with_monitoring | hold_rollout | rollback | not_enough_data',
                        'label' => 'short human label for dashboard',
                        'confidence_score' => '0-100 integer',
                        'reason' => 'why this release decision is recommended',
                        'next_action' => 'specific next action for release today',
                        'guardrail_metric' => 'metric that must be monitored for release risk'
                    ],
                    'product_decision' => [
                        'decision' => 'prioritize_activation | prioritize_retention | prioritize_monetization | prioritize_release_quality | not_enough_data',
                        'label' => 'short human label for dashboard',
                        'confidence_score' => '0-100 integer',
                        'reason' => 'why this product priority is recommended',
                        'next_action' => 'specific product action to do next',
                        'success_metric' => 'metric to judge if product action worked'
                    ],
                    'monetization_decision' => [
                        'decision' => 'increase_paywall_pressure | keep_current | reduce_pressure | segment_only | not_enough_data',
                        'label' => 'short human label for dashboard',
                        'confidence_score' => '0-100 integer',
                        'reason' => 'why this monetization decision is recommended',
                        'next_action' => 'specific monetization action to do next',
                        'guardrail_metric' => 'metric to ensure monetization does not hurt activation/retention'
                    ],
                    'campaign_decision' => [
                        'decision' => 'scale_validated_campaigns | continue_holdout | pause_weak_campaigns | launch_small_test | not_enough_data',
                        'label' => 'short human label for dashboard',
                        'confidence_score' => '0-100 integer',
                        'reason' => 'why this campaign decision is recommended',
                        'next_action' => 'specific push/CRM/campaign action to do next',
                        'guardrail_metric' => 'metric that must be monitored for campaign quality'
                    ],
                ],
                'today_operator_summary' => 'one sentence: what the operator should do today and what should not be scaled yet',
                'growth_health_score' => [
                    'overall_score' => '0-100 integer; overall growth health score',
                    'activation_score' => '0-100 integer',
                    'retention_score' => '0-100 integer',
                    'monetization_score' => '0-100 integer',
                    'release_score' => '0-100 integer',
                    'main_constraint' => 'activation | retention | monetization | release | ads_acquisition | data_quality',
                    'score_explanation' => 'brief explanation of how the score should be interpreted'
                ],
                'business_impact_estimate' => [
                    'main_metric_at_risk' => 'metric name',
                    'growth_blocker' => 'main growth blocker',
                    'revenue_risk' => 'low | medium | high',
                    'efficiency_impact' => 'how this affects team/marketing/product efficiency',
                    'estimated_uplift_if_fixed' => [
                        'assumption' => 'clear assumption used for estimate',
                        'extra_workspace_users_7d' => 'integer or null',
                        'extra_food_add_success_users_7d' => 'integer or null',
                        'extra_paywall_eligible_users_7d' => 'integer or null',
                        'revenue_direction' => 'positive | neutral | negative | unknown'
                    ]
                ],
                'deterministic_guardrail_decision_basis' => [
                    'policy_available' => 'true | false',
                    'policy_version' => 'guardrail_policy.policy_version if available',
                    'policy_type' => 'guardrail_policy.policy_type if available',
                    'winning_guardrail' => 'guardrail_policy.winning_guardrail if available',
                    'business_verdict' => 'guardrail_policy.deterministic_decision.business_verdict if available',
                    'blocked_decision' => 'guardrail_policy.deterministic_decision.blocked_decision if available',
                    'allowed_decision' => 'guardrail_policy.deterministic_decision.allowed_decision if available',
                    'confidence_score' => 'guardrail_policy.deterministic_decision.confidence_score if available',
                    'blocked_actions' => ['blocked action from deterministic policy'],
                    'allowed_actions' => ['allowed action from deterministic policy'],
                    'reason_codes' => ['reason codes from deterministic policy'],
                    'impact_on_final_decision' => 'if winning_guardrail is null, say no deterministic guardrail violation and explain only narrow allowed/blocked operating constraints; if winning_guardrail is non-null, explain how this deterministic policy constrained or shaped the final decision',
                    'override_status' => 'not_overridden | overridden_due_to_data_quality | not_available',
                    'override_reason' => 'if winning_guardrail is null, say no deterministic guardrail needed overriding because none was triggered; if overridden, explain objective reason; otherwise say deterministic blocked actions were respected'
                ],
                'agent_debate_summary' => [
                    'activation_agent_view' => 'short view from activation agent',
                    'retention_agent_view' => 'short view from retention agent',
                    'monetization_agent_view' => 'short view from monetization agent',
                    'version_agent_view' => 'short view from version agent',
                    'ads_agent_view' => 'short view from Ads Agent about acquisition cost, campaign lifecycle, reset campaign, and budget guardrails',
                    'guardrail_policy_view' => 'short view from deterministic Guardrail Policy Engine: if winning_guardrail is null and triggered_guardrails is empty, say guardrail clear/no deterministic violation; otherwise state winning guardrail, blocked decision, allowed decision, and reason codes',
                    'tomorrow_forecast_agent_view' => 'Forecast-based next-day risk view using forecast risk flags/cautions, not deterministic Guardrail Policy Engine triggers, and how it affects today decision',
                    'forecast_calibration_view' => 'How forecast calibration memory changes the weight of forecast evidence without replacing the business decision',
                    'final_resolution' => 'how final agent resolves agent agreement/conflict; if winning_guardrail is null, frame this as normal operating decision, not guardrail victory or guardrail veto'
                ],
                'agent_debate_trace' => [
                    [
                        'step' => 1,
                        'agent' => 'Activation Agent | Retention Agent | Monetization Agent | Version Agent | Ads Agent | Guardrail Policy Engine | Tomorrow Forecast Agent | Forecast Calibration Memory | Final Decision Agent',
                        'dialogue_turn' => 'short dialogue-like sentence. If winning_guardrail is null, do not use veto language; e.g. Retention Agent: I caution against aggressive scaling because D1/habit is still weak. Use veto language only when a deterministic guardrail is triggered.',
                        'position' => 'what this agent argues based on its metrics',
                        'evidence' => 'specific metric or signal used by this agent; for Version Agent use compact_version_context.relevant_versions, release_candidate_versions, legacy_context_summary, and release_guardrail_relevance_rule instead of raw long version lists; for Ads Agent include ads_verdict, deterministic_lifecycle_context, ads_metric_independent_assessment, field_resolution_rule, campaign_health, budget_decision, campaign_lifecycle_interpretation, guardrails, and reset campaign context; for Guardrail Policy Engine include policy_version, winning_guardrail, triggered_guardrails, blocked_actions, allowed_actions, and reason_codes; for Tomorrow Forecast Agent include forecast_for_date, predicted D1/habit/activation risk, risk_flags, and scaling_caution; for forecast evaluation evidence include forecast_quality, hit_rate, metrics_pending_maturity, maturity_interpretation, and main_misses; for Forecast Calibration Memory include trust_score, overall_mature_hit_rate, forecast_role, guardrail_adjustment as forecast confidence weighting, and bias_detection',
                        'objection_or_veto' => 'whether this agent challenges another agent; if winning_guardrail is null, call it caution/objection not veto; null if none',
                        'vote' => 'scale | hold | reduce | continue | rollback | investigate | caution_against_scale | veto_scale | deterministic_policy_blocks_action | deterministic_policy_allows_action | shift_to_reset_campaign | ads_allows_cautious_test | ads_blocks_scaling | forecast_cautions_against_scaling | forecast_supports_cautious_test; use veto_scale only when deterministic guardrail is triggered',
                        'impact_on_final_decision' => 'how this changed or influenced the final decision'
                    ],
                    [
                        'step' => 2,
                        'agent' => 'another agent',
                        'dialogue_turn' => 'another short dialogue-like sentence',
                        'position' => 'another agent position',
                        'evidence' => 'specific metric or signal',
                        'objection_or_veto' => 'objection/veto if any',
                        'vote' => 'scale | hold | reduce | continue | rollback | investigate | caution_against_scale | veto_scale; use veto_scale only when deterministic guardrail is triggered',
                        'impact_on_final_decision' => 'how this changed or influenced the final decision'
                    ]
                ],
                'tomorrow_forecast_decision_impact' => [
                    'forecast_agent_present' => 'true | false',
                    'forecast_for_date' => 'date from ai_tomorrow_forecast_agent.result.forecast_for_date if present',
                    'scaling_caution' => 'scaling_caution from ai_tomorrow_forecast_agent.result.risk_flags or guardrail_assessment if present; this is a forecast caution, not deterministic guardrail_policy',
                    'main_forecast_risk' => 'main predicted risk from Tomorrow Forecast Agent',
                    'impact_on_today_decision' => 'how the forecast risk flags changed, strengthened, or softened today operating decision without being treated as deterministic guardrail triggers'
                ],
                'ads_decision_impact' => [
                    'ads_agent_present' => 'true | false',
                    'ads_verdict' => 'ads_verdict from ai_ads_agent.result if present',
                    'campaign_health' => 'campaign_health from ai_ads_agent.result if present',
                    'budget_decision' => 'budget_decision.decision from ai_ads_agent.result if present',
                    'legacy_campaign_interpretation' => 'how Final Decision interprets Volume Stabil if degraded_legacy is present',
                    'reset_campaign_interpretation' => 'how Final Decision interprets Volume Install Reset if reset_successor is present',
                    'ads_supply_vs_product_quality' => 'how ads supply evidence is combined with activation/retention quality',
                    'impact_on_today_decision' => 'how Ads Agent changed, strengthened, or softened the ads/budget operating decision today'
                ],
                'forecast_evaluation_decision_impact' => [
                    'evaluation_available' => 'true | false',
                    'actual_data_available_until' => 'date from evaluations.forecast_evaluations.actual_data_available_until if present',
                    'latest_forecast_for_date' => 'latest evaluated forecast_for_date if present',
                    'latest_forecast_quality' => 'good | partially_correct | poor | no_comparable_metrics | not_available',
                    'latest_hit_rate' => 'numeric hit rate or null',
                    'metrics_pending_maturity' => 'integer count of metrics not yet fair to evaluate because cohort data has not matured',
                    'maturity_interpretation' => 'explain whether evaluation is complete or partial due to D1/7D maturity lag',
                    'main_misses' => ['short summary of main forecast misses if any'],
                    'impact_on_today_decision' => 'how mature evaluation result changes trust in forecast risk cautions and operating decision today; do not punish forecast for pending_maturity metrics'
                ],
                'forecast_calibration_decision_impact' => [
                    'calibration_available' => 'true | false',
                    'evaluations_used' => 'integer count from evaluations.forecast_model_calibration.evaluations_used if present',
                    'trust_score' => 'numeric updated trust score or null',
                    'trust_interpretation' => 'high_trust | medium_high_trust | medium_trust | low_trust | not_available',
                    'overall_mature_hit_rate' => 'numeric mature hit rate or null',
                    'forecast_role' => 'can_strengthen_forecast_caution | supporting_forecast_caution | directional_signal_only | not_available',
                    'guardrail_adjustment' => 'forecast_can_strengthen_caution | supporting_caution_only | do_not_use_as_primary_veto | use_directionally_only | not_available; field name kept for compatibility but meaning is forecast confidence weighting, not deterministic guardrail trigger',
                    'bias_summary' => 'short summary of systematic bias detection and weak metrics',
                    'impact_on_today_decision' => 'how calibration changes the weight of forecast evidence in today business decision; must not replace actual mature metrics or specialist evidence'
                ],
                'conflict_resolution_rule' => [
                    'winning_guardrail' => 'must match guardrail_policy.winning_guardrail when available; use null or none when guardrail_policy.winning_guardrail is null; otherwise activation_guardrail | retention_guardrail | monetization_guardrail | release_guardrail | ads_acquisition_guardrail | data_quality_guardrail. Do not invent forecast_guardrail, forecast_evaluation_guardrail, or forecast_calibration_guardrail unless GuardrailPolicyEngine actually outputs that exact winning_guardrail.',
                    'resolution_type' => 'normal_operating_resolution when winning_guardrail is null; deterministic_guardrail_resolution only when winning_guardrail is non-null',
                    'rule_triggered' => 'deterministic policy reason code or threshold that was triggered; if winning_guardrail is null, say no deterministic guardrail triggered and do not mention specialist cautions as triggered guardrail rules',
                    'blocked_decision' => 'must match or be consistent with guardrail_policy.deterministic_decision.blocked_decision when available; if winning_guardrail is null and blocked_decision is none, do not imply the final decision is blocked',
                    'allowed_decision' => 'must match or be consistent with guardrail_policy.deterministic_decision.allowed_decision when available; if winning_guardrail is null, interpret allowed_decision as operating guidance, not guardrail victory',
                    'why_veto_won' => 'if winning_guardrail is null, say no veto won because no deterministic guardrail was triggered; do not say retention veto, ads veto, or forecast veto in this case. Otherwise explain why this deterministic guardrail priority wins over competing agent recommendations',
                    'objective_thresholds_used' => [
                        'threshold or reason_code 1 from guardrail_policy',
                        'threshold or reason_code 2 from guardrail_policy'
                    ],
                    'policy_consistency_check' => 'guardrail_clear_consistent_with_policy | consistent_with_guardrail_policy | no_guardrail_policy_available | overridden_due_to_data_quality'
                ],
                'operational_action_plan' => [
                    [
                        'action' => 'specific experiment/action name',
                        'target_user_segment' => 'who exactly receives this action',
                        'trigger_condition' => 'when this action should trigger',
                        'success_metric' => 'primary success metric',
                        'stop_loss_metric' => 'metric that stops the action if it worsens',
                        'expected_lift' => 'numeric or directional expected lift',
                        'experiment_duration' => 'duration such as 7 days',
                        'minimum_sample_size' => 'minimum sample before judging',
                        'rollback_condition' => 'when to stop/rollback this action',
                        'owner_area' => 'product | marketing | ads | release | monetization | data'
                    ]
                ],
                'action_plan' => [
                    'mode' => 'dry_run_only',
                    'requires_human_approval' => true,
                    'tool_call_ready' => true,
                    'proposed_tools' => [
                        [
                            'tool' => 'schedule_push_campaign | update_ads_budget | hold_release_rollout | create_product_experiment | update_paywall_rule',
                            'action' => 'short action name',
                            'target_segment' => 'target users or scope',
                            'payload_summary' => 'brief payload that would be sent to the tool/API',
                            'expected_business_impact' => 'high | medium | low',
                            'safety_guardrail' => 'metric or rule that prevents unsafe execution',
                            'approval_question' => 'human approval question before executing this action',
                            'execution_status' => 'not_executed_dry_run_only'
                        ]
                    ]
                ],
                'main_diagnosis' => 'main diagnosis in ' . $language,
                'executive_summary' => 'one short executive summary suitable for dashboard headline',
                'agent_consensus' => 'what the specialist agents agree on',
                'agent_conflicts' => ['conflict 1 if any', 'conflict 2 if any'],
                'weak_evidence_or_uncertainty' => ['uncertainty 1', 'uncertainty 2'],
                'root_cause_hypothesis' => ['hypothesis 1', 'hypothesis 2'],
                'prioritized_actions' => [
                    [
                        'priority' => 1,
                        'action' => 'action text',
                        'owner_area' => 'product | marketing | ads | release | monetization | data',
                        'expected_impact' => 'high | medium | low',
                        'why' => 'short reason this action is prioritized',
                        'success_metric' => 'objective metric to evaluate this action',
                        'success_target' => 'numeric or directional target'
                    ],
                    [
                        'priority' => 2,
                        'action' => 'action text',
                        'owner_area' => 'product | marketing | ads | release | monetization | data',
                        'expected_impact' => 'high | medium | low',
                        'why' => 'short reason this action is prioritized',
                        'success_metric' => 'objective metric to evaluate this action',
                        'success_target' => 'numeric or directional target'
                    ]
                ],
                'recommended_actions' => ['action 1', 'action 2', 'action 3'],
                'risk_notes' => ['risk 1', 'risk 2'],
                'next_24h_monitoring_plan' => ['metric to check 1', 'metric to check 2'],
                'next_7d_learning_plan' => ['learning item 1', 'learning item 2'],
                'objective_evaluation_plan' => [
                    'primary_metric' => 'main metric to evaluate after actions',
                    'secondary_metrics' => ['metric 1', 'metric 2'],
                    'decision_rule' => 'objective rule for continue/hold/rollback after next checkpoint',
                    'next_checkpoint_window' => '24h | 3d | 7d',
                    'minimum_sample_needed' => 'sample size or condition needed before changing the decision'
                ],
                'previous_decision_evaluation' => [
                    'available' => 'true | false',
                    'previous_decision' => 'summary of previous decision if available',
                    'expected_outcome' => 'what yesterday decision expected to improve',
                    'actual_outcome' => 'what actually happened in the current checkpoint',
                    'decision_quality' => 'correct | partially_correct | wrong | not_enough_data; use forecast_evaluations when available, but treat pending_maturity as not_enough_data for that metric',
                    'lesson' => 'what the system learned from previous decision outcome and forecast calibration memory; keep the lesson business-action oriented, not just trust/distrust forecast'
                ],
                'decision_risk_assessment' => [
                    'decision' => 'main operating decision such as hold_budget | scale_carefully | prioritize_activation | segment_only | continue_monitoring',
                    'primary_reason' => 'plain-language reason why the decision was taken',
                    'evidence_summary' => [
                        'signals_supporting_decision' => 'integer',
                        'signals_against_decision' => 'integer',
                        'signals_inconclusive' => 'integer',
                        'short_explanation' => 'summary of the evidence balance',
                        'deterministic_policy_used' => 'true | false',
                        'deterministic_policy_summary' => 'if winning_guardrail is null, summarize as guardrail clear/no deterministic violation plus narrow blocked/allowed actions; otherwise summarize winning guardrail and blocked/allowed action if guardrail_policy is available'
                    ],
                    'confidence_score' => '0-100 integer',
                    'if_wrong' => [
                        'risk_type' => 'missed_upside | wasted_spend | activation_damage | release_regression | not_enough_data',
                        'estimated_7d_impact' => 'currency range if enough data exists, otherwise not_enough_data',
                        'impact_explanation' => 'what business downside happens if this decision is wrong',
                        'missing_inputs' => ['missing input such as CPI, budget_delta, ARPPU, purchase_rate if currency estimate is not enough data']
                    ],
                    'reverse_condition' => [
                        'condition' => 'objective condition that would reverse or soften this decision',
                        'next_decision' => 'what decision should be considered when condition is met'
                    ],
                    'forecast_role' => [
                        'role' => 'primary_forecast_caution | supporting_forecast_caution | directional_signal_only | ignored_due_to_low_trust',
                        'why' => 'how forecast evaluation/calibration and Ads Agent evidence influenced but did not replace the final business decision; forecast cautions are not deterministic Guardrail Policy Engine triggers'
                    ]
                ],
                'competition_pitch' => '1-2 sentence explanation of why this multi-agent system is useful for business decision making',
            ],
            $context,
            $requestMeta
        );
    }
    private function compactContextForFinalDecision(array $context): array
    {
        if (isset($context['metrics_context']) && is_array($context['metrics_context'])) {
            $context['metrics_context'] = $this->compactMetricsContext($context['metrics_context']);
        }

        if (isset($context['specialist_agents']) && is_array($context['specialist_agents'])) {
            $context['specialist_agents'] = $this->compactSpecialistAgents($context['specialist_agents']);
        }

        if (isset($context['evaluations']) && is_array($context['evaluations'])) {
            $context['evaluations'] = $this->compactEvaluations($context['evaluations']);
        }

        if (isset($context['forecast_model_calibration']) && is_array($context['forecast_model_calibration'])) {
            $context['forecast_model_calibration'] = $this->compactForecastCalibration($context['forecast_model_calibration']);
        }

        if (isset($context['structured_negotiation']) && is_array($context['structured_negotiation'])) {
            $context['structured_negotiation'] = $this->compactStructuredNegotiation($context['structured_negotiation']);
        }

        if (isset($context['orchestrator_evidence_assembly']) && is_array($context['orchestrator_evidence_assembly'])) {
            $context['orchestrator_evidence_assembly'] = $this->compactOrchestratorEvidenceAssembly($context['orchestrator_evidence_assembly']);
        }

        if (isset($context['metrics_context']['version_metrics']) && is_array($context['metrics_context']['version_metrics'])) {
            $context['metrics_context']['version_metrics'] = $this->compactVersionMetrics($context['metrics_context']['version_metrics']);
        }

        if (isset($context['metrics']['version_metrics']) && is_array($context['metrics']['version_metrics'])) {
            $context['metrics']['version_metrics'] = $this->compactVersionMetrics($context['metrics']['version_metrics']);
        }

        return $context;
    }

    private function compactStructuredNegotiation(array $negotiation): array
    {
        return [
            'round' => $negotiation['round'] ?? null,
            'negotiation_type' => $negotiation['negotiation_type'] ?? null,
            'execution' => $negotiation['execution'] ?? [],
            'ui_summary' => $negotiation['ui_summary'] ?? [],
            'rules' => $negotiation['rules'] ?? [],
            'agent_responses' => array_slice($negotiation['agent_responses'] ?? [], 0, 8),
            'negotiation_timeline' => array_slice($negotiation['negotiation_timeline'] ?? [], 0, 8),
            'round_summaries' => array_slice($negotiation['round_summaries'] ?? [], 0, 3),
            'negotiation_transcript' => array_slice($negotiation['negotiation_transcript'] ?? [], 0, 10),
            'revised_recommendations' => array_slice($negotiation['revised_recommendations'] ?? [], 0, 5),
            'conflicts' => array_slice($negotiation['conflict_matrix'] ?? ($negotiation['conflicts'] ?? []), 0, 6),
            'conflict_matrix' => array_slice($negotiation['conflict_matrix'] ?? ($negotiation['conflicts'] ?? []), 0, 6),
            'orchestrator_package' => [
                'rounds_completed' => $negotiation['orchestrator_package']['rounds_completed'] ?? ($negotiation['execution']['rounds_completed'] ?? null),
                'early_exit_reason' => $negotiation['orchestrator_package']['early_exit_reason'] ?? ($negotiation['execution']['early_exit_reason'] ?? null),
                'material_or_higher_conflict_count' => $negotiation['orchestrator_package']['material_or_higher_conflict_count'] ?? ($negotiation['summary']['material_or_higher_conflict_count'] ?? null),
            ],
            'summary' => $negotiation['summary'] ?? [],
            'baseline_comparison' => $negotiation['baseline_comparison'] ?? [],
            'specialist_output_summaries' => array_slice($negotiation['specialist_output_summaries'] ?? [], 0, 8),
        ];
    }

    private function compactOrchestratorEvidenceAssembly(array $assembly): array
    {
        return [
            'guardrail_result' => $assembly['guardrail_result'] ?? [],
            'structured_negotiation' => isset($assembly['structured_negotiation']) && is_array($assembly['structured_negotiation'])
                ? $this->compactStructuredNegotiation($assembly['structured_negotiation'])
                : [],
            'conflict_matrix' => array_slice($assembly['conflict_matrix'] ?? [], 0, 6),
            'negotiation_summary' => $assembly['negotiation_summary'] ?? [],
            'forecast_evaluation' => isset($assembly['forecast_evaluation']) && is_array($assembly['forecast_evaluation'])
                ? $this->compactForecastEvaluationsReference($assembly['forecast_evaluation'])
                : [],
            'calibration_memory' => isset($assembly['calibration_memory']) && is_array($assembly['calibration_memory'])
                ? $this->compactForecastCalibrationReference($assembly['calibration_memory'])
                : [],
            'final_decision_context' => $assembly['final_decision_context'] ?? [],
        ];
    }

    private function compactMetricsContext(array $metricsContext): array
    {
        if (isset($metricsContext['version_metrics']) && is_array($metricsContext['version_metrics'])) {
            $metricsContext['version_metrics'] = $this->compactVersionMetrics($metricsContext['version_metrics']);
        }

        if (isset($metricsContext['forecast_evaluations']) && is_array($metricsContext['forecast_evaluations'])) {
            $metricsContext['forecast_evaluations'] = $this->compactForecastEvaluationsReference($metricsContext['forecast_evaluations']);
        }

        if (isset($metricsContext['forecast_model_calibration']) && is_array($metricsContext['forecast_model_calibration'])) {
            $metricsContext['forecast_model_calibration'] = $this->compactForecastCalibrationReference($metricsContext['forecast_model_calibration']);
        }

        if (isset($metricsContext['evaluations']) && is_array($metricsContext['evaluations'])) {
            $metricsContext['evaluations'] = $this->compactEvaluations($metricsContext['evaluations']);
        }

        if (isset($metricsContext['source_metrics_context']) && is_array($metricsContext['source_metrics_context'])) {
            $metricsContext['source_metrics_context'] = $this->compactSourceMetricsContext($metricsContext['source_metrics_context']);
        }

        return $metricsContext;
    }

    private function compactSourceMetricsContext(array $sourceMetricsContext): array
    {
        return [
            'omitted_from_final_prompt' => true,
            'reason' => 'Raw source metrics duplicate metrics_context and forecast evaluation payloads; use generic_metrics_context and source_metric_refs for final decision evidence.',
            'available_context_keys' => array_keys($sourceMetricsContext),
            'detail_location' => 'checkpoint/audit source_metrics_context',
        ];
    }

    private function compactSpecialistAgents(array $specialistAgents): array
    {
        $compact = [];

        foreach ($specialistAgents as $key => $agentOutput) {
            $compact[$key] = is_array($agentOutput) ? $this->compactAgentOutput($agentOutput) : $agentOutput;
        }

        return $compact;
    }

    private function compactAgentOutput(array $agentOutput): array
    {
        return array_filter([
            'agent' => $agentOutput['agent'] ?? null,
            'status' => $agentOutput['status'] ?? null,
            'model' => $agentOutput['model'] ?? null,
            'result' => $agentOutput['result'] ?? [],
            'error' => $agentOutput['error'] ?? null,
        ], function ($value) {
            return $value !== null && $value !== [];
        });
    }

    private function compactEvaluations(array $evaluations): array
    {
        if (isset($evaluations['forecast_evaluations']) && is_array($evaluations['forecast_evaluations'])) {
            $evaluations['forecast_evaluations'] = $this->compactForecastEvaluations($evaluations['forecast_evaluations']);
        }

        if (isset($evaluations['forecast_model_calibration']) && is_array($evaluations['forecast_model_calibration'])) {
            $evaluations['forecast_model_calibration'] = $this->compactForecastCalibration($evaluations['forecast_model_calibration']);
        }

        return $evaluations;
    }

    private function compactForecastEvaluations(array $forecastEvaluations): array
    {
        $evaluated = $forecastEvaluations['evaluated'] ?? [];
        $evaluated = is_array($evaluated) ? $evaluated : [];
        $latestEvaluation = $this->latestForecastEvaluation($evaluated);

        return [
            'status' => $forecastEvaluations['status'] ?? null,
            'checkpoint_window_end' => $forecastEvaluations['checkpoint_window_end'] ?? null,
            'actual_data_available_until' => $forecastEvaluations['actual_data_available_until'] ?? null,
            'actual_availability_by_group' => $forecastEvaluations['actual_availability_by_group'] ?? [],
            'evaluated_count' => $forecastEvaluations['evaluated_count'] ?? count($evaluated),
            'pending_count' => $forecastEvaluations['pending_count'] ?? count($forecastEvaluations['pending'] ?? []),
            'skipped_count' => $forecastEvaluations['skipped_count'] ?? count($forecastEvaluations['skipped'] ?? []),
            'latest_evaluation' => $latestEvaluation ? $this->compactForecastEvaluation($latestEvaluation, true) : null,
            'recent_evaluation_summaries' => $this->compactRecentEvaluationSummaries($evaluated, 7),
            'pending' => array_slice($forecastEvaluations['pending'] ?? [], 0, 5),
            'skipped' => array_slice($forecastEvaluations['skipped'] ?? [], 0, 5),
        ];
    }

    private function compactForecastEvaluationsReference(array $forecastEvaluations): array
    {
        $evaluated = $forecastEvaluations['evaluated'] ?? [];
        $evaluated = is_array($evaluated) ? $evaluated : [];
        $latestEvaluation = $this->latestForecastEvaluation($evaluated);

        return [
            'status' => $forecastEvaluations['status'] ?? null,
            'actual_data_available_until' => $forecastEvaluations['actual_data_available_until'] ?? null,
            'evaluated_count' => $forecastEvaluations['evaluated_count'] ?? count($evaluated),
            'pending_count' => $forecastEvaluations['pending_count'] ?? count($forecastEvaluations['pending'] ?? []),
            'skipped_count' => $forecastEvaluations['skipped_count'] ?? count($forecastEvaluations['skipped'] ?? []),
            'latest_forecast_for_date' => $latestEvaluation['forecast_for_date'] ?? null,
            'latest_data_as_of_date' => $latestEvaluation['data_as_of_date'] ?? null,
            'latest_summary' => $latestEvaluation['summary'] ?? [],
            'detail_location' => 'evaluations.forecast_evaluations',
        ];
    }

    private function latestForecastEvaluation(array $evaluations): ?array
    {
        $valid = array_values(array_filter($evaluations, function ($evaluation) {
            return is_array($evaluation) && !empty($evaluation['forecast_for_date']);
        }));

        if (empty($valid)) {
            return null;
        }

        usort($valid, function ($a, $b) {
            return strcmp((string) ($b['forecast_for_date'] ?? ''), (string) ($a['forecast_for_date'] ?? ''));
        });

        return $valid[0];
    }

    private function compactRecentEvaluationSummaries(array $evaluations, int $limit): array
    {
        $valid = array_values(array_filter($evaluations, function ($evaluation) {
            return is_array($evaluation) && !empty($evaluation['forecast_for_date']);
        }));

        usort($valid, function ($a, $b) {
            return strcmp((string) ($b['forecast_for_date'] ?? ''), (string) ($a['forecast_for_date'] ?? ''));
        });

        return array_map(function ($evaluation) {
            return $this->compactForecastEvaluation($evaluation, false);
        }, array_slice($valid, 0, $limit));
    }

    private function compactForecastEvaluation(array $evaluation, bool $includeMetricDetails): array
    {
        $compact = [
            'forecast_for_date' => $evaluation['forecast_for_date'] ?? null,
            'data_as_of_date' => $evaluation['data_as_of_date'] ?? null,
            'actual_data_available_until' => $evaluation['actual_data_available_until'] ?? null,
            'actual_availability_by_group' => $evaluation['actual_availability_by_group'] ?? [],
            'summary' => $evaluation['summary'] ?? [],
            'actual_metrics' => $evaluation['actual_metrics'] ?? [],
        ];

        if ($includeMetricDetails) {
            $compact['metric_evaluations'] = $this->compactMetricEvaluations($evaluation['metric_evaluations'] ?? []);
        }

        return $compact;
    }

    private function compactMetricEvaluations(array $metricEvaluations): array
    {
        $compact = [];

        foreach ($metricEvaluations as $group => $metrics) {
            if (!is_array($metrics)) {
                continue;
            }

            foreach ($metrics as $metric => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $compact[$group][$metric] = [
                    'quality' => $row['quality'] ?? null,
                    'status' => $row['status'] ?? null,
                    'actual' => $row['actual'] ?? null,
                    'forecast_low' => $row['forecast_low'] ?? null,
                    'forecast_point' => $row['forecast_point'] ?? null,
                    'forecast_high' => $row['forecast_high'] ?? null,
                    'range_hit' => $row['range_hit'] ?? null,
                    'direction_vs_point' => $row['direction_vs_point'] ?? null,
                    'reason_code' => $row['reason_code'] ?? null,
                    'maturity' => $this->compactMaturity($row['maturity'] ?? []),
                ];
            }
        }

        return $compact;
    }

    private function compactMaturity($maturity): array
    {
        if (!is_array($maturity)) {
            return [];
        }

        return array_filter([
            'status' => $maturity['status'] ?? null,
            'is_mature' => $maturity['is_mature'] ?? null,
            'lag_days' => $maturity['lag_days'] ?? null,
            'required_actual_until' => $maturity['required_actual_until'] ?? null,
            'actual_data_available_until' => $maturity['actual_data_available_until'] ?? null,
            'reason_code' => $maturity['reason_code'] ?? null,
        ], function ($value) {
            return $value !== null;
        });
    }

    private function compactForecastCalibration(array $calibration): array
    {
        return [
            'status' => $calibration['status'] ?? null,
            'learning_window' => $calibration['learning_window'] ?? null,
            'mature_metrics_only' => $calibration['mature_metrics_only'] ?? null,
            'evaluations_used' => $calibration['evaluations_used'] ?? null,
            'overall_mature_hit_rate' => $calibration['overall_mature_hit_rate'] ?? null,
            'mature_metrics_total' => $calibration['mature_metrics_total'] ?? null,
            'mature_metrics_hit' => $calibration['mature_metrics_hit'] ?? null,
            'pending_maturity_total' => $calibration['pending_maturity_total'] ?? null,
            'trust_score' => $calibration['trust_score'] ?? [],
            'latest_evaluation_summary' => $calibration['latest_evaluation_summary'] ?? [],
            'group_accuracy' => $calibration['group_accuracy'] ?? [],
            'bias_detection' => $calibration['bias_detection'] ?? [],
            'confidence_adjustment' => $calibration['confidence_adjustment'] ?? [],
            'decision_instruction' => $calibration['decision_instruction'] ?? [],
            'metric_biases' => array_slice($calibration['metric_biases'] ?? [], 0, 12, true),
            'evaluation_files_used' => array_slice($calibration['evaluation_files_used'] ?? [], 0, 7),
        ];
    }

    private function compactForecastCalibrationReference(array $calibration): array
    {
        return [
            'status' => $calibration['status'] ?? null,
            'evaluations_used' => $calibration['evaluations_used'] ?? null,
            'overall_mature_hit_rate' => $calibration['overall_mature_hit_rate'] ?? null,
            'trust_score' => $calibration['trust_score'] ?? [],
            'latest_evaluation_summary' => $calibration['latest_evaluation_summary'] ?? [],
            'bias_detection' => $calibration['bias_detection'] ?? [],
            'forecast_role' => $calibration['decision_instruction']['forecast_role'] ?? null,
            'detail_location' => 'evaluations.forecast_model_calibration',
        ];
    }

    private function compactVersionMetrics(array $versionMetrics): array
    {
        $versions = $versionMetrics['versions'] ?? [];
        $topVersions = $versionMetrics['top_versions'] ?? [];
        $sourceVersions = !empty($topVersions) ? $topVersions : $versions;

        if (empty($sourceVersions) || !is_array($sourceVersions)) {
            return $versionMetrics;
        }

        $totalSessionUsers = $this->totalSessionUsersFromVersions($sourceVersions);
        $relevantVersions = [];
        $releaseCandidateVersions = [];
        $legacyContext = [];

        foreach ($sourceVersions as $row) {
            if (!is_array($row)) {
                continue;
            }

            $appVersion = (string)($row['app_version'] ?? 'unknown');
            $sessionUsers = $this->numberOrNull($row['session_users'] ?? null);
            $sessionShare = $this->sessionShare($sessionUsers, $totalSessionUsers);
            $majorMinor = $this->majorMinorVersion($appVersion);
            $isCompatible = $majorMinor !== null && $majorMinor >= 3.5;
            $isCurrentLine = $majorMinor !== null && in_array(number_format($majorMinor, 1, '.', ''), ['3.5', '3.6'], true);
            $hasMeaningfulBase = $sessionUsers !== null && $sessionUsers >= 100 && $sessionShare !== null && $sessionShare >= 3.0;

            $compactRow = [
                'app_version' => $appVersion,
                'session_users' => $sessionUsers,
                'session_share_pct' => $sessionShare,
                'workspace_users' => $row['workspace_users'] ?? null,
                'food_add_success_users' => $row['food_add_success_users'] ?? null,
                'paywall_view_users' => $row['paywall_view_users'] ?? null,
                'purchase_success_users' => $row['purchase_success_users'] ?? null,
                'food_add_success_rate_from_session' => $row['food_add_success_rate_from_session'] ?? null,
                'food_add_success_rate_from_workspace' => $row['food_add_success_rate_from_workspace'] ?? null,
                'purchase_success_rate_from_paywall' => $row['purchase_success_rate_from_paywall'] ?? null,
            ];

            if ($isCompatible && ($isCurrentLine || $hasMeaningfulBase)) {
                $compactRow['release_relevance'] = $isCurrentLine ? 'current_release_line' : 'meaningful_active_base';
                $relevantVersions[] = $compactRow;
            } else {
                $legacyContext[] = [
                    'app_version' => $appVersion,
                    'session_users' => $sessionUsers,
                    'session_share_pct' => $sessionShare,
                    'reason' => $isCompatible ? 'small_or_low_share_version_context_only' : 'legacy_or_instrumentation_incompatible_context_only',
                ];
            }

            if ($majorMinor !== null && $majorMinor >= 3.6) {
                $releaseCandidateVersions[] = $compactRow;
            }
        }

        $versionMetrics['compact_version_context'] = [
            'relevance_rule' => 'Use relevant_versions and release_candidate_versions for rollout/release decisions. Legacy/instrumentation-incompatible versions are context only and must not veto current rollout decisions.',
            'total_session_users_in_version_window' => $totalSessionUsers,
            'relevant_versions' => array_slice($relevantVersions, 0, 8),
            'release_candidate_versions' => array_slice($releaseCandidateVersions, 0, 5),
            'legacy_context_summary' => [
                'legacy_or_context_versions_count' => count($legacyContext),
                'top_legacy_context_versions' => array_slice($legacyContext, 0, 8),
            ],
        ];

        unset($versionMetrics['versions']);
        $versionMetrics['top_versions'] = array_slice($versionMetrics['top_versions'] ?? $sourceVersions, 0, 8);

        return $versionMetrics;
    }

    private function totalSessionUsersFromVersions(array $versions): float
    {
        $total = 0.0;

        foreach ($versions as $row) {
            if (!is_array($row)) {
                continue;
            }

            $sessionUsers = $this->numberOrNull($row['session_users'] ?? null);
            if ($sessionUsers !== null && $sessionUsers > 0) {
                $total += $sessionUsers;
            }
        }

        return $total;
    }

    private function sessionShare(?float $sessionUsers, float $totalSessionUsers): ?float
    {
        if ($sessionUsers === null || $totalSessionUsers <= 0) {
            return null;
        }

        return round(($sessionUsers / $totalSessionUsers) * 100, 2);
    }

    private function majorMinorVersion(string $appVersion): ?float
    {
        $normalized = strtolower(trim($appVersion));

        if (!preg_match('/^(\d+)\.(\d+)/', $normalized, $matches)) {
            return null;
        }

        return (float)($matches[1] . '.' . $matches[2]);
    }

    private function numberOrNull($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float)$value : null;
    }
}
