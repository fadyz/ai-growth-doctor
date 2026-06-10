<?php


namespace App\Services\GrowthDoctor\Agents;

use App\Services\GrowthDoctor\Agents\AiAgentClient;

class AiAdsAgent
{
    private $client;

    public function __construct(AiAgentClient $client)
    {
        $this->client = $client;
    }

    public function run(array $metricsContext): array
    {
        $noAdsDataResult = $this->noAdsDataResult($metricsContext);

        if ($noAdsDataResult !== null) {
            return $noAdsDataResult;
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
        $adsMetrics = $metricsContext['ads_metrics'] ?? [];
        $activationMetrics = $metricsContext['activation_metrics'] ?? [];
        $retentionMetrics = $metricsContext['retention_metrics'] ?? [];
        $ruleDecision = $metricsContext['rule_based_decision'] ?? [];

        $expectedSchema = [
            'agent' => 'AI Ads Agent',
            'ads_verdict' => 'scale_carefully | maintain_budget | hold_budget | reduce_budget | shift_to_reset_campaign | evaluate_reset_successor | monitor_ads | no_ads_data',
            'campaign_health' => 'healthy | mixed | deteriorating | recovery_candidate | degraded_legacy | insufficient_data',
            'confidence_score' => '0-100 integer',
            'main_diagnosis' => 'plain-language diagnosis of ads performance and campaign context',
            'deterministic_lifecycle_context' => [
                'source' => 'deterministic campaign lifecycle mapping, not AI inference',
                'legacy_campaign' => [
                    'campaign_name' => 'legacy campaign name when known',
                    'lifecycle_status' => 'degraded_legacy | legacy_context | active | unknown | no_ads_data',
                    'system_interpretation' => 'how the system says this campaign should be interpreted',
                    'allowed_interpretation' => 'what Ads Agent may infer from this lifecycle label',
                    'blocked_interpretation' => 'what Ads Agent must not infer from this lifecycle label',
                ],
                'reset_successor_campaign' => [
                    'campaign_name' => 'reset successor campaign name when known',
                    'lifecycle_status' => 'reset_successor | active | unknown | no_ads_data',
                    'system_interpretation' => 'how the system says this campaign should be interpreted',
                    'allowed_interpretation' => 'what Ads Agent may infer from this lifecycle label',
                    'blocked_interpretation' => 'what Ads Agent must not infer from this lifecycle label',
                ],
                'interpretation_winner' => 'lifecycle_context_wins_campaign_identity',
                'note' => 'lifecycle context chooses which campaign is valid to interpret; it does not by itself prove budget performance',
            ],
            'ads_metric_independent_assessment' => [
                'scope' => 'campaign or campaign family assessed from ads metrics only',
                'metric_basis' => [
                    'recent_conversion_rate' => 'recent conversion rate when available',
                    'previous_conversion_rate' => 'previous comparison conversion rate when available',
                    'cost_per_install_change_pct' => 'CPI movement when available',
                    'conversion_volume_change_pct' => 'conversion count/volume movement, not conversion-rate movement',
                    'spend_change_pct' => 'spend/cost movement when available',
                    'sample_quality' => 'sufficient | early | low_sample | insufficient',
                ],
                'independent_performance_read' => 'what ads metrics show before lifecycle/downstream constraints decide the action',
                'budget_intensity_supported_by_metrics' => 'reduce | hold | monitor | cautious_test | maintain | scale_carefully',
                'metric_winner_for_budget_intensity' => 'ads_metrics_win_budget_intensity',
            ],
            'campaign_lifecycle_interpretation' => [
                'legacy_campaign_status' => 'explain whether Volume Stabil is degraded legacy, active, or unknown',
                'reset_campaign_status' => 'explain whether Volume Install Reset is successor/recovery candidate',
                'operator_action_interpretation' => 'explain whether pausing/reducing legacy campaign should be interpreted as recovery strategy or acquisition shutdown',
            ],
            'field_resolution_rule' => [
                'lifecycle_wins_for' => 'campaign identity and interpretation, e.g. legacy vs reset successor',
                'metrics_win_for' => 'budget intensity, e.g. reduce/hold/monitor/cautious_test/scale_carefully',
                'downstream_guardrails_win_for' => 'safety limits, e.g. no aggressive scaling when activation/retention is weak',
                'final_ads_posture' => 'combined decision after applying lifecycle identity, ads metrics, and downstream safety limits',
            ],
            'reset_campaign_decision' => [
                'recommended_posture' => 'ignore_reset | monitor_only | evaluate_small_controlled_test | shift_attention_to_reset | increase_reset_budget_cautiously',
                'why' => 'why this posture is appropriate given legacy lifecycle, reset successor status, and downstream activation/retention quality',
                'allowed_today' => 'allowed action today for reset successor campaign',
                'not_allowed_today' => 'action that should not be taken today',
                'success_metric_to_watch' => 'metric that determines whether reset campaign is working',
            ],
            'budget_decision' => [
                'decision' => 'maintain | hold | reduce | cautious_test | shift_budget_attention | evaluate_reset_campaign | no_action',
                'scope' => 'which campaign/family the decision applies to',
                'reason' => 'why this budget decision is recommended',
                'allowed_action' => 'specific allowed action today, e.g. evaluate_reset_campaign_with_small_controlled_budget, maintain_current_budget, or monitor_without_scaling',
                'blocked_action' => 'specific blocked action today, e.g. scale_legacy_campaign, aggressive_budget_increase, or treat_legacy_pause_as_acquisition_shutdown',
            ],
            'campaign_observations' => [
                [
                    'campaign' => 'campaign name',
                    'lifecycle_status' => 'degraded_legacy | reset_successor | active | unknown',
                    'performance_signal' => 'what the ads metrics show',
                    'risk' => 'main risk',
                    'recommendation' => 'campaign-specific recommendation',
                ],
            ],
            'ads_supply_vs_product_quality' => [
                'ads_supply_signal' => 'summary of CPI/conversion/spend health',
                'downstream_quality_dependency' => 'activation/retention metric that must validate ads quality',
                'interpretation' => 'how to combine ads evidence with app metrics',
            ],
            'guardrails' => [
                'scale_guardrail' => 'condition required before scaling ads; this is Ads Agent operating guardrail/campaign rule, not GuardrailPolicyEngine winning_guardrail',
                'stop_loss_guardrail' => 'condition that should stop or reduce ads',
                'monitoring_metric' => 'metric to monitor tomorrow',
            ],
            'impact_on_final_decision' => 'how this Ads Agent output should affect Final Decision today',
        ];

        $agentContext = [
            'task' => 'Evaluate ads acquisition performance and campaign lifecycle context for Final Decision Agent.',
            'ads_metrics' => $adsMetrics,
            'deterministic_lifecycle_context' => $this->deterministicLifecycleContext($adsMetrics),
            'ads_metric_independent_inputs' => $this->adsMetricIndependentInputs($adsMetrics),
            'field_resolution_rule' => [
                'lifecycle_wins_for' => 'Campaign identity and interpretation. If a campaign is deterministically marked degraded_legacy or reset_successor, use that as the campaign lifecycle frame.',
                'metrics_win_for' => 'Budget intensity. CPI, conversion rate, conversion volume, spend movement, and sample quality decide whether action is reduce, hold, monitor, cautious_test, maintain, or scale_carefully.',
                'downstream_guardrails_win_for' => 'Safety limits. Activation, retention, and guardrail context can cap an otherwise positive ads read to small controlled testing.',
                'conflict_rule' => 'If lifecycle says reset successor should be evaluated but ads metrics clearly deteriorate, metrics win budget intensity: hold/reduce/monitor the reset campaign. Lifecycle remains true, but action is constrained.',
            ],
            'activation_context' => [
                'status' => $activationMetrics['status'] ?? null,
                'summary' => $activationMetrics['summary'] ?? null,
                'diagnosis' => $activationMetrics['diagnosis'] ?? null,
                'key_metrics' => $activationMetrics['key_metrics'] ?? [],
                'metrics_7d' => $activationMetrics['metrics_7d'] ?? [],
            ],
            'retention_context' => [
                'status' => $retentionMetrics['status'] ?? null,
                'summary' => $retentionMetrics['summary'] ?? null,
                'diagnosis' => $retentionMetrics['diagnosis'] ?? null,
                'key_metrics' => $retentionMetrics['key_metrics'] ?? [],
                'metrics_7d_avg' => $retentionMetrics['metrics_7d_avg'] ?? [],
            ],
            'rule_based_decision' => $ruleDecision,
            'strict_rules' => [
                'If HK - ID - Volume Stabil is marked degraded_legacy, do not recommend scaling it unless independent recovery is proven.',
                'If HK - ID - Volume Install Reset is marked reset_successor, interpret it as replacement/recovery candidate, not as a random new campaign.',
                'If a degraded_legacy campaign and reset_successor campaign both exist, prefer ads_verdict = shift_to_reset_campaign or evaluate_reset_successor unless reset successor data is clearly deteriorating.',
                'When reset_successor exists but sample is still early, recommend budget_decision.decision = cautious_test, shift_budget_attention, or evaluate_reset_campaign; do not default to monitor_ads unless there is truly insufficient reset data.',
                'In this legacy-to-reset scenario, the default allowed action should be evaluate_reset_campaign_with_small_controlled_budget, while the default blocked action should be scale_legacy_campaign or aggressive_budget_increase.',
                'If legacy campaign is paused or reduced while reset successor exists, do not interpret that as shutting down acquisition.',
                'If ads look efficient but retention is weak, recommend only small controlled test of the reset successor, not aggressive scaling.',
                'If ads CPI/conversion deteriorates, strengthen hold/reduce budget recommendation.',
                'IMPORTANT METRIC SEMANTICS: conversion_change_pct in ads_metrics.recent_vs_previous means change in conversion count/volume, not change in conversion_rate.',
                'When discussing conversion rate movement, compare recent_3d.conversion_rate versus previous_7d.conversion_rate directly; do not call conversion_change_pct a conversion-rate change.',
                'When discussing conversion volume movement, use conversions and conversion_change_pct, and explicitly say conversion volume/count changed.',
                'If spend/cost also changed materially, explain that conversion count may change because spend changed; do not treat lower conversion count as lower conversion_rate without checking conversion_rate.',
                'If campaign is unmapped, avoid strong lifecycle assumptions.',
                'Always separate deterministic_lifecycle_context from ads_metric_independent_assessment.',
                'Lifecycle context wins campaign identity and interpretation; ads metrics win budget intensity; downstream activation/retention/guardrails win safety limits.',
                'Do not claim the reset successor is working merely because lifecycle context says it is a reset successor. Prove budget intensity from conversion_rate, CPI, conversion volume, spend, and sample quality.',
                'Do not claim a legacy campaign is the main campaign merely because it has historical volume when deterministic lifecycle marks it as degraded_legacy and a reset_successor exists.',
            ],
        ];

        return $this->client->prepareRequest(
            'AI Ads Agent',
            'You are the Ads Acquisition Specialist Agent in a multi-agent growth doctor system for the Hitung Kalori app. Analyze Google Ads performance from ads_metrics, including campaign lifecycle context. You must explicitly separate deterministic_lifecycle_context from ads_metric_independent_assessment. Lifecycle context wins only campaign identity and interpretation: degraded legacy vs reset successor. Ads metrics win budget intensity: reduce, hold, monitor, cautious_test, maintain, or scale_carefully. Downstream activation/retention/guardrails win safety limits and can cap a positive ads read to small controlled testing. You must not treat a degraded legacy campaign as the main campaign to continue if a reset successor exists. When degraded legacy and reset successor both exist, produce a concrete reset-campaign posture: shift_to_reset_campaign or evaluate_reset_successor, with cautious_test/evaluate_reset_campaign as the budget decision unless reset successor data is clearly deteriorating. Separate ads supply health from downstream product quality. Be precise with ads metric semantics: conversion_change_pct in ads_metrics means conversion count/volume change, not conversion-rate change; compare recent_3d.conversion_rate against previous_7d.conversion_rate when discussing conversion rate. Do not claim the reset successor is working merely because lifecycle context says it is a reset successor; prove budget intensity from conversion_rate, CPI, conversion volume, spend, and sample quality. Ads can support maintain, reduce, hold, shift attention to reset, or cautious reset test decisions, but it cannot override weak retention by itself. Return valid JSON only in ' . $language . '. No markdown, no prose outside JSON, no code fences.',
            $expectedSchema,
            $agentContext
        );
    }

    private function noAdsDataResult(array $metricsContext): ?array
    {
        $adsMetrics = $metricsContext['ads_metrics'] ?? [];

        if (!empty($adsMetrics) && ($adsMetrics['status'] ?? null) !== 'no_ads_data') {
            return null;
        }

        return [
            'agent' => 'AI Ads Agent',
            'status' => 'no_ads_data',
            'result' => [
                'ads_verdict' => 'no_ads_data',
                'budget_decision' => [
                    'decision' => 'no_action',
                    'scope' => 'ads',
                    'reason' => 'No ads data available in checkpoint.',
                    'allowed_action' => 'use_non_ads_evidence',
                    'blocked_action' => 'ads_based_recommendation',
                ],
                'confidence_score' => 0,
                'main_diagnosis' => 'No Google Ads data is available in the checkpoint, so the Ads Agent does not affect the final decision yet.',
                'deterministic_lifecycle_context' => [
                    'source' => 'deterministic campaign lifecycle mapping, not AI inference',
                    'legacy_campaign' => [
                        'campaign_name' => null,
                        'lifecycle_status' => 'no_ads_data',
                        'system_interpretation' => 'No legacy campaign can be interpreted because ads data is unavailable.',
                        'allowed_interpretation' => 'Use non-ads evidence.',
                        'blocked_interpretation' => 'Do not make an ads-based recommendation.',
                    ],
                    'reset_successor_campaign' => [
                        'campaign_name' => null,
                        'lifecycle_status' => 'no_ads_data',
                        'system_interpretation' => 'No reset successor campaign can be interpreted because ads data is unavailable.',
                        'allowed_interpretation' => 'Use non-ads evidence.',
                        'blocked_interpretation' => 'Do not make an ads-based recommendation.',
                    ],
                    'interpretation_winner' => 'no_ads_lifecycle_context_available',
                    'note' => 'Lifecycle context is unavailable because no ads data exists.',
                ],
                'ads_metric_independent_assessment' => [
                    'scope' => 'ads',
                    'metric_basis' => [
                        'recent_conversion_rate' => null,
                        'previous_conversion_rate' => null,
                        'cost_per_install_change_pct' => null,
                        'conversion_volume_change_pct' => null,
                        'spend_change_pct' => null,
                        'sample_quality' => 'insufficient',
                    ],
                    'independent_performance_read' => 'No ads metrics are available for an independent assessment.',
                    'budget_intensity_supported_by_metrics' => 'monitor',
                    'metric_winner_for_budget_intensity' => 'no_metric_basis_available',
                ],
                'campaign_lifecycle_interpretation' => [
                    'legacy_campaign_status' => 'no_ads_data',
                    'reset_campaign_status' => 'no_ads_data',
                    'operator_action_interpretation' => 'No ads campaign can be evaluated.',
                ],
                'field_resolution_rule' => [
                    'lifecycle_wins_for' => 'Campaign identity and interpretation when lifecycle context exists.',
                    'metrics_win_for' => 'Budget intensity when ads metrics exist.',
                    'downstream_guardrails_win_for' => 'Safety limits when downstream metrics exist.',
                    'final_ads_posture' => 'No ads-based posture; use non-ads evidence.',
                ],
                'reset_campaign_decision' => [
                    'recommended_posture' => 'monitor_only',
                    'why' => 'No reset campaign data is available for evaluation.',
                    'allowed_today' => 'use_non_ads_evidence',
                    'not_allowed_today' => 'ads_based_recommendation',
                    'success_metric_to_watch' => 'ads data availability in next checkpoint',
                ],
                'impact_on_final_decision' => 'Ads evidence is unavailable; Final Decision must use activation, retention, monetization, version, forecast, and evaluation evidence.',
            ],
        ];
    }

    private function deterministicLifecycleContext(array $adsMetrics): array
    {
        $campaigns = $adsMetrics['campaigns'] ?? [];
        $legacy = null;
        $reset = null;

        foreach ($campaigns as $campaignName => $campaign) {
            $context = $campaign['lifecycle_context'] ?? [];
            $status = $context['lifecycle_status'] ?? 'unknown';

            if (in_array($status, ['degraded_legacy', 'legacy_context'], true) && $legacy === null) {
                $legacy = [
                    'campaign_name' => (string) $campaignName,
                    'lifecycle_status' => $status,
                    'system_interpretation' => $context['decision_rule'] ?? 'Treat this campaign as legacy context, not the primary acquisition candidate when a reset successor exists.',
                    'allowed_interpretation' => 'Use this campaign as historical/degraded context unless independent recovery is proven.',
                    'blocked_interpretation' => 'Do not treat legacy pause/reduction as acquisition shutdown when a reset successor exists.',
                ];
            }

            if ($status === 'reset_successor' && $reset === null) {
                $reset = [
                    'campaign_name' => (string) $campaignName,
                    'lifecycle_status' => $status,
                    'system_interpretation' => $context['decision_rule'] ?? 'Treat this campaign as the reset/recovery successor candidate.',
                    'allowed_interpretation' => 'Evaluate this campaign as the replacement/recovery candidate.',
                    'blocked_interpretation' => 'Do not assume it deserves scaling without independent metric evidence.',
                ];
            }
        }

        return [
            'source' => 'deterministic campaign lifecycle mapping, not AI inference',
            'legacy_campaign' => $legacy ?? [
                'campaign_name' => null,
                'lifecycle_status' => 'unknown',
                'system_interpretation' => 'No deterministic legacy campaign was identified.',
                'allowed_interpretation' => 'Avoid strong legacy assumptions.',
                'blocked_interpretation' => 'Do not invent degraded legacy status.',
            ],
            'reset_successor_campaign' => $reset ?? [
                'campaign_name' => null,
                'lifecycle_status' => 'unknown',
                'system_interpretation' => 'No deterministic reset successor campaign was identified.',
                'allowed_interpretation' => 'Avoid strong reset-successor assumptions.',
                'blocked_interpretation' => 'Do not invent reset successor status.',
            ],
            'interpretation_winner' => 'lifecycle_context_wins_campaign_identity',
            'note' => 'Lifecycle context chooses which campaign is valid to interpret; it does not by itself prove budget performance.',
        ];
    }

    private function adsMetricIndependentInputs(array $adsMetrics): array
    {
        $campaigns = $adsMetrics['campaigns'] ?? [];
        $inputs = [];

        foreach ($campaigns as $campaignName => $campaign) {
            $recent = $campaign['recent_vs_previous'] ?? [];
            $recentWindow = $recent['recent_3d'] ?? [];
            $previousWindow = $recent['previous_7d'] ?? [];

            $inputs[] = [
                'campaign_name' => (string) $campaignName,
                'lifecycle_status' => $campaign['lifecycle_context']['lifecycle_status'] ?? 'unknown',
                'health_status' => $campaign['health']['status'] ?? null,
                'recent_conversion_rate' => $recentWindow['conversion_rate'] ?? null,
                'previous_conversion_rate' => $previousWindow['conversion_rate'] ?? null,
                'cost_per_install_change_pct' => $recent['cost_per_install_change_pct'] ?? null,
                'conversion_volume_change_pct' => $recent['conversion_change_pct'] ?? null,
                'spend_change_pct' => $recent['cost_change_pct'] ?? null,
                'recent_conversions' => $recentWindow['conversions'] ?? null,
                'previous_conversions' => $previousWindow['conversions'] ?? null,
                'sample_quality_hint' => $this->adsSampleQuality($recentWindow['conversions'] ?? null, $previousWindow['conversions'] ?? null),
                'metric_reading_instruction' => 'Assess this from ads metrics only. Do not let lifecycle status prove budget intensity.',
            ];
        }

        return $inputs;
    }

    private function adsSampleQuality($recentConversions, $previousConversions): string
    {
        $total = (int) ($recentConversions ?? 0) + (int) ($previousConversions ?? 0);

        if ($total <= 0) {
            return 'insufficient';
        }

        if ($total < 10) {
            return 'low_sample';
        }

        if ($total < 30) {
            return 'early';
        }

        return 'sufficient';
    }
}
