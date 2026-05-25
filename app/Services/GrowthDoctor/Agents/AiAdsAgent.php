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
            'campaign_lifecycle_interpretation' => [
                'legacy_campaign_status' => 'explain whether Volume Stabil is degraded legacy, active, or unknown',
                'reset_campaign_status' => 'explain whether Volume Install Reset is successor/recovery candidate',
                'operator_action_interpretation' => 'explain whether pausing/reducing legacy campaign should be interpreted as recovery strategy or acquisition shutdown',
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
            ],
        ];

        return $this->client->prepareRequest(
            'AI Ads Agent',
            'You are the Ads Acquisition Specialist Agent in a multi-agent growth doctor system for the Hitung Kalori app. Analyze Google Ads performance from ads_metrics, including campaign lifecycle context. You must not treat a degraded legacy campaign as the main campaign to continue if a reset successor exists. When degraded legacy and reset successor both exist, produce a concrete reset-campaign posture: shift_to_reset_campaign or evaluate_reset_successor, with cautious_test/evaluate_reset_campaign as the budget decision unless reset successor data is clearly deteriorating. Separate ads supply health from downstream product quality. Be precise with ads metric semantics: conversion_change_pct is conversion count/volume change, not conversion-rate change; compare recent_3d.conversion_rate against previous_7d.conversion_rate when discussing conversion rate. Ads can support maintain, reduce, hold, shift attention to reset, or cautious reset test decisions, but it cannot override weak retention by itself. Return valid JSON only in Indonesian. No markdown, no prose outside JSON, no code fences.',
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
                'main_diagnosis' => 'Belum ada data Google Ads di checkpoint, jadi Ads Agent belum memengaruhi final decision.',
                'campaign_lifecycle_interpretation' => [
                    'legacy_campaign_status' => 'no_ads_data',
                    'reset_campaign_status' => 'no_ads_data',
                    'operator_action_interpretation' => 'Tidak ada campaign ads yang bisa dievaluasi.',
                ],
                'reset_campaign_decision' => [
                    'recommended_posture' => 'monitor_only',
                    'why' => 'Tidak ada data reset campaign yang bisa dievaluasi.',
                    'allowed_today' => 'use_non_ads_evidence',
                    'not_allowed_today' => 'ads_based_recommendation',
                    'success_metric_to_watch' => 'ads data availability in next checkpoint',
                ],
                'impact_on_final_decision' => 'Ads evidence unavailable; Final Decision harus memakai activation, retention, monetization, version, forecast, dan evaluation evidence.',
            ],
        ];
    }
}