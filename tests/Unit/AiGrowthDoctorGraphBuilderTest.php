<?php

namespace Tests\Unit;

use App\Services\AiGrowthDoctor\AiGrowthDoctorGraphBuilder;
use App\Services\AiGrowthDoctor\AppProfileService;
use App\Services\AiGrowthDoctor\GenericMetricMapperService;
use App\Services\AiGrowthDoctor\MappingValidationService;
use App\Services\AiGrowthDoctor\MetricMappingService;
use Tests\TestCase;

class AiGrowthDoctorGraphBuilderTest extends TestCase
{
    public function testStructuredNegotiationGraphUsesRunRoundStateAndSingleOutgoingOrchestratorEdge(): void
    {
        $builder = new AiGrowthDoctorGraphBuilder(
            new AppProfileService(),
            new MetricMappingService(),
            new GenericMetricMapperService(new MappingValidationService())
        );

        $graph = $builder->build([
            'run_id' => 'demo',
            'status' => 'done',
            'result' => [
                'structured_negotiation' => [
                    'round' => 2,
                    'rounds_completed' => 2,
                    'summary' => [
                        'total_conflict_count' => 1,
                        'material_conflict_count' => 0,
                        'critical_conflict_count' => 0,
                        'material_or_higher_conflict_count' => 0,
                        'unresolved_material_or_higher_conflict_count' => 0,
                        'resolved_material_tension_count' => 2,
                        'minor_bounded_tension_count' => 1,
                    ],
                    'ui_summary' => [
                        'unresolved_hard_conflict_count' => 0,
                        'resolved_material_tension_count' => 2,
                        'minor_bounded_caution_count' => 1,
                        'rounds_completed' => 2,
                        'rounds_supported' => 3,
                        'round_2_status' => 'skipped_by_policy',
                        'round_2_skip_reason' => 'All material tensions were resolved in Round 1.',
                    ],
                    'rules' => [
                        'max_rounds' => 3,
                    ],
                    'execution' => [
                        'mode' => 'deterministic_adaptive_bounded_negotiation',
                        'max_rounds' => 3,
                        'rounds_completed' => 2,
                        'early_exit' => true,
                        'early_exit_reason' => 'no_material_conflicts_remaining',
                        'agent_response_count' => 4,
                        'material_or_higher_conflict_count' => 0,
                    ],
                    'round_summaries' => [
                        [
                            'round' => 1,
                            'purpose' => 'Objection / Support / Risk Warning',
                            'status' => 'completed',
                            'turn_count' => 4,
                            'material_or_higher_conflict_count_after_round' => 1,
                            'skip_reason' => null,
                        ],
                        [
                            'round' => 2,
                            'purpose' => 'Revision / Rebuttal',
                            'status' => 'completed',
                            'turn_count' => 1,
                            'material_or_higher_conflict_count_after_round' => 0,
                            'skip_reason' => null,
                        ],
                        [
                            'round' => 3,
                            'purpose' => 'Escalation Only',
                            'status' => 'skipped',
                            'turn_count' => 0,
                            'material_or_higher_conflict_count_after_round' => 0,
                            'skip_reason' => 'Early Exit: material tensions were resolved and no unresolved material or critical conflict remained.',
                        ],
                    ],
                ],
            ],
        ]);

        $negotiationNode = current(array_filter($graph['nodes'], function (array $node) {
            return $node['id'] === 'structured_negotiation';
        }));
        $outgoingNegotiationEdges = array_values(array_filter($graph['edges'], function (array $edge) {
            return $edge['source'] === 'structured_negotiation';
        }));

        $this->assertSame('Structured Negotiation Layer', $negotiationNode['data']['title']);
        $this->assertSame(3, $negotiationNode['data']['maxRounds']);
        $this->assertSame(2, $negotiationNode['data']['completedRoundCount']);
        $this->assertSame('completed', $negotiationNode['data']['rounds'][0]['status']);
        $this->assertSame('Objection / Support / Risk Warning', $negotiationNode['data']['rounds'][0]['name']);
        $this->assertSame('completed', $negotiationNode['data']['rounds'][1]['status']);
        $this->assertSame('Revision / Rebuttal', $negotiationNode['data']['rounds'][1]['name']);
        $this->assertSame('skipped', $negotiationNode['data']['rounds'][2]['status']);
        $this->assertSame('Escalation Only', $negotiationNode['data']['rounds'][2]['name']);
        $this->assertSame('unresolved_hard_conflict_count = 0', $negotiationNode['data']['earlyExit']['condition']);
        $this->assertSame(0, $graph['summary']['unresolved_hard_conflict_count']);
        $this->assertSame(2, $graph['summary']['resolved_material_tension_count']);
        $this->assertSame(1, $graph['summary']['minor_bounded_caution_count']);
        $this->assertSame('clear', $negotiationNode['data']['earlyExit']['status']);
        $this->assertCount(1, $outgoingNegotiationEdges);
        $this->assertSame('orchestrator_evidence_assembly', $outgoingNegotiationEdges[0]['target']);
    }
}
