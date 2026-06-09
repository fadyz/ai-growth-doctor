<?php

namespace App\Services\AiGrowthDoctor;

class AiGrowthDoctorGraphBuilder
{
    private $appProfileService;
    private $metricMappingService;
    private $genericMetricMapperService;

    public function __construct(
        AppProfileService $appProfileService,
        MetricMappingService $metricMappingService,
        GenericMetricMapperService $genericMetricMapperService
    ) {
        $this->appProfileService = $appProfileService;
        $this->metricMappingService = $metricMappingService;
        $this->genericMetricMapperService = $genericMetricMapperService;
    }

    public function build(array $run): array
    {
        $result = $run['result'] ?? $run;
        $runId = $run['run_id'] ?? ($result['meta']['run_id'] ?? null);
        $agents = $result['agents'] ?? [];
        $structuredNegotiation = $result['structured_negotiation'] ?? ($agents['structured_negotiation']['result'] ?? []);
        $finalDecision = $agents['ai_final_decision_agent']['result']
            ?? ($agents['ai_decision_agent']['result'] ?? []);
        $scenarioSimulator = $agents['decision_scenario_simulator']['result'] ?? [];
        $metrics = $result['metrics'] ?? [];
        $guardrail = $metrics['guardrail_policy'] ?? [];
        $steps = $run['steps'] ?? ($result['workflow'] ?? []);
        $mappingContext = $this->resolveMappingContext($run, $result, $metrics);
        $appProfile = $mappingContext['app_profile'] ?? [];
        $mappingValidation = $mappingContext['mapping_validation'] ?? [];

        $nodes = [
            $this->pipelineNode('checkpoint_load', 0, 260, 'Checkpoint Load', 'Run JSON input', $this->stepStatus($steps, 'checkpoint_load', $run['status'] ?? 'done'), 'loaded', 'Existing run JSON loaded', 'checkpoint', 'steps.checkpoint_load'),
            $this->pipelineNode('metrics_extraction', 260, 260, 'Metrics Extraction', 'Deterministic metrics', $this->stepStatus($steps, 'metrics_extraction'), 'extracted', 'Shared metrics context built', 'deterministic', 'steps.metrics_extraction'),
            $this->mappingNode('app_data_mapping', 520, 260, $appProfile, $mappingValidation),
            $this->pipelineNode(
                'guardrail_context',
                700,
                260,
                'Guardrail & Safe Context',
                'Policy constraints',
                !empty($guardrail) ? 'done' : 'empty',
                $guardrail['winning_guardrail'] ?? ($guardrail['deterministic_decision']['winning_guardrail'] ?? 'safe context'),
                $this->guardrailSummary($guardrail),
                'guardrail',
                'guardrail_context'
            ),
            $this->agentNode('activation_agent', 980, 20, 'Activation Agent', 'activation', $agents['ai_activation_agent'] ?? [], 'agents.ai_activation_agent'),
            $this->agentNode('retention_agent', 980, 150, 'Retention Agent', 'retention', $agents['ai_retention_agent'] ?? [], 'agents.ai_retention_agent'),
            $this->agentNode('monetization_agent', 980, 280, 'Monetization Agent', 'monetization', $agents['ai_monetization_agent'] ?? [], 'agents.ai_monetization_agent'),
            $this->agentNode('version_agent', 980, 410, 'Version Agent', 'version', $agents['ai_version_agent'] ?? [], 'agents.ai_version_agent'),
            $this->agentNode('ads_agent', 980, 540, 'Ads Agent', 'ads', $agents['ai_ads_agent'] ?? [], 'agents.ai_ads_agent'),
            $this->agentNode('tomorrow_forecast_agent', 980, 670, 'Tomorrow Forecast Agent', 'forecast', $agents['ai_tomorrow_forecast_agent'] ?? [], 'agents.ai_tomorrow_forecast_agent'),
            $this->negotiationNode('structured_negotiation', 1340, 260, $structuredNegotiation),
            $this->pipelineNode('orchestrator_evidence_assembly', 1640, 260, 'Orchestrator Evidence Assembly', 'Decision package', !empty($result['orchestrator_evidence_assembly']) ? 'done' : 'empty', 'evidence', 'Conflict-aware package assembled', 'orchestrator', 'orchestrator_evidence_assembly'),
            $this->decisionNode('final_decision_agent', 1940, 260, $finalDecision),
            $this->outputNode('decision_scenario_simulator', 2240, 260, $scenarioSimulator),
        ];

        return [
            'run_id' => $runId,
            'title' => 'AI Growth Doctor Agent Society Graph',
            'workflow_mode' => $structuredNegotiation['execution']['mode'] ?? ($result['meta']['architecture'] ?? 'unknown'),
            'nodes' => $nodes,
            'edges' => $this->edges(),
            'details' => [
                'steps' => $steps,
                'metrics' => $metrics,
                'app_data_mapping' => [
                    'app_profile' => $appProfile,
                    'metric_mapping' => $mappingContext['metric_mapping'] ?? [],
                    'mapping_validation' => $mappingValidation,
                    'generic_metrics_context' => $mappingContext['generic_metrics_context'] ?? [],
                    'source_metric_refs' => $mappingContext['source_metric_refs'] ?? [],
                ],
                'guardrail_context' => $guardrail,
                'agents' => $agents,
                'structured_negotiation' => $structuredNegotiation,
                'orchestrator_evidence_assembly' => $result['orchestrator_evidence_assembly'] ?? [],
                'final_decision' => $finalDecision,
                'scenario_simulator' => $scenarioSimulator,
                'interaction_log' => $result['interaction_log'] ?? [],
            ],
            'summary' => $this->summary($run, $result, $structuredNegotiation, $finalDecision),
        ];
    }

