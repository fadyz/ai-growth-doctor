<?php

namespace Tests\Unit;

use App\Services\GrowthDoctor\AgentSocietyBaselineComparisonService;
use PHPUnit\Framework\TestCase;

class AgentSocietyBaselineComparisonServiceTest extends TestCase
{
    public function test_it_derives_ads_baseline_and_quantitative_delta_from_run_outputs(): void
    {
        $service = new AgentSocietyBaselineComparisonService();

        $comparison = $service->compare([
            'metrics_context' => [
                'activation_metrics' => ['core_action_success_users' => 12],
                'retention_metrics' => ['d1_logged_rate' => 0.18],
                'monetization_metrics' => ['paywall_to_purchase_rate' => 0.02],
                'ads_metrics' => ['campaigns' => ['reset' => ['spend' => 10]]],
                'source_metric_refs' => [
                    'ads' => ['campaigns.reset.spend' => '$.ads.campaigns.reset.spend'],
                    'activation' => ['core_action_success_users' => '$.activation.core_action_success_users'],
                    'retention' => ['d1_logged_rate' => '$.retention.d1_logged_rate'],
                ],
                'guardrail_policy' => [
                    'triggered_guardrails' => ['retention_guardrail' => ['status' => 'triggered']],
                    'deterministic_decision' => [
                        'blocked_decision' => 'block aggressive ads budget scaling',
                        'allowed_decision' => 'small controlled reset campaign test',
                    ],
                ],
            ],
            'specialist_agents' => [
                'ai_ads_agent' => [
                    'agent' => 'AI Ads Agent',
                    'result' => [
                        'summary' => 'Ads look better, but activation and retention are weak. Do not scale aggressively; run a small controlled test and monitor downstream quality.',
                        'evidence_refs' => ['metrics_context.source_metric_refs.ads.campaigns.reset.spend'],
                    ],
                ],
                'ai_activation_agent' => [
                    'agent' => 'AI Activation Agent',
                    'result' => ['summary' => 'Activation core action is weak.'],
                ],
            ],
            'structured_negotiation' => [
                'summary' => [
                    'resolved_material_tension_count' => 2,
                    'minor_bounded_caution_count' => 1,
                    'safety_bounded_revision_count' => 1,
                ],
                'negotiation_transcript' => [
                    ['evidence_refs' => ['metrics_context.source_metric_refs.activation.core_action_success_users']],
                    ['evidence_refs' => ['metrics_context.source_metric_refs.retention.d1_logged_rate']],
                ],
            ],
            'conflict_matrix' => [
                [
                    'severity' => 'material',
                    'status' => 'resolved_in_round_1',
                    'agents_involved' => ['ai_ads_agent', 'ai_retention_agent'],
                    'evidence_refs' => ['metrics_context.source_metric_refs.retention.d1_logged_rate'],
                ],
            ],
            'final_decision' => [
                'result' => [
                    'business_verdict' => 'HOLD_AND_OPTIMIZE',
                    'today_operator_summary' => 'Hold aggressive ads scaling, fix activation and D1 habit, keep paywall value-gated.',
                ],
            ],
            'normalized_action_plan' => [
                ['owner_domain' => 'ads', 'action' => 'Small controlled reset campaign test'],
                ['owner_domain' => 'activation', 'action' => 'Fix core action path'],
                ['owner_domain' => 'retention', 'action' => 'Improve D1 habit'],
                ['owner_domain' => 'monetization', 'action' => 'Keep paywall value-gated'],
            ],
        ]);

        $this->assertSame('ads_agent_only', $comparison['baseline_mode']);
        $this->assertTrue($comparison['is_data_derived']);
        $this->assertSame(2, $comparison['agent_society']['resolved_material_tensions_detected']);
        $this->assertSame(4, $comparison['agent_society']['action_items_count']);
        $this->assertArrayHasKey('numeric', $comparison['delta']['decision_completeness_score']);
        $this->assertArrayHasKey('display', $comparison['delta']['decision_completeness_score']);
        $expectedBaselineEvidenceScore = (int) round(
            ($comparison['single_agent_baseline']['evidence_domains_used'] / $comparison['available_domains_in_run']) * 100
        );
        $this->assertSame($expectedBaselineEvidenceScore, $comparison['single_agent_baseline']['evidence_coverage_score']);
        $this->assertSame('heuristic_audit_score', $comparison['score_methodology']['score_type']);
    }
}
