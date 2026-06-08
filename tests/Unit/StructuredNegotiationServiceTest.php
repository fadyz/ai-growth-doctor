<?php

namespace Tests\Unit;

use App\Services\GrowthDoctor\StructuredNegotiationService;
use App\Services\GrowthDoctor\RunProgressStore;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class StructuredNegotiationServiceTest extends TestCase
{
    public function testNegotiationOutputIsSingleRoundAndBuildsConflictMatrix(): void
    {
        $service = new StructuredNegotiationService();

        $output = $service->run([
            'metrics_context' => [
                'activation_metrics' => [
                    'metrics_7d' => [
                        'workspace_rate' => 18,
                        'food_add_success_rate_from_session' => 21,
                        'food_add_success_rate_from_workspace' => 24,
                    ],
                ],
                'retention_metrics' => [
                    'metrics_7d_avg' => [
                        'd1_logged_rate' => 14,
                    ],
                ],
            ],
            'guardrail_result' => [
                'blocked_actions' => ['scale_ads_aggressively'],
            ],
            'specialist_outputs' => [
                'ai_ads_agent' => [
                    'result' => [
                        'main_diagnosis' => 'CPI is efficient and a scale test may be possible.',
                        'impact_on_final_decision' => 'Consider scale carefully.',
                        'confidence_score' => 70,
                    ],
                ],
                'ai_activation_agent' => [
                    'result' => [
                        'diagnosis' => 'Activation is weak and below safe threshold.',
                        'confidence_score' => 82,
                    ],
                ],
                'ai_retention_agent' => [
                    'result' => [
                        'diagnosis' => 'D1 retention is weak.',
                        'confidence_score' => 65,
                    ],
                ],
            ],
        ]);

        $this->assertSame(1, $output['round']);
        $this->assertSame(1, $output['rules']['max_rounds']);
        $this->assertSame('deterministic_single_round', $output['execution']['mode']);
        $this->assertNotEmpty($output['conflicts']);
        $this->assertSame(count($output['conflicts']), $output['summary']['total_conflict_count']);
        $this->assertSame($output['conflicts'], $output['decision_package']['conflict_matrix']);
        $this->assertNotEmpty($output['negotiation_timeline']);
        $this->assertArrayHasKey('baseline_comparison', $output);
        $this->assertContains('metrics_context.activation_metrics.metrics_7d.food_add_success_rate_from_session', $output['agent_responses'][0]['evidence_refs']);
    }

    public function testNegotiationHasNoMoreThanOneResponsePerAgent(): void
    {
        $service = new StructuredNegotiationService();
        $output = $service->run([
            'specialist_outputs' => [],
            'metrics_context' => [],
            'guardrail_result' => [],
        ]);

        $agentNames = array_map(function (array $response) {
            return $response['agent_name'];
        }, $output['agent_responses']);

        $this->assertSame($agentNames, array_values(array_unique($agentNames)));
    }

    public function testObjectionWithoutEvidenceIsConvertedToNoMaterialObjection(): void
    {
        $service = new StructuredNegotiationService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizeAgentResponses');
        $method->setAccessible(true);

        $responses = $method->invoke($service, [
            [
                'agent_name' => 'Activation Agent',
                'target_agent' => 'Ads Agent',
                'response_type' => 'objection',
                'severity' => 'material',
                'claim' => 'Unsafe scale.',
                'evidence_refs' => [],
                'revised_recommendation' => 'Hold scale.',
                'confidence' => 'high',
            ],
        ]);

        $this->assertSame('no_material_objection', $responses[0]['response_type']);
        $this->assertSame('none', $responses[0]['severity']);
        $this->assertSame(['specialist_output_summary'], $responses[0]['evidence_refs']);
    }

    public function testAdsEvidenceUsesResetSuccessorNestedComparisonMetrics(): void
    {
        $service = new StructuredNegotiationService();

        $output = $service->standardizeSpecialistOutputs([], [
            'ads_metrics' => [
                'campaigns' => [
                    'Legacy Campaign' => [
                        'recent_vs_previous' => [
                            'recent_3d' => ['conversion_rate' => 10],
                            'previous_7d' => ['conversion_rate' => 11],
                        ],
                        'lifecycle_context' => ['lifecycle_status' => 'degraded_legacy'],
                    ],
                    'Reset Campaign' => [
                        'recent_vs_previous' => [
                            'recent_3d' => ['conversion_rate' => 34.89],
                            'previous_7d' => ['conversion_rate' => 31.82],
                            'cost_per_install_change_pct' => 11.34,
                            'conversion_change_pct' => -57.95,
                            'cost_change_pct' => -53.18,
                        ],
                        'lifecycle_context' => ['lifecycle_status' => 'reset_successor'],
                    ],
                ],
            ],
            'retention_metrics' => [
                'status' => 'warning',
            ],
        ]);

        $adsEvidence = $output['ai_ads_agent']['supporting_evidence'];

        $this->assertSame('34.89%', $adsEvidence[0]['value']);
        $this->assertSame('31.82%', $adsEvidence[1]['value']);
        $this->assertSame('11.34%', $adsEvidence[2]['value']);
        $this->assertSame('-57.95%', $adsEvidence[3]['value']);
        $this->assertSame('-53.18%', $adsEvidence[4]['value']);
        $this->assertSame('warning', $adsEvidence[5]['value']);
    }

    public function testStructuredNegotiationCreatesAdsConflictOnlyFromMaterialEvidenceBackedActionConflict(): void
    {
        $service = new StructuredNegotiationService();

        $output = $service->run([
            'metrics_context' => [
                'retention_metrics' => [
                    'status' => 'warning',
                    'metrics_7d_avg' => [
                        'd1_logged_rate' => 14,
                    ],
                ],
                'guardrail_policy' => [
                    'deterministic_decision' => [
                        'blocked_actions' => ['aggressive_ads_scale'],
                    ],
                ],
            ],
            'guardrail_result' => [
                'deterministic_decision' => [
                    'blocked_actions' => ['aggressive_ads_scale'],
                ],
            ],
            'specialist_outputs' => [
                'ai_ads_agent' => [
                    'result' => [
                        'main_diagnosis' => 'Reset successor can be tested, but scaling is risky.',
                        'recommendation' => 'Run only a small controlled test.',
                        'confidence_score' => 70,
                    ],
                ],
            ],
        ]);

        $conflictIds = array_map(function (array $conflict) {
            return $conflict['conflict_id'];
        }, $output['conflicts']);

        $this->assertContains('conflict_ads_scale_vs_retention', $conflictIds);
        $this->assertTrue($output['baseline_comparison']['agent_society']['unsafe_recommendation_prevented']);
        $this->assertNotEmpty($output['baseline_comparison']['agent_society']['unsafe_prevention_basis']);
    }

    public function testRunProgressUsesSingularConflictLabel(): void
    {
        $store = new RunProgressStore();
        $reflection = new ReflectionClass($store);
        $method = $reflection->getMethod('summarizeStepResult');
        $method->setAccessible(true);

        $summary = $method->invoke($store, [
            'negotiation_type' => 'single_round_structured_cross_examination',
            'summary' => [
                'total_conflict_count' => 1,
                'critical_conflict_count' => 0,
                'material_conflict_count' => 1,
            ],
        ]);

        $this->assertSame('1 conflict detected: 0 critical, 1 material', $summary);
    }
}