    private function resolveMappingContext(array $run, array $result, array $metrics): array
    {
        if (!empty($result['app_profile']) || !empty($result['generic_metrics_context'])) {
            return [
                'app_profile' => $result['app_profile'] ?? [],
                'metric_mapping' => $result['metric_mapping'] ?? [],
                'generic_metrics_context' => $result['generic_metrics_context'] ?? [],
                'mapping_validation' => $result['mapping_validation'] ?? [],
                'source_metric_refs' => $result['source_metric_refs'] ?? [],
            ];
        }

        $sourceMetricsContext = [
            'activation_metrics' => $metrics['activation_metrics'] ?? [],
            'retention_metrics' => $metrics['retention_metrics'] ?? [],
            'monetization_metrics' => $metrics['monetization_metrics'] ?? [],
            'version_metrics' => $metrics['version_metrics'] ?? [],
            'ads_metrics' => $metrics['ads_metrics'] ?? [],
            'tomorrow_forecast_metrics' => $metrics['tomorrow_forecast_metrics'] ?? [],
            'rule_based_decision' => $metrics['rule_based_decision'] ?? [],
        ];

        $appProfile = $this->appProfileService->resolve($result);
        $metricMapping = $this->metricMappingService->resolve($result, $appProfile);

        return $this->genericMetricMapperService->buildGenericContext($sourceMetricsContext, $metricMapping, $appProfile);
    }

    private function pipelineNode(string $id, int $x, int $y, string $title, string $subtitle, string $status, string $badge, string $summary, string $category, string $detailKey): array
    {
        return [
            'id' => $id,
            'type' => 'pipelineNode',
            'position' => ['x' => $x, 'y' => $y],
            'data' => compact('title', 'subtitle', 'status', 'badge', 'summary', 'category', 'detailKey'),
        ];
    }

    private function agentNode(string $id, int $x, int $y, string $title, string $domain, array $agent, string $detailKey): array
    {
        $result = $agent['result'] ?? [];
        $status = $result['status'] ?? ($result['prediction_status'] ?? ($agent['status'] ?? 'empty'));
        $cache = $agent['cache'] ?? [];

        return [
            'id' => $id,
            'type' => 'agentNode',
            'position' => ['x' => $x, 'y' => $y],
            'data' => [
                'title' => $title,
                'domain' => $domain,
                'status' => $status,
                'cacheHit' => $cache['hit'] ?? ($agent['cache_hit'] ?? false),
                'summary' => $this->firstText($result, ['diagnosis', 'main_diagnosis', 'executive_summary', 'summary', 'ads_verdict']),
                'confidence' => $result['confidence_score'] ?? null,
                'model' => $agent['model'] ?? null,
                'detailKey' => $detailKey,
                'highlight' => $this->agentHighlight($title, $result),
            ],
        ];
    }

