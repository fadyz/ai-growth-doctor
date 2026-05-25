<?php

namespace App\Services\GrowthDoctor\Agents;

use App\Services\GrowthDoctor\Agents\AiAgentClient;

class DecisionScenarioSimulator
{
    private $client;

    public function __construct(AiAgentClient $client)
    {
        $this->client = $client;
    }

    public function buildRequest(array $context): array
    {
        $metrics = $context['metrics_context'] ?? $context['metrics'] ?? [];
        $finalDecisionEnvelope = $context['final_decision']
            ?? ($context['ai_final_decision_agent'] ?? []);
        $finalDecision = $finalDecisionEnvelope['result'] ?? $finalDecisionEnvelope;
        $specialistAgents = $context['specialist_agents'] ?? [];
        $evaluations = $context['evaluations'] ?? [];

        $tomorrowForecastMetrics = $context['tomorrow_forecast_metrics']
            ?? ($metrics['tomorrow_forecast_metrics'] ?? []);
        $guardrailPolicy = $context['guardrail_policy']
            ?? ($metrics['guardrail_policy'] ?? []);

        $adsAgentEnvelope = $specialistAgents['ai_ads_agent']
            ?? ($context['ai_ads_agent'] ?? []);
        $adsAgent = $adsAgentEnvelope['result'] ?? $adsAgentEnvelope;

        $forecastAgentEnvelope = $specialistAgents['ai_tomorrow_forecast_agent']
            ?? ($context['ai_tomorrow_forecast_agent'] ?? []);
        $forecastAgent = $forecastAgentEnvelope['result'] ?? $forecastAgentEnvelope;

        if (empty($tomorrowForecastMetrics) && !empty($forecastAgent)) {
            $tomorrowForecastMetrics = [
                'forecast_for_date' => $forecastAgent['forecast_for_date'] ?? null,
                'data_as_of_date' => $forecastAgent['data_as_of_date'] ?? null,
                'predicted_metrics' => $forecastAgent['predicted_metrics'] ?? [],
                'risk_flags' => $forecastAgent['risk_flags'] ?? ($forecastAgent['guardrail_assessment'] ?? []),
                'forecast_engine' => $forecastAgent['forecast_engine'] ?? null,
                'source' => 'reconstructed_from_tomorrow_forecast_agent_result',
            ];
        }

        $expectedSchema = [
            'agent' => 'Decision Scenario Simulator',
            'simulation_type' => 'baseline_vs_recommended_action',
            'simulation_scope' => 'short_horizon_operating_scenario',
            'baseline_without_intervention' => [
                'forecast_for_date' => 'date from tomorrow_forecast_metrics if available',
                'summary' => 'what likely happens if no major intervention is made',
                'key_metrics' => [
                    'activation' => 'baseline activation forecast summary',
                    'retention' => 'baseline retention forecast summary',
                    'monetization' => 'baseline monetization forecast summary',
                ],
                'risk_flags' => [
                    'activation_risk' => 'risk flag if available',
                    'retention_risk' => 'risk flag if available',
                    'habit_risk' => 'risk flag if available',
                    'monetization_sample' => 'sample flag if available',
                    'scaling_caution' => 'forecast caution if available',
                ],
            ],
            'recommended_intervention' => [
                'action' => 'action inferred from final decision, e.g. evaluate_reset_campaign_with_small_controlled_budget',
                'source' => 'final_decision_agent | ads_agent | guardrail_policy | mixed',
                'why_this_action' => 'why this action is the recommended intervention to simulate',
                'what_is_not_being_simulated' => 'explicitly state actions not simulated, such as aggressive scaling or paywall overhaul',
            ],
            'scenario_with_intervention' => [
                'summary' => 'probabilistic scenario if recommended action is executed',
                'expected_direction' => [
                    'acquisition_supply' => 'likely_up | likely_stable | likely_down | unknown',
                    'cpi_or_cost_efficiency' => 'likely_improve | watch | likely_worsen | unknown',
                    'd0_activation_quality' => 'likely_improve | neutral_to_slightly_up | watch | unknown_until_validated',
                    'd1_retention' => 'likely_improve | watch | unknown_until_cohort_matures',
                    'habit_7d' => 'likely_improve | watch | unknown_until_cohort_matures',
                    'purchase_or_revenue' => 'likely_improve | low_sample_do_not_use_as_primary_target | unknown',
                ],
                'confidence' => 'low | medium_low | medium | medium_high | high',
                'confidence_reason' => 'why confidence is set at this level',
                'evidence_basis' => [
                    'evidence item 1 from final decision / ads / forecast / evaluation / guardrail policy',
                    'evidence item 2',
                ],
                'risk' => [
                    'risk item 1',
                    'risk item 2',
                ],
                'success_criteria' => [
                    'specific metric to watch after action is executed',
                    'specific metric to watch after cohort matures',
                ],
            ],
            'baseline_vs_intervention_comparison' => [
                'main_difference' => 'what changes compared with baseline',
                'upside' => 'best plausible upside without overstating certainty',
                'downside' => 'main plausible downside',
                'decision_implication' => 'how user should compare doing nothing vs executing recommended action',
            ],
            'allowed_use' => 'how this simulation may be used in final decision / human decision',
            'not_allowed_use' => 'what this simulation must not be used for, e.g. aggressive scaling justification or exact uplift claim',
            'human_review_note' => 'what the human operator should verify before executing',
        ];

        $agentContext = [
            'metrics_context' => $metrics,
            'tomorrow_forecast_metrics' => $tomorrowForecastMetrics,
            'input_availability_check' => [
                'has_tomorrow_forecast_metrics' => !empty($tomorrowForecastMetrics),
                'has_forecast_for_date' => !empty($tomorrowForecastMetrics['forecast_for_date']),
                'has_final_decision' => !empty($finalDecision),
                'has_business_verdict' => !empty($finalDecision['business_verdict']),
                'has_ads_agent_result' => !empty($adsAgent),
                'has_tomorrow_forecast_agent_result' => !empty($forecastAgent),
            ],
            'guardrail_policy' => $guardrailPolicy,
            'final_decision_agent' => $finalDecision,
            'final_decision' => $finalDecision,
            'recommended_actions_from_final_decision' => [
                'business_verdict' => $finalDecision['business_verdict'] ?? null,
                'today_operator_summary' => $finalDecision['today_operator_summary'] ?? null,
                'operating_decision' => $finalDecision['operating_decision'] ?? [],
                'prioritized_actions' => $finalDecision['prioritized_actions'] ?? [],
                'operational_action_plan' => $finalDecision['operational_action_plan'] ?? [],
                'action_plan' => $finalDecision['action_plan'] ?? [],
            ],
            'specialist_agents' => $specialistAgents,
            'ads_agent_result' => $adsAgent,
            'tomorrow_forecast_agent_result' => $forecastAgent,
            'evaluations' => $evaluations,
            'strict_rules' => [
                'This simulator compares deterministic baseline forecast against the recommended action from FinalDecisionAgent.',
                'The recommended action is available in final_decision_agent / final_decision, especially business_verdict, today_operator_summary, operating_decision, prioritized_actions, operational_action_plan, and action_plan.',
                'The baseline forecast is available in tomorrow_forecast_metrics and may also be interpreted by tomorrow_forecast_agent_result. Do not say forecast metrics are missing when tomorrow_forecast_metrics is present.',
                'If input_availability_check.has_tomorrow_forecast_metrics is true, you must not output forecast_for_date as unknown. Use tomorrow_forecast_metrics.forecast_for_date or tomorrow_forecast_agent_result.forecast_for_date.',
                'Do not create a new business decision that contradicts FinalDecisionAgent or GuardrailPolicyEngine.',
                'Do not treat forecast risk_flags or scaling_caution as deterministic GuardrailPolicyEngine triggers.',
                'Do not invent exact numeric uplift unless the context contains experiment, holdout, or mature historical uplift evidence.',
                'If evidence is weak, use directional language such as likely_up, watch, unknown_until_validated, or unknown_until_cohort_matures.',
                'If simulating a reset campaign test, the default safe interpretation is small controlled test only, not aggressive scaling.',
                'Always include allowed_use and not_allowed_use so the simulation cannot be overinterpreted.',
                'The simulator is decision support for a human operator, not an autonomous executor.',
            ],
        ];

        return $this->client->prepareRequest(
            'Decision Scenario Simulator',
            'You are the Decision Scenario Simulator in a multi-agent growth doctor system for the Hitung Kalori app. Your job is to compare the baseline no-major-intervention forecast from tomorrow_forecast_metrics with the recommended action produced by FinalDecisionAgent. The final decision may be nested under final_decision_agent/final_decision and includes fields such as business_verdict, today_operator_summary, operating_decision, prioritized_actions, operational_action_plan, and action_plan. You must not create a new recommendation, override GuardrailPolicyEngine, or invent exact uplift numbers without experiment/holdout evidence. Produce a probabilistic, evidence-bounded scenario in Indonesian. Use forecast risk flags as cautions, not deterministic guardrails. Return valid JSON only. No markdown, no prose outside JSON, no code fences.',
            $expectedSchema,
            $agentContext
        );
    }

    public function run(array $context): array
    {
        $request = $this->buildRequest($context);

        return $this->client->call(
            $request['agent'] ?? ($request['agent_name'] ?? 'Decision Scenario Simulator'),
            $request['system_prompt'] ?? ($request['prompt'] ?? ''),
            $request['expected_schema'] ?? ($request['schema'] ?? []),
            $request['context'] ?? ($request['agent_context'] ?? [])
        );
    }
}