    private function mappingNode(string $id, int $x, int $y, array $appProfile, array $mappingValidation): array
    {
        $missingRequired = $mappingValidation['missing_required_metrics'] ?? [];
        $warnings = array_merge(
            $mappingValidation['data_quality_warnings'] ?? [],
            $mappingValidation['low_sample_warnings'] ?? [],
            $mappingValidation['missing_optional_metrics'] ?? []
        );

        return [
            'id' => $id,
            'type' => 'pipelineNode',
            'position' => ['x' => $x, 'y' => $y],
            'data' => [
                'title' => 'App Data Mapping',
                'subtitle' => $appProfile['app_name'] ?? 'Demo Mobile App',
                'status' => $mappingValidation['status'] ?? 'unknown',
                'badge' => ($mappingValidation['mapped_metric_count'] ?? 0) . ' mapped',
                'summary' => 'Core action: ' . ($appProfile['core_action_name'] ?? 'core action') . '. Missing required: ' . count($missingRequired) . '. Warnings: ' . count($warnings) . '.',
                'category' => 'metric contract',
                'detailKey' => 'app_data_mapping',
            ],
        ];
    }

    private function negotiationNode(string $id, int $x, int $y, array $negotiation): array
    {
        $summary = $negotiation['summary'] ?? [];
        $execution = $negotiation['execution'] ?? [];

        return [
            'id' => $id,
            'type' => 'negotiationNode',
            'position' => ['x' => $x, 'y' => $y],
            'data' => [
                'title' => 'Single-Round Structured Negotiation',
                'maxRounds' => $negotiation['rules']['max_rounds'] ?? ($execution['max_rounds'] ?? 1),
                'totalConflictCount' => $summary['total_conflict_count'] ?? ($execution['total_conflict_count'] ?? count($negotiation['conflicts'] ?? [])),
                'materialConflictCount' => $summary['material_conflict_count'] ?? ($execution['material_conflict_count'] ?? 0),
                'criticalConflictCount' => $summary['critical_conflict_count'] ?? ($execution['critical_conflict_count'] ?? 0),
                'agentResponseCount' => $execution['agent_response_count'] ?? count($negotiation['agent_responses'] ?? []),
                'resultSummary' => $summary['recommended_next_step'] ?? 'Conflict matrix prepared',
                'status' => !empty($negotiation) ? 'done' : 'empty',
                'detailKey' => 'structured_negotiation',
            ],
        ];
    }

    private function decisionNode(string $id, int $x, int $y, array $decision): array
    {
        return [
            'id' => $id,
            'type' => 'decisionNode',
            'position' => ['x' => $x, 'y' => $y],
            'data' => [
                'title' => 'Final Decision Agent',
                'businessVerdict' => $decision['business_verdict'] ?? 'No verdict',
                'confidence' => $decision['confidence_score'] ?? null,
                'summary' => $decision['today_operator_summary'] ?? ($decision['top_priority'] ?? ($decision['executive_summary'] ?? 'No decision summary available')),
                'status' => !empty($decision) ? 'done' : 'empty',
                'detailKey' => 'final_decision',
            ],
        ];
    }

    private function outputNode(string $id, int $x, int $y, array $simulator): array
    {
        return [
            'id' => $id,
            'type' => 'outputNode',
            'position' => ['x' => $x, 'y' => $y],
            'data' => [
                'title' => 'Decision Scenario Simulator',
                'summary' => $this->scenarioSummary($simulator),
                'baseline' => $simulator['baseline_without_intervention'] ?? null,
                'recommended' => $simulator['recommended_intervention'] ?? null,
                'status' => !empty($simulator) ? 'done' : 'empty',
                'detailKey' => 'scenario_simulator',
            ],
        ];
    }

    private function edges(): array
    {
        $specialists = ['activation_agent', 'retention_agent', 'monetization_agent', 'version_agent', 'ads_agent', 'tomorrow_forecast_agent'];
        $edges = [
            $this->edge('checkpoint-to-metrics', 'checkpoint_load', 'metrics_extraction', 'metrics', 'deterministic-edge'),
            $this->edge('metrics-to-mapping', 'metrics_extraction', 'app_data_mapping', 'metric contract', 'deterministic-edge'),
            $this->edge('mapping-to-guardrail', 'app_data_mapping', 'guardrail_context', 'generic metrics', 'deterministic-edge'),
        ];

        foreach ($specialists as $specialist) {
            $edges[] = $this->edge('guardrail-to-' . $specialist, 'guardrail_context', $specialist, 'specialist analysis', 'agent-edge');
            $edges[] = $this->edge($specialist . '-to-negotiation', $specialist, 'structured_negotiation', 'single-round negotiation', 'negotiation-edge');
        }

        $edges[] = $this->edge('negotiation-to-orchestrator', 'structured_negotiation', 'orchestrator_evidence_assembly', 'decision package', 'decision-edge');
        $edges[] = $this->edge('orchestrator-to-final', 'orchestrator_evidence_assembly', 'final_decision_agent', 'final decision', 'decision-edge');
        $edges[] = $this->edge('final-to-simulator', 'final_decision_agent', 'decision_scenario_simulator', 'scenario comparison', 'output-edge');

        return $edges;
    }

    private function edge(string $id, string $source, string $target, string $label, string $className): array
    {
        return compact('id', 'source', 'target', 'label', 'className') + [
            'type' => 'smoothstep',
            'animated' => false,
        ];
    }

    private function summary(array $run, array $result, array $negotiation, array $decision): array
    {
        $negotiationSummary = $negotiation['summary'] ?? [];
        $execution = $negotiation['execution'] ?? [];
        $calibration = $result['evaluations']['forecast_model_calibration'] ?? [];
        $baseline = $negotiation['baseline_comparison']['agent_society'] ?? [];

        return [
            'status' => $run['status'] ?? 'done',
            'agent_count' => 6,
            'total_conflict_count' => $negotiationSummary['total_conflict_count'] ?? ($execution['total_conflict_count'] ?? count($negotiation['conflicts'] ?? [])),
            'material_conflict_count' => $negotiationSummary['material_conflict_count'] ?? ($execution['material_conflict_count'] ?? 0),
            'critical_conflict_count' => $negotiationSummary['critical_conflict_count'] ?? ($execution['critical_conflict_count'] ?? 0),
            'business_verdict' => $decision['business_verdict'] ?? null,
            'forecast_trust_score' => $calibration['trust_score']['updated_score'] ?? null,
            'unsafe_recommendation_prevented' => $baseline['unsafe_recommendation_prevented'] ?? null,
        ];
    }

    private function stepStatus($steps, string $key, string $fallback = 'done'): string
    {
        if (!is_array($steps)) {
            return $fallback;
        }

        return $steps[$key]['status'] ?? $steps[$key] ?? $fallback;
    }

    private function firstText(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (!empty($data[$key])) {
                return is_scalar($data[$key]) ? (string) $data[$key] : json_encode($data[$key], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return 'No data available';
    }

    private function guardrailSummary(array $guardrail): string
    {
        $triggered = $guardrail['triggered_guardrails'] ?? [];
        $winningGuardrail = $guardrail['winning_guardrail'] ?? ($guardrail['deterministic_decision']['winning_guardrail'] ?? null);
        $businessVerdict = $guardrail['deterministic_decision']['business_verdict'] ?? null;

        if ($winningGuardrail && isset($triggered[$winningGuardrail])) {
            $active = $triggered[$winningGuardrail];
            $reasonCodes = $active['reason_codes'] ?? [];
            $reasonText = is_array($reasonCodes) && !empty($reasonCodes)
                ? ' Reasons: ' . implode(', ', $reasonCodes) . '.'
                : '';

            return trim(($businessVerdict ? $businessVerdict . '. ' : '') . $winningGuardrail . ' triggered.' . $reasonText);
        }

        return $businessVerdict ?? 'Guardrail context prepared';
    }

    private function scenarioSummary(array $simulator): string
    {
        $recommendedAction = $simulator['recommended_intervention']['action'] ?? null;

        if ($recommendedAction) {
            return (string) $recommendedAction;
        }

        $scenarioSummary = $simulator['scenario_with_intervention']['summary'] ?? null;

        if ($scenarioSummary) {
            return (string) $scenarioSummary;
        }

        $mainDifference = $simulator['baseline_vs_intervention_comparison']['main_difference'] ?? null;

        if ($mainDifference) {
            return (string) $mainDifference;
        }

        return $simulator['human_review_note'] ?? 'No data available';
    }

    private function agentHighlight(string $title, array $result): ?string
    {
        if ($title === 'Ads Agent') {
            return 'material-conflict';
        }

        if ($title === 'Retention Agent') {
            return 'guardrail';
        }

        if ($title === 'Tomorrow Forecast Agent') {
            $summary = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return strpos($summary, 'low_trust') !== false || strpos($summary, 'directional_signal_only') !== false ? 'low-trust' : null;
        }

        return null;
    }
}
