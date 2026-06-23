<?php

namespace App\Services\GrowthDoctor;

use App\Services\Ai\FinalDecisionContextBuilder;
use App\Services\GrowthDoctor\Agents\ActivationAgent;
use App\Services\GrowthDoctor\Agents\AiAdsAgent;
use App\Services\GrowthDoctor\Agents\FinalDecisionAgent;
use App\Services\GrowthDoctor\Agents\DecisionScenarioSimulator;
use App\Services\GrowthDoctor\Agents\AiAgentClient;
use App\Services\GrowthDoctor\Agents\MonetizationAgent;
use App\Services\GrowthDoctor\Agents\RetentionAgent;
use App\Services\GrowthDoctor\Agents\TomorrowForecastAgent;
use App\Services\GrowthDoctor\Agents\VersionAgent;
use App\Services\GrowthDoctor\RunProgressStore;
use Illuminate\Support\Facades\Log;

class AgentOrchestrator
{
    private $activationAgent;
    private $retentionAgent;
    private $monetizationAgent;
    private $versionAgent;
    private $adsAgent;
    private $tomorrowForecastAgent;
    private $finalDecisionAgent;
    private $decisionScenarioSimulator;
    private $aiAgentClient;
    private $runProgressStore;
    private $structuredNegotiationService;
    private $baselineComparisonService;
    private $finalDecisionContextBuilder;
    private $finalDecisionEnrichmentService;

    public function __construct(
        ActivationAgent $activationAgent,
        RetentionAgent $retentionAgent,
        MonetizationAgent $monetizationAgent,
        VersionAgent $versionAgent,
        AiAdsAgent $adsAgent,
        TomorrowForecastAgent $tomorrowForecastAgent,
        FinalDecisionAgent $finalDecisionAgent,
        DecisionScenarioSimulator $decisionScenarioSimulator,
        AiAgentClient $aiAgentClient,
        RunProgressStore $runProgressStore,
        StructuredNegotiationService $structuredNegotiationService,
        AgentSocietyBaselineComparisonService $baselineComparisonService,
        FinalDecisionContextBuilder $finalDecisionContextBuilder,
        FinalDecisionEnrichmentService $finalDecisionEnrichmentService
    ) {
        $this->activationAgent = $activationAgent;
        $this->retentionAgent = $retentionAgent;
        $this->monetizationAgent = $monetizationAgent;
        $this->versionAgent = $versionAgent;
        $this->adsAgent = $adsAgent;
        $this->tomorrowForecastAgent = $tomorrowForecastAgent;
        $this->finalDecisionAgent = $finalDecisionAgent;
        $this->decisionScenarioSimulator = $decisionScenarioSimulator;
        $this->aiAgentClient = $aiAgentClient;
        $this->runProgressStore = $runProgressStore;
        $this->structuredNegotiationService = $structuredNegotiationService;
        $this->baselineComparisonService = $baselineComparisonService;
        $this->finalDecisionContextBuilder = $finalDecisionContextBuilder;
        $this->finalDecisionEnrichmentService = $finalDecisionEnrichmentService;
    }

    public function run(array $metricsContext, ?string $trackedRunId = null): array
    {
        $runId = $trackedRunId ?: $this->makeRunId();
        $isTracked = $trackedRunId !== null;
        $interactionLog = [];
        $agentRequestMetrics = [];
        $genericSharedContext = ['app_profile', 'generic_metrics_context', 'mapping_validation', 'source_metric_refs', 'source_metrics_context'];

        $specialistRequestDefinitions = [
            'ai_activation_agent' => [
                'label' => 'AI Activation Agent',
                'step_key' => 'activation_agent',
                'running_note' => 'Activation Agent is analyzing activation bottlenecks.',
                'done_note' => 'Activation Agent completed.',
                'shared_context' => array_merge(['checkpoint_meta', 'activation_metrics', 'version_metrics'], $genericSharedContext),
                'request' => $this->withRequestMeta($this->activationAgent->buildRequest($metricsContext), $runId, array_merge(['checkpoint_meta', 'activation_metrics', 'version_metrics'], $genericSharedContext)),
            ],
            'ai_retention_agent' => [
                'label' => 'AI Retention Agent',
                'step_key' => 'retention_agent',
                'running_note' => 'Retention Agent is analyzing D0, D1, and early habit risk.',
                'done_note' => 'Retention Agent completed.',
                'shared_context' => array_merge(['checkpoint_meta', 'retention_metrics'], $genericSharedContext),
                'request' => $this->withRequestMeta($this->retentionAgent->buildRequest($metricsContext), $runId, array_merge(['checkpoint_meta', 'retention_metrics'], $genericSharedContext)),
            ],
            'ai_monetization_agent' => [
                'label' => 'AI Monetization Agent',
                'step_key' => 'monetization_agent',
                'running_note' => 'Monetization Agent is analyzing paywall and purchase signal.',
                'done_note' => 'Monetization Agent completed.',
                'shared_context' => array_merge(['checkpoint_meta', 'monetization_metrics', 'activation_metrics', 'version_metrics'], $genericSharedContext),
                'request' => $this->withRequestMeta($this->monetizationAgent->buildRequest($metricsContext), $runId, array_merge(['checkpoint_meta', 'monetization_metrics', 'activation_metrics', 'version_metrics'], $genericSharedContext)),
            ],
            'ai_version_agent' => [
                'label' => 'AI Version Agent',
                'step_key' => 'version_agent',
                'running_note' => 'Version Agent is analyzing release and version risk.',
                'done_note' => 'Version Agent completed.',
                'shared_context' => array_merge(['checkpoint_meta', 'version_metrics', 'activation_metrics', 'monetization_metrics'], $genericSharedContext),
                'request' => $this->withRequestMeta($this->versionAgent->buildRequest($metricsContext), $runId, array_merge(['checkpoint_meta', 'version_metrics', 'activation_metrics', 'monetization_metrics'], $genericSharedContext)),
            ],
            'ai_ads_agent' => [
                'label' => 'AI Ads Agent',
                'step_key' => 'ads_agent',
                'running_note' => 'Ads Agent is analyzing acquisition cost, campaign lifecycle, and reset campaign context.',
                'done_note' => 'Ads Agent completed.',
                'shared_context' => array_merge(['checkpoint_meta', 'ads_metrics', 'activation_metrics', 'retention_metrics', 'rule_based_decision'], $genericSharedContext),
                'request' => $this->withRequestMeta($this->adsAgent->buildRequest($metricsContext), $runId, array_merge(['checkpoint_meta', 'ads_metrics', 'activation_metrics', 'retention_metrics', 'rule_based_decision'], $genericSharedContext)),
            ],
            'ai_tomorrow_forecast_agent' => [
                'label' => 'Tomorrow Forecast Agent',
                'step_key' => 'tomorrow_forecast_agent',
                'running_note' => 'Tomorrow Forecast Agent is interpreting quantitative forecast and next-day risk.',
                'done_note' => 'Tomorrow Forecast Agent completed.',
                'shared_context' => ['checkpoint_meta', 'tomorrow_forecast_metrics', 'forecast_evaluation_summary', 'forecast_calibration_summary', 'activation_compact', 'retention_compact', 'monetization_compact', 'guardrail_compact'],
                'request' => $this->withRequestMeta(
                    $this->tomorrowForecastAgent->buildRequest($metricsContext),
                    $runId,
                    ['checkpoint_meta', 'tomorrow_forecast_metrics', 'forecast_evaluation_summary', 'forecast_calibration_summary', 'activation_compact', 'retention_compact', 'monetization_compact', 'guardrail_compact'],
                    ['context_mode' => 'compact']
                ),
            ],
        ];

        foreach ($specialistRequestDefinitions as $definition) {
            $this->logInteraction($interactionLog, $runId, 'orchestrator', $definition['label'], 'agent_request', [
                'shared_context' => $definition['shared_context'],
                'execution_mode' => 'parallel_fan_out',
            ]);
            $this->markRunningIfTracked($isTracked, $runId, $definition['step_key'], $definition['running_note']);
        }

        $completedSpecialistKeys = [];

        try {
            $parallelRequests = [];

            foreach ($specialistRequestDefinitions as $key => $definition) {
                $parallelRequests[$key] = $definition['request'];
            }

            $specialistAgents = $this->aiAgentClient->callMany(
                $parallelRequests,
                function (string $key, array $agentOutput) use (&$interactionLog, &$completedSpecialistKeys, &$agentRequestMetrics, $runId, $isTracked, $specialistRequestDefinitions, $metricsContext) {
                    if (!isset($specialistRequestDefinitions[$key])) {
                        return;
                    }

                    if (isset($completedSpecialistKeys[$key])) {
                        return;
                    }

                    $definition = $specialistRequestDefinitions[$key];
                    if ($key === 'ai_tomorrow_forecast_agent') {
                        $agentOutput = $this->tomorrowForecastAgent->applyDeterministicFallback($agentOutput, $metricsContext);
                    }

                    $agentRequestMetrics[$key] = $this->extractAgentRequestMetrics($agentOutput);
                    $completedSpecialistKeys[$key] = true;

                    $this->markDoneIfTracked($isTracked, $runId, $definition['step_key'], $agentOutput, $definition['done_note']);
                    $this->logAgentResponse($interactionLog, $runId, $definition['label'], $agentOutput);
                }
            );
        } catch (\Throwable $e) {
            foreach ($specialistRequestDefinitions as $definition) {
                $this->markFailedIfTracked($isTracked, $runId, $definition['step_key'], $e);
            }
            throw $e;
        }

        foreach ($specialistRequestDefinitions as $key => $definition) {
            $agentOutput = $specialistAgents[$key] ?? [
                'agent' => $definition['label'],
                'status' => 'missing_result',
                'error' => 'Parallel specialist result missing.',
            ];

            if ($key === 'ai_tomorrow_forecast_agent') {
                $agentOutput = $this->tomorrowForecastAgent->applyDeterministicFallback($agentOutput, $metricsContext);
            }

            $agentOutput = $this->normalizeGenericAgentOutput($agentOutput);
            $specialistAgents[$key] = $agentOutput;
            $agentRequestMetrics[$key] = $this->extractAgentRequestMetrics($agentOutput);

            if (!isset($completedSpecialistKeys[$key])) {
                $completedSpecialistKeys[$key] = true;
                $this->markDoneIfTracked($isTracked, $runId, $definition['step_key'], $agentOutput, $definition['done_note']);
                $this->logAgentResponse($interactionLog, $runId, $definition['label'], $agentOutput);
            }
        }

        $aiActivationAgent = $specialistAgents['ai_activation_agent'];
        $aiRetentionAgent = $specialistAgents['ai_retention_agent'];
        $aiMonetizationAgent = $specialistAgents['ai_monetization_agent'];
        $aiVersionAgent = $specialistAgents['ai_version_agent'];
        $aiAdsAgent = $specialistAgents['ai_ads_agent'];
        $aiTomorrowForecastAgent = $specialistAgents['ai_tomorrow_forecast_agent'];

        $forecastEvaluations = $metricsContext['forecast_evaluations']
            ?? ($metricsContext['evaluations']['forecast_evaluations'] ?? []);

        $forecastCalibration = $metricsContext['forecast_model_calibration']
            ?? ($metricsContext['evaluations']['forecast_model_calibration'] ?? []);

        if (!empty($forecastEvaluations)) {
            $this->logInteraction($interactionLog, $runId, 'Forecast Evaluation Service', 'AI Final Decision Agent', 'evaluation_output_shared', [
                'status' => $forecastEvaluations['status'] ?? null,
                'actual_data_available_until' => $forecastEvaluations['actual_data_available_until'] ?? null,
                'evaluated_count' => $forecastEvaluations['evaluated_count'] ?? 0,
                'pending_count' => $forecastEvaluations['pending_count'] ?? 0,
                'skipped_count' => $forecastEvaluations['skipped_count'] ?? 0,
                'latest_quality' => $this->extractLatestForecastEvaluationQuality($forecastEvaluations),
            ]);
        }

        if (!empty($forecastCalibration)) {
            $this->logInteraction($interactionLog, $runId, 'Forecast Calibration Service', 'AI Final Decision Agent', 'calibration_output_shared', [
                'status' => $forecastCalibration['status'] ?? null,
                'evaluations_used' => $forecastCalibration['evaluations_used'] ?? 0,
                'overall_mature_hit_rate' => $forecastCalibration['overall_mature_hit_rate'] ?? null,
                'trust_score' => $forecastCalibration['trust_score']['updated_score'] ?? null,
                'trust_interpretation' => $forecastCalibration['trust_score']['interpretation'] ?? null,
                'forecast_role' => $forecastCalibration['decision_instruction']['forecast_role'] ?? null,
                'systematic_bias_detected' => $forecastCalibration['bias_detection']['systematic_bias_detected'] ?? null,
            ]);
        }

        foreach ($specialistAgents as $key => $agentOutput) {
            $this->logInteraction($interactionLog, $runId, $agentOutput['agent'] ?? $key, 'AI Final Decision Agent', 'agent_output_shared', [
                'source_key' => $key,
                'status' => $agentOutput['status'] ?? null,
                'result_status' => $agentOutput['result']['status'] ?? null,
                'decision_usable' => $agentOutput['decision_usable'] ?? ($agentOutput['result']['decision_usable'] ?? null),
                'llm_error' => $agentOutput['llm_error'] ?? ($agentOutput['result']['llm_error'] ?? null),
                'execution_mode' => $agentOutput['execution']['mode'] ?? null,
                'parallel_pool' => $agentOutput['execution']['parallel_pool'] ?? null,
                'summary' => $this->extractAgentSummary($agentOutput),
            ]);
        }

        $this->markRunningIfTracked($isTracked, $runId, 'structured_negotiation', 'Structured Negotiation is running adaptive bounded cross-examination.');
        $structuredNegotiation = $this->structuredNegotiationService->run([
            'metrics_context' => $metricsContext,
            'guardrail_result' => $metricsContext['guardrail_policy'] ?? [],
            'specialist_outputs' => $specialistAgents,
            'forecast_evaluation' => $forecastEvaluations,
            'calibration_memory' => $forecastCalibration,
            'max_rounds' => 3,
        ]);
        $this->markDoneIfTracked($isTracked, $runId, 'structured_negotiation', $structuredNegotiation, 'Structured Negotiation completed.');

        foreach (($structuredNegotiation['agent_responses'] ?? []) as $response) {
            $this->logInteraction($interactionLog, $runId, $response['agent_name'] ?? 'Specialist Agent', 'Structured Negotiation Service', 'negotiation_' . ($response['response_type'] ?? 'response'), [
                'target_agent' => $response['target_agent'] ?? null,
                'severity' => $response['severity'] ?? null,
                'claim' => $response['claim'] ?? null,
                'evidence_refs' => $response['evidence_refs'] ?? [],
                'revised_recommendation' => $response['revised_recommendation'] ?? null,
            ]);
        }

        $this->logInteraction($interactionLog, $runId, 'Structured Negotiation Service', 'orchestrator', 'negotiation_output', [
            'round' => $structuredNegotiation['round'] ?? null,
            'rounds_completed' => $structuredNegotiation['execution']['rounds_completed'] ?? ($structuredNegotiation['rounds_completed'] ?? null),
            'negotiation_type' => $structuredNegotiation['negotiation_type'] ?? null,
            'execution_mode' => $structuredNegotiation['execution']['mode'] ?? null,
            'early_exit' => $structuredNegotiation['execution']['early_exit'] ?? null,
            'early_exit_reason' => $structuredNegotiation['execution']['early_exit_reason'] ?? null,
            'agent_response_count' => count($structuredNegotiation['agent_responses'] ?? []),
            'total_conflict_count' => $structuredNegotiation['summary']['total_conflict_count'] ?? count($structuredNegotiation['conflicts'] ?? []),
            'material_conflict_count' => $structuredNegotiation['summary']['material_conflict_count'] ?? 0,
            'critical_conflict_count' => $structuredNegotiation['summary']['critical_conflict_count'] ?? 0,
            'material_or_higher_conflict_count' => $structuredNegotiation['summary']['material_or_higher_conflict_count'] ?? 0,
            'resolved_material_tension_count' => $structuredNegotiation['summary']['resolved_material_tension_count'] ?? 0,
            'minor_bounded_tension_count' => $structuredNegotiation['summary']['minor_bounded_tension_count'] ?? 0,
            'minor_bounded_caution_count' => $structuredNegotiation['summary']['minor_bounded_caution_count'] ?? ($structuredNegotiation['summary']['minor_bounded_tension_count'] ?? 0),
            'conflict_ids' => array_values(array_map(function (array $conflict) {
                return $conflict['conflict_id'] ?? null;
            }, $structuredNegotiation['conflicts'] ?? [])),
        ]);

        $orchestratorEvidenceAssembly = $this->buildOrchestratorEvidenceAssembly(
            $metricsContext,
            $specialistAgents,
            $structuredNegotiation,
            $forecastEvaluations,
            $forecastCalibration
        );

        $contextMode = (string) config('ai_growth_doctor.ai.final_decision_context_mode', 'compact');
        $maxPayloadKb = (int) config('ai_growth_doctor.ai.final_decision_max_payload_kb', 120);
        $maxPayloadBytes = max(1, $maxPayloadKb) * 1024;
        $finalDecisionTimeoutSeconds = (int) config('ai_growth_doctor.ai.final_decision_timeout_seconds', 60);
        $finalDecisionRepairTimeoutSeconds = (int) config('ai_growth_doctor.ai.final_decision_repair_timeout_seconds', 30);
        $finalDecisionRetryEnabled = (bool) config('ai_growth_doctor.ai.final_decision_retry_enabled', true);
        $compactFinalDecisionContext = $this->finalDecisionContextBuilder->buildCompact(
            $metricsContext,
            $specialistAgents,
            $structuredNegotiation,
            [
                'forecast_evaluations' => $forecastEvaluations,
                'forecast_model_calibration' => $forecastCalibration,
            ],
            []
        );
        $compactPayloadBytes = $this->finalDecisionContextBuilder->estimatedPayloadBytes($compactFinalDecisionContext);

        if ($compactPayloadBytes > $maxPayloadBytes) {
            Log::warning('final_decision_payload_too_large', [
                'run_id' => $runId,
                'payload_kb' => round($compactPayloadBytes / 1024, 1),
                'max_payload_kb' => $maxPayloadKb,
                'context_mode' => $contextMode,
            ]);

            $compactFinalDecisionContext = $this->finalDecisionContextBuilder->buildCompact(
                $metricsContext,
                $specialistAgents,
                $structuredNegotiation,
                [
                    'forecast_evaluations' => $forecastEvaluations,
                    'forecast_model_calibration' => $forecastCalibration,
                ],
                [],
                true
            );
            $compactPayloadBytes = $this->finalDecisionContextBuilder->estimatedPayloadBytes($compactFinalDecisionContext);
        }

        $finalDecisionContext = [
            'final_decision_compact_context' => $compactFinalDecisionContext,
            'final_decision_context_mode' => $contextMode,
            'compact_context_payload_bytes' => $compactPayloadBytes,
        ];

        if ($compactPayloadBytes > 45 * 1024) {
            Log::warning('final_decision_prompt_still_too_large', [
                'run_id' => $runId,
                'compact_payload_kb' => round($compactPayloadBytes / 1024, 1),
                'target_payload_kb' => 45,
                'context_mode' => $contextMode,
            ]);
        }

        $this->logInteraction($interactionLog, $runId, 'orchestrator', 'AI Final Decision Agent', 'agent_request', [
            'shared_context' => [
                'final_decision_compact_context',
                'compact_core_metrics',
                'compact_specialist_summaries',
                'compact_structured_negotiation',
                'compact_guardrail_policy',
                'compact_forecast_evaluation',
                'compact_baseline_comparison',
            ],
            'execution_mode' => 'sequential_fan_in_after_parallel_specialists_and_adaptive_bounded_negotiation',
            'final_decision_context_mode' => $contextMode,
            'compact_context_payload_kb' => round($compactPayloadBytes / 1024, 1),
            'context_trace' => $this->buildFinalDecisionContextTrace($finalDecisionContext),
        ]);
        $this->markRunningIfTracked($isTracked, $runId, 'final_decision_agent', 'Final Decision Agent is resolving conflict and producing operating decision.');
        try {
            $aiDecisionAgent = $this->finalDecisionAgent->run($finalDecisionContext, [
                'run_id' => $runId,
                'shared_context_keys' => [
                    'final_decision_compact_context',
                    'compact_core_metrics',
                    'compact_specialist_summaries',
                    'compact_structured_negotiation',
                    'compact_guardrail_policy',
                    'compact_forecast_evaluation',
                    'compact_baseline_comparison',
                ],
                'context_mode' => $contextMode,
                'timeout_seconds' => $finalDecisionTimeoutSeconds,
            ]);
            $aiDecisionAgent = $this->normalizeGenericAgentOutput($aiDecisionAgent);

            if (!$this->isFinalDecisionUsable($aiDecisionAgent)) {
                $firstAttempt = $aiDecisionAgent;
                $missingRequiredFields = $this->missingFinalDecisionFields($firstAttempt);
                $this->logInteraction($interactionLog, $runId, 'AI Final Decision Agent', 'orchestrator', 'schema_validation_failed', [
                    'status' => $firstAttempt['status'] ?? null,
                    'response_status' => $firstAttempt['response_metrics']['status'] ?? null,
                    'missing_required_fields' => $missingRequiredFields,
                    'raw_response' => $firstAttempt['raw_response'] ?? null,
                    'raw_response_type' => $firstAttempt['raw_response_type'] ?? null,
                ]);

                if ($finalDecisionRetryEnabled && $this->shouldRepairFinalDecision($firstAttempt)) {
                    $repairAttempt = $this->finalDecisionAgent->run($finalDecisionContext, [
                        'run_id' => $runId,
                        'shared_context_keys' => [
                            'invalid_raw_response',
                            'missing_required_fields',
                            'compact_core_schema',
                        ],
                        'context_mode' => 'json_repair_only',
                        'timeout_seconds' => $finalDecisionRepairTimeoutSeconds,
                        'json_repair_attempt' => true,
                        'invalid_status' => $firstAttempt['status'] ?? null,
                        'invalid_response_status' => $firstAttempt['response_metrics']['status'] ?? null,
                        'invalid_raw_response' => $firstAttempt['raw_response'] ?? null,
                        'invalid_raw_response_type' => $firstAttempt['raw_response_type'] ?? null,
                        'missing_required_fields' => $missingRequiredFields,
                    ]);
                    $repairAttempt = $this->normalizeGenericAgentOutput($repairAttempt);

                    if ($this->isFinalDecisionUsable($repairAttempt)) {
                        $repairAttempt['final_decision_repair'] = [
                            'used' => true,
                            'reason' => 'initial_final_decision_schema_invalid',
                            'initial_status' => $firstAttempt['status'] ?? null,
                            'initial_response_status' => $firstAttempt['response_metrics']['status'] ?? null,
                        ];
                        $aiDecisionAgent = $repairAttempt;
                    } else {
                        $aiDecisionAgent = $this->buildFinalDecisionFallback($repairAttempt, $compactFinalDecisionContext, [$firstAttempt, $repairAttempt]);
                    }
                } else {
                    $aiDecisionAgent = $this->buildFinalDecisionFallback($firstAttempt, $compactFinalDecisionContext, [$firstAttempt]);
                }
            }

            $aiDecisionAgent = $this->enrichFinalDecisionAgent($aiDecisionAgent, $compactFinalDecisionContext);

            $agentRequestMetrics['ai_final_decision_agent'] = $this->extractAgentRequestMetrics($aiDecisionAgent);
            $this->markDoneIfTracked($isTracked, $runId, 'final_decision_agent', $aiDecisionAgent, 'Final Decision Agent completed.');
        } catch (\Throwable $e) {
            $this->markFailedIfTracked($isTracked, $runId, 'final_decision_agent', $e);
            throw $e;
        }
        $this->logAgentResponse($interactionLog, $runId, 'AI Final Decision Agent', $aiDecisionAgent);

        $conflictMatrix = $structuredNegotiation['orchestrator_package']['conflict_matrix']
            ?? ($structuredNegotiation['conflict_matrix'] ?? ($structuredNegotiation['conflicts'] ?? []));

        $quantitativeBaselineComparison = $this->baselineComparisonService->compare([
            'metrics_context' => $metricsContext,
            'specialist_agents' => $specialistAgents,
            'structured_negotiation' => $structuredNegotiation,
            'conflict_matrix' => $conflictMatrix,
            'final_decision' => $aiDecisionAgent,
            'guardrail_policy' => $metricsContext['guardrail_policy'] ?? [],
            'source_metric_refs' => $metricsContext['source_metric_refs'] ?? [],
            'normalized_action_plan' => $this->normalizedActionPlan($aiDecisionAgent['result'] ?? []),
        ]);

        $structuredNegotiation['quantitative_baseline_comparison'] = $quantitativeBaselineComparison;
        $orchestratorEvidenceAssembly['quantitative_baseline_comparison'] = $quantitativeBaselineComparison;
        $this->logInteraction($interactionLog, $runId, 'Agent Society Baseline Comparison Service', 'orchestrator', 'quantitative_baseline_comparison', [
            'baseline_mode' => $quantitativeBaselineComparison['baseline_mode'] ?? null,
            'baseline_source_agent' => $quantitativeBaselineComparison['baseline_source_agent'] ?? null,
            'headline' => $quantitativeBaselineComparison['headline'] ?? null,
            'delta' => $quantitativeBaselineComparison['delta'] ?? [],
        ]);

        if (!$this->isFinalDecisionUsable($aiDecisionAgent)) {
            $aiDecisionAgent = $this->buildFinalDecisionFallback($aiDecisionAgent, $compactFinalDecisionContext, [$aiDecisionAgent]);
        }

        $scenarioSimulationContext = [
            'metrics_context' => $metricsContext,
            'tomorrow_forecast_metrics' => $metricsContext['tomorrow_forecast_metrics'] ?? ($aiTomorrowForecastAgent['result']['tomorrow_forecast_metrics'] ?? []),
            'guardrail_policy' => $metricsContext['guardrail_policy'] ?? [],
            'final_decision' => $aiDecisionAgent,
            'ai_final_decision_agent' => $aiDecisionAgent,
            'specialist_agents' => $specialistAgents,
            'structured_negotiation' => $structuredNegotiation,
            'conflict_matrix' => $conflictMatrix,
            'ai_tomorrow_forecast_agent' => $aiTomorrowForecastAgent,
            'ai_ads_agent' => $aiAdsAgent,
            'evaluations' => [
                'forecast_evaluations' => $forecastEvaluations,
                'forecast_model_calibration' => $forecastCalibration,
            ],
            'forecast_model_calibration' => $forecastCalibration,
        ];

        $this->logInteraction($interactionLog, $runId, 'AI Final Decision Agent', 'Decision Scenario Simulator', 'final_decision_shared', [
            'shared_context' => ['final_decision', 'metrics_context.generic_metrics_context', 'metrics_context.tomorrow_forecast_metrics', 'specialist_agents', 'evaluations'],
            'execution_mode' => 'sequential_after_final_decision',
            'purpose' => 'compare baseline forecast against recommended action scenario',
            'context_trace' => $this->buildScenarioSimulationContextTrace($scenarioSimulationContext),
        ]);
        $this->markRunningIfTracked($isTracked, $runId, 'decision_scenario_simulator', 'Decision Scenario Simulator is comparing baseline versus recommended action.');
        try {
            $decisionScenarioSimulation = $this->decisionScenarioSimulator->run($scenarioSimulationContext, [
                'run_id' => $runId,
                'shared_context_keys' => ['metrics_context', 'tomorrow_forecast_metrics', 'guardrail_policy', 'final_decision', 'specialist_agents', 'structured_negotiation', 'conflict_matrix', 'evaluations'],
            ]);
            $decisionScenarioSimulation = $this->normalizeGenericAgentOutput($decisionScenarioSimulation);
            $agentRequestMetrics['decision_scenario_simulator'] = $this->extractAgentRequestMetrics($decisionScenarioSimulation);
            $this->markDoneIfTracked($isTracked, $runId, 'decision_scenario_simulator', $decisionScenarioSimulation, 'Decision Scenario Simulator completed.');
        } catch (\Throwable $e) {
            $decisionScenarioSimulation = [
                'agent' => 'Decision Scenario Simulator',
                'status' => 'failed',
                'error' => $e->getMessage(),
                'result' => [
                    'simulation_type' => 'baseline_vs_recommended_action',
                    'status' => 'unavailable',
                    'summary' => 'Decision scenario simulation unavailable; use final decision and deterministic guardrail policy only.',
                ],
            ];
            $this->markFailedIfTracked($isTracked, $runId, 'decision_scenario_simulator', $e);
        }
        $this->logAgentResponse($interactionLog, $runId, 'Decision Scenario Simulator', $decisionScenarioSimulation);

        return [
            'run_id' => $runId,
            'agents' => [
                'ai_activation_agent' => $aiActivationAgent,
                'ai_retention_agent' => $aiRetentionAgent,
                'ai_monetization_agent' => $aiMonetizationAgent,
                'ai_version_agent' => $aiVersionAgent,
                'ai_ads_agent' => $aiAdsAgent,
                'ai_tomorrow_forecast_agent' => $aiTomorrowForecastAgent,
                'structured_negotiation' => [
                    'agent' => 'Structured Negotiation Service',
                    'status' => 'active',
                    'result' => $structuredNegotiation,
                ],
                'orchestrator_evidence_assembly' => [
                    'agent' => 'Orchestrator Evidence Assembly',
                    'status' => 'active',
                    'result' => $orchestratorEvidenceAssembly,
                ],
                'ai_final_decision_agent' => $aiDecisionAgent,
                'decision_scenario_simulator' => $decisionScenarioSimulation,
            ],
            'structured_negotiation' => $structuredNegotiation,
            'conflict_matrix' => $conflictMatrix,
            'negotiation_summary' => $structuredNegotiation['summary'] ?? [],
            'orchestrator_evidence_assembly' => $orchestratorEvidenceAssembly,
            'quantitative_baseline_comparison' => $quantitativeBaselineComparison,
            'agent_request_metrics' => $agentRequestMetrics,
            'tomorrow_forecast_context_mode' => 'compact',
            'interaction_log' => $interactionLog,
            'workflow' => [
                'name' => 'AI Growth Doctor Multi-Agent Workflow',
                'mode' => 'parallel_specialist_fan_out_then_adaptive_bounded_structured_negotiation_then_final_decision',
                'steps' => [
                    '1. Metric extraction builds shared metrics_context.',
                    '2. Independent specialist agents run as a parallel fan-out evidence layer: activation, retention, monetization, version risk, ads acquisition/campaign lifecycle, and tomorrow forecast interpretation.',
                    '3. Adaptive Structured Negotiation runs up to three evidence-bound rounds: objection/support, revision/rebuttal, then escalation only when unresolved material conflicts remain.',
                    '4. Orchestrator assembles metrics, guardrail, specialist summaries, negotiation output, conflict matrix, forecast evaluation, and calibration memory into a decision package.',
                    '5. Final Decision Agent runs sequentially after the negotiation package and resolves material/critical conflicts into one business operating decision.',
                    '6. Decision Scenario Simulator runs sequentially after the final decision and compares baseline no-major-intervention forecast against the recommended action scenario.',
                ],
                'parallelization_note' => 'Specialist agents are independent and run through AiAgentClient::callMany parallel HTTP pool. Structured Negotiation is a deterministic adaptive bounded fan-in layer over specialist summaries only. Final Decision Agent remains sequential because it depends on the complete specialist and negotiation evidence bundle.',
            ],
        ];
    }

    private function withRequestMeta(array $request, string $runId, array $sharedContextKeys, array $extra = []): array
    {
        $request['request_meta'] = array_merge($request['request_meta'] ?? [], $extra, [
            'run_id' => $runId,
            'shared_context_keys' => $sharedContextKeys,
        ]);

        return $request;
    }

    private function normalizeGenericAgentOutput(array $agentOutput): array
    {
        if (!isset($agentOutput['result']) || !is_array($agentOutput['result'])) {
            return $agentOutput;
        }

        $result = $agentOutput['result'];
        $finding = $result['finding']
            ?? ($result['summary']
                ?? ($result['diagnosis']
                    ?? ($result['main_diagnosis']
                        ?? ($result['executive_summary'] ?? null))));
        $recommendation = $result['recommendation']
            ?? ($result['recommended_actions']
                ?? ($result['recommended_experiment']
                    ?? ($result['recommended_intervention']['action'] ?? null)));

        if (empty($result['generic_diagnosis'])) {
            $result['generic_diagnosis'] = is_array($finding) ? json_encode($finding, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ($finding ?? 'No generic diagnosis available.');
        }

        if (empty($result['app_specific_diagnosis'])) {
            $result['app_specific_diagnosis'] = $result['generic_diagnosis'];
        }

        if (empty($result['generic_recommendation'])) {
            $result['generic_recommendation'] = is_array($recommendation) ? json_encode($recommendation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ($recommendation ?? 'No generic recommendation available.');
        }

        if (empty($result['app_specific_recommendation'])) {
            $result['app_specific_recommendation'] = $result['generic_recommendation'];
        }

        $agentOutput['result'] = $result;

        return $agentOutput;
    }

    private function markRunningIfTracked(bool $isTracked, string $runId, string $stepKey, ?string $note = null): void
    {
        if (!$isTracked) {
            return;
        }

        $this->runProgressStore->markRunning($runId, $stepKey, $note);
    }

    private function markDoneIfTracked(bool $isTracked, string $runId, string $stepKey, $stepResult = null, ?string $note = null): void
    {
        if (!$isTracked) {
            return;
        }

        $this->runProgressStore->markDone($runId, $stepKey, $stepResult, $note);
    }

    private function markFailedIfTracked(bool $isTracked, string $runId, string $stepKey, $error): void
    {
        if (!$isTracked) {
            return;
        }

        $this->runProgressStore->markFailed($runId, $stepKey, $error);
    }

    private function makeRunId(): string
    {
        return 'agd_' . date('Ymd_His') . '_' . substr(sha1((string) microtime(true)), 0, 8);
    }

    private function buildOrchestratorEvidenceAssembly(
        array $metricsContext,
        array $specialistAgents,
        array $structuredNegotiation,
        array $forecastEvaluations,
        array $forecastCalibration
    ): array {
        $guardrailResult = $metricsContext['guardrail_policy'] ?? [];
        $orchestratorPackage = $structuredNegotiation['orchestrator_package'] ?? [];
        $conflictMatrix = $orchestratorPackage['conflict_matrix']
            ?? ($structuredNegotiation['conflict_matrix'] ?? ($structuredNegotiation['conflicts'] ?? []));

        return [
            'metrics_context' => $metricsContext,
            'guardrail_result' => $guardrailResult,
            'specialist_outputs' => $specialistAgents,
            'structured_negotiation' => $structuredNegotiation,
            'orchestrator_package' => $orchestratorPackage,
            'negotiation_transcript' => $orchestratorPackage['negotiation_transcript'] ?? ($structuredNegotiation['negotiation_transcript'] ?? []),
            'round_summaries' => $orchestratorPackage['round_summaries'] ?? ($structuredNegotiation['round_summaries'] ?? []),
            'revised_recommendations' => $orchestratorPackage['revised_recommendations'] ?? ($structuredNegotiation['revised_recommendations'] ?? []),
            'conflict_matrix' => $conflictMatrix,
            'negotiation_summary' => $structuredNegotiation['summary'] ?? [],
            'forecast_evaluation' => $forecastEvaluations,
            'calibration_memory' => $forecastCalibration,
            'final_decision_context' => [
                'must_resolve_conflicts' => true,
                'final_decision_owner' => 'FinalDecisionAgent',
                'guardrail_blocked_actions' => $guardrailResult['blocked_actions']
                    ?? ($guardrailResult['deterministic_decision']['blocked_actions'] ?? []),
                'total_conflict_count' => $structuredNegotiation['summary']['total_conflict_count'] ?? count($structuredNegotiation['conflicts'] ?? []),
                'material_conflict_count' => $structuredNegotiation['summary']['material_conflict_count'] ?? 0,
                'critical_conflict_count' => $structuredNegotiation['summary']['critical_conflict_count'] ?? 0,
                'material_or_higher_conflict_count' => $structuredNegotiation['summary']['material_or_higher_conflict_count'] ?? 0,
                'negotiation_rounds' => $structuredNegotiation['execution']['rounds_completed'] ?? ($structuredNegotiation['round'] ?? 1),
                'early_exit' => $structuredNegotiation['execution']['early_exit'] ?? false,
                'early_exit_reason' => $structuredNegotiation['execution']['early_exit_reason'] ?? null,
            ],
        ];
    }

    private function logAgentResponse(array &$interactionLog, string $runId, string $agentName, array $agentOutput): void
    {
        $this->logInteraction($interactionLog, $runId, $agentName, 'orchestrator', 'agent_response', [
            'status' => $agentOutput['status'] ?? null,
            'model' => $agentOutput['model'] ?? null,
            'cache_hit' => $agentOutput['cache']['hit'] ?? null,
            'result_status' => $agentOutput['result']['status'] ?? null,
            'summary' => $this->extractAgentSummary($agentOutput),
            'request_metrics' => $this->extractAgentRequestMetrics($agentOutput),
            'response_metrics' => $agentOutput['response_metrics'] ?? null,
        ]);
    }

    private function extractAgentRequestMetrics(array $agentOutput): array
    {
        $metrics = $agentOutput['request_metrics'] ?? [];
        $responseMetrics = $agentOutput['response_metrics'] ?? [];

        return array_filter([
            'payload_bytes' => $metrics['payload_bytes'] ?? null,
            'estimated_tokens' => $metrics['estimated_tokens'] ?? null,
            'message_count' => $metrics['message_count'] ?? null,
            'timeout_seconds' => $metrics['timeout_seconds'] ?? null,
            'shared_context_keys' => $metrics['shared_context_keys'] ?? [],
            'model' => $metrics['model'] ?? ($agentOutput['model'] ?? null),
            'provider' => $metrics['provider'] ?? ($agentOutput['provider'] ?? null),
            'endpoint' => $metrics['endpoint'] ?? null,
            'duration_ms' => $responseMetrics['duration_ms'] ?? null,
            'status' => $responseMetrics['status'] ?? null,
            'http_status' => $responseMetrics['http_status'] ?? null,
            'response_bytes' => $responseMetrics['response_bytes'] ?? null,
            'context_mode' => $metrics['context_mode'] ?? null,
        ], function ($value) {
            return $value !== null && $value !== [];
        });
    }

    private function isFinalDecisionUsable(array $agentOutput): bool
    {
        if (in_array($agentOutput['status'] ?? null, ['invalid_json', 'exception', 'error'], true)) {
            return false;
        }

        return empty($this->missingFinalDecisionFields($agentOutput));
    }

    private function missingFinalDecisionFields(array $agentOutput): array
    {
        $result = $this->finalDecisionResult($agentOutput);

        if (empty($result)) {
            return [
                'business_verdict',
                'today_operator_summary',
                'main_diagnosis',
                'action_plan_or_prioritized_actions',
                'operating_decision_or_agent_society_operating_verdict',
            ];
        }

        $missing = [];

        foreach (['business_verdict', 'today_operator_summary', 'main_diagnosis'] as $field) {
            if (empty($result[$field])) {
                $missing[] = $field;
            }
        }

        if (empty($result['action_plan']) && empty($result['prioritized_actions'])) {
            $missing[] = 'action_plan_or_prioritized_actions';
        }

        if (empty($result['operating_decision']) && empty($result['agent_society_operating_verdict'])) {
            $missing[] = 'operating_decision_or_agent_society_operating_verdict';
        }

        return $missing;
    }

    private function finalDecisionResult(array $agentOutput): array
    {
        $result = $agentOutput['result'] ?? $agentOutput;

        return is_array($result) ? $result : [];
    }

    private function shouldRepairFinalDecision(array $agentOutput): bool
    {
        $responseStatus = $agentOutput['response_metrics']['status'] ?? null;
        $rawResponse = $agentOutput['raw_response'] ?? null;

        if ($responseStatus === 'timeout' && ($rawResponse === null || $rawResponse === '')) {
            return false;
        }

        return $rawResponse !== null || !empty($this->finalDecisionResult($agentOutput));
    }

    private function enrichFinalDecisionAgent(array $agentOutput, array $compactContext): array
    {
        $result = $this->finalDecisionResult($agentOutput);

        if (!$this->isFinalDecisionUsable($agentOutput)) {
            $agentOutput = $this->buildFinalDecisionFallback($agentOutput, $compactContext, [$agentOutput]);
            $result = $this->finalDecisionResult($agentOutput);
        }

        $agentOutput['result'] = $this->finalDecisionEnrichmentService->enrich($result, $compactContext, [
            'fallback_used' => ($agentOutput['status'] ?? null) === 'fallback',
            'final_decision_repair_used' => (bool) ($agentOutput['final_decision_repair']['used'] ?? false),
            'original_status' => $agentOutput['status'] ?? null,
            'invalid_attempts' => $result['invalid_attempts'] ?? [],
        ]);

        return $agentOutput;
    }

    private function buildFinalDecisionFallback(array $failedAgent, array $compactContext, array $invalidAttempts = []): array
    {
        $guardrail = $compactContext['guardrail_policy'] ?? [];
        $coreMetrics = $compactContext['core_metrics'] ?? [];
        $structuredNegotiation = $compactContext['structured_negotiation'] ?? [];
        $specialists = $compactContext['specialist_summaries'] ?? [];
        $businessVerdict = $guardrail['deterministic_business_verdict'] ?? 'HOLD_AND_OPTIMIZE';
        $mainDiagnosis = $this->fallbackMainDiagnosis($coreMetrics, $specialists);
        $errorMessage = (string) ($failedAgent['response_metrics']['error_message'] ?? ($failedAgent['error'] ?? 'Final Decision LLM timed out.'));

        $failedAgent['status'] = 'fallback';
        $failedAgent['result'] = [
            'status' => 'fallback',
            'result_status' => 'deterministic_final_decision_fallback',
            'business_verdict' => $businessVerdict,
            'business_status' => 'Final Decision fallback used',
            'deterministic_guardrail_verdict' => $businessVerdict,
            'agent_society_operating_verdict' => $guardrail['allowed_decision'] ?? 'HOLD_AND_OPTIMIZE',
            'operating_decision_summary' => 'Use deterministic guardrails and compact specialist summaries while the Final Decision LLM is unavailable.',
            'today_operator_summary' => 'Final Decision fallback used: hold unsafe scaling, prioritize activation/retention fixes, and keep ads/paywall changes guarded.',
            'main_diagnosis' => $mainDiagnosis,
            'action_plan' => [
                'fix_session_to_workspace_onboarding',
                'improve_day_1_reentry',
                'guarded_reset_campaign_test',
                'value_gated_paywall',
            ],
            'prioritized_actions' => [
                [
                    'priority' => 1,
                    'action' => 'fix_session_to_workspace_onboarding',
                    'owner_area' => 'product',
                    'expected_impact' => 'high',
                    'why' => 'Activation and specialist summaries remain sufficient for a deterministic fallback.',
                    'success_metric' => 'workspace_users',
                    'success_target' => 'increase directionally before scaling',
                ],
                [
                    'priority' => 2,
                    'action' => 'improve_day_1_reentry',
                    'owner_area' => 'product',
                    'expected_impact' => 'high',
                    'why' => 'Retention risk should be repaired before aggressive acquisition or monetization pressure.',
                    'success_metric' => 'd1_logged_rate',
                    'success_target' => 'increase directionally after cohort matures',
                ],
            ],
            'operational_action_plan' => [
                [
                    'action' => 'fix_session_to_workspace_onboarding',
                    'target_user_segment' => 'new and returning users entering the app session',
                    'trigger_condition' => 'session starts before workspace/core action success',
                    'success_metric' => 'workspace_users',
                    'stop_loss_metric' => 'd1_logged_rate',
                    'expected_lift' => 'directional improvement',
                    'experiment_duration' => '7 days',
                    'minimum_sample_size' => 'use next checkpoint sample',
                    'rollback_condition' => 'workspace entry or D1 retention worsens',
                    'owner_area' => 'product',
                ],
                [
                    'action' => 'guarded_reset_campaign_test',
                    'target_user_segment' => 'paid acquisition cohorts with downstream activation monitoring',
                    'trigger_condition' => 'ads reset campaign remains within deterministic safety boundary',
                    'success_metric' => 'cost_per_install and workspace_users',
                    'stop_loss_metric' => 'activation and retention quality',
                    'expected_lift' => 'controlled acquisition learning',
                    'experiment_duration' => '7 days',
                    'minimum_sample_size' => 'do not judge before enough conversions',
                    'rollback_condition' => 'CPI worsens or activation quality drops',
                    'owner_area' => 'ads',
                ],
            ],
            'operating_decision' => [
                'ads_decision' => [
                    'decision' => 'hold_budget',
                    'label' => 'Hold Budget',
                    'confidence_score' => 55,
                    'reason' => 'Fallback uses deterministic guardrails and compact ads evidence only.',
                    'next_action' => 'Keep ads changes guarded until Final Decision LLM completes.',
                    'guardrail_metric' => 'activation and retention quality',
                ],
                'product_decision' => [
                    'decision' => 'prioritize_activation',
                    'label' => 'Fix Activation',
                    'confidence_score' => 60,
                    'reason' => $mainDiagnosis,
                    'next_action' => 'Improve session to workspace and early re-entry.',
                    'success_metric' => 'workspace_users and D1 logged rate',
                ],
            ],
            'structured_negotiation_summary' => $structuredNegotiation,
            'specialist_summaries' => $specialists,
            'llm_error' => [
                'type' => ($failedAgent['response_metrics']['status'] ?? null) === 'timeout' ? 'timeout' : 'schema_failure',
                'message' => substr($errorMessage, 0, 500),
            ],
            'invalid_attempts' => $this->compactInvalidFinalDecisionAttempts($invalidAttempts),
            'decision_usable' => true,
            'fallback_reason' => 'Final Decision LLM returned invalid or incomplete JSON, but deterministic guardrail, specialist summaries, and structured negotiation were available.',
        ];

        return $failedAgent;
    }

    private function compactInvalidFinalDecisionAttempts(array $attempts): array
    {
        return array_map(function ($attempt) {
            if (!is_array($attempt)) {
                return [];
            }

            return [
                'status' => $attempt['status'] ?? null,
                'response_status' => $attempt['response_metrics']['status'] ?? null,
                'http_status' => $attempt['response_metrics']['http_status'] ?? null,
                'response_bytes' => $attempt['response_metrics']['response_bytes'] ?? null,
                'raw_response' => $attempt['raw_response'] ?? null,
                'raw_response_type' => $attempt['raw_response_type'] ?? null,
                'missing_required_fields' => $this->missingFinalDecisionFields($attempt),
            ];
        }, $attempts);
    }

    private function fallbackMainDiagnosis(array $coreMetrics, array $specialists): string
    {
        foreach (['ai_activation_agent', 'ai_retention_agent', 'ai_ads_agent'] as $key) {
            $summary = $specialists[$key]['summary'] ?? null;
            if (is_string($summary) && trim($summary) !== '') {
                return $summary;
            }
        }

        $activation = $coreMetrics['activation'] ?? [];
        $retention = $coreMetrics['retention'] ?? [];

        return sprintf(
            'Compact fallback diagnosis: activation workspace entry and early retention need guarded optimization before aggressive scaling. Workspace users: %s; D1 logged rate: %s.',
            $activation['workspace_users'] ?? 'unknown',
            $retention['d1_logged_rate'] ?? 'unknown'
        );
    }

    private function logInteraction(array &$interactionLog, string $runId, string $from, string $to, string $messageType, array $payload = []): void
    {
        $interactionLog[] = [
            'sequence' => count($interactionLog) + 1,
            'run_id' => $runId,
            'timestamp' => date('Y-m-d H:i:s'),
            'from' => $from,
            'to' => $to,
            'message_type' => $messageType,
            'payload' => $payload,
        ];
    }

    private function extractAgentSummary(array $agentOutput): ?string
    {
        $result = $agentOutput['result'] ?? [];

        $summary = $agentOutput['summary']
            ?? $agentOutput['result_summary']
            ?? ($result['summary'] ?? null)
            ?? ($result['executive_summary'] ?? null)
            ?? ($result['diagnosis'] ?? null)
            ?? ($result['main_diagnosis'] ?? null)
            ?? ($result['main_predicted_risk'] ?? null)
            ?? ($result['decision_impact_today'] ?? null)
            ?? ($result['baseline_vs_intervention_comparison']['decision_implication'] ?? null)
            ?? ($result['scenario_with_intervention']['summary'] ?? null)
            ?? ($agentOutput['diagnosis'] ?? null)
            ?? ($agentOutput['error'] ?? null);

        if (is_array($summary)) {
            $summary = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($summary === null) {
            return null;
        }

        $summary = trim((string) $summary);

        return $summary !== '' ? $summary : null;
    }

    private function buildFinalDecisionContextTrace(array $context): array
    {
        if (isset($context['final_decision_compact_context']) && is_array($context['final_decision_compact_context'])) {
            $compact = $context['final_decision_compact_context'];

            return [
                'context_mode' => $context['final_decision_context_mode'] ?? 'compact',
                'compact_context_payload_bytes' => $context['compact_context_payload_bytes'] ?? null,
                'compact_context_keys' => array_keys($compact),
                'core_metric_groups' => array_keys($compact['core_metrics'] ?? []),
                'specialist_summary_keys' => array_keys($compact['specialist_summaries'] ?? []),
                'triggered_guardrails_count' => $compact['guardrail_policy']['triggered_guardrails_count'] ?? null,
                'winning_guardrail' => $compact['guardrail_policy']['winning_guardrail'] ?? null,
                'structured_negotiation' => [
                    'rounds_completed' => $compact['structured_negotiation']['rounds_completed'] ?? null,
                    'total_conflict_count' => $compact['structured_negotiation']['total_conflict_count'] ?? null,
                    'key_tensions_count' => count($compact['structured_negotiation']['key_tensions'] ?? []),
                ],
                'forecast' => [
                    'forecast_for_date' => $compact['forecast']['forecast_for_date'] ?? null,
                    'latest_quality' => $compact['forecast']['evaluation']['latest_quality'] ?? null,
                    'trust_score' => $compact['forecast']['calibration']['trust_score'] ?? null,
                ],
            ];
        }

        $metricsContext = $context['metrics_context'] ?? [];
        $versionMetrics = $metricsContext['version_metrics'] ?? [];
        $adsMetrics = $metricsContext['ads_metrics'] ?? [];
        $guardrailPolicy = $metricsContext['guardrail_policy'] ?? [];
        $tomorrowForecastMetrics = $metricsContext['tomorrow_forecast_metrics'] ?? [];
        $specialistAgents = $context['specialist_agents'] ?? [];
        $evaluations = $context['evaluations'] ?? [];
        $structuredNegotiation = $context['structured_negotiation'] ?? [];
        $conflictMatrix = $context['conflict_matrix'] ?? [];

        return [
            'metrics_keys' => array_keys($metricsContext),
            'version_metrics' => [
                'has_versions_full_list' => isset($versionMetrics['versions']),
                'versions_count' => is_array($versionMetrics['versions'] ?? null) ? count($versionMetrics['versions']) : 0,
                'top_versions_count' => is_array($versionMetrics['top_versions'] ?? null) ? count($versionMetrics['top_versions']) : 0,
                'has_compact_version_context' => isset($versionMetrics['compact_version_context']),
                'compact_relevant_versions_count' => is_array($versionMetrics['compact_version_context']['relevant_versions'] ?? null) ? count($versionMetrics['compact_version_context']['relevant_versions']) : 0,
                'compact_release_candidate_versions_count' => is_array($versionMetrics['compact_version_context']['release_candidate_versions'] ?? null) ? count($versionMetrics['compact_version_context']['release_candidate_versions']) : 0,
                'legacy_context_versions_count' => $versionMetrics['compact_version_context']['legacy_context_summary']['legacy_or_context_versions_count'] ?? null,
            ],
            'ads_metrics' => [
                'campaigns_count' => is_array($adsMetrics['campaigns'] ?? null) ? count($adsMetrics['campaigns']) : 0,
                'campaign_names' => is_array($adsMetrics['campaigns'] ?? null) ? array_keys($adsMetrics['campaigns']) : [],
                'ads_verdict' => $adsMetrics['ads_verdict']['decision'] ?? null,
            ],
            'guardrail_policy' => [
                'policy_version' => $guardrailPolicy['policy_version'] ?? null,
                'triggered_guardrails_count' => is_array($guardrailPolicy['triggered_guardrails'] ?? null) ? count($guardrailPolicy['triggered_guardrails']) : 0,
                'winning_guardrail' => $guardrailPolicy['winning_guardrail'] ?? null,
                'deterministic_business_verdict' => $guardrailPolicy['deterministic_decision']['business_verdict'] ?? null,
                'blocked_decision' => $guardrailPolicy['deterministic_decision']['blocked_decision'] ?? null,
                'allowed_decision' => $guardrailPolicy['deterministic_decision']['allowed_decision'] ?? null,
            ],
            'tomorrow_forecast_metrics' => [
                'has_forecast' => !empty($tomorrowForecastMetrics),
                'forecast_for_date' => $tomorrowForecastMetrics['forecast_for_date'] ?? null,
                'data_as_of_date' => $tomorrowForecastMetrics['data_as_of_date'] ?? null,
                'risk_flags' => $tomorrowForecastMetrics['risk_flags'] ?? ($tomorrowForecastMetrics['guardrails'] ?? []),
                'completeness_guard_status' => $tomorrowForecastMetrics['data_windows']['completeness_guard']['status'] ?? null,
            ],
            'specialist_agents' => [
                'keys' => array_keys($specialistAgents),
                'count' => count($specialistAgents),
                'statuses' => $this->agentStatusMap($specialistAgents),
                'execution_modes' => $this->agentExecutionModeMap($specialistAgents),
            ],
            'structured_negotiation' => [
                'present' => !empty($structuredNegotiation),
                'round' => $structuredNegotiation['round'] ?? null,
                'negotiation_type' => $structuredNegotiation['negotiation_type'] ?? null,
                'agent_response_count' => count($structuredNegotiation['agent_responses'] ?? []),
                'conflict_count' => count($conflictMatrix),
                'total_conflict_count' => $structuredNegotiation['summary']['total_conflict_count'] ?? count($conflictMatrix),
                'material_conflict_count' => $structuredNegotiation['summary']['material_conflict_count'] ?? null,
                'critical_conflict_count' => $structuredNegotiation['summary']['critical_conflict_count'] ?? null,
                'material_or_higher_conflict_count' => $structuredNegotiation['summary']['material_or_higher_conflict_count'] ?? null,
                'bounded_tension_count' => $structuredNegotiation['summary']['bounded_tension_count'] ?? null,
                'resolved_bounded_tension_count' => $structuredNegotiation['summary']['resolved_bounded_tension_count'] ?? null,
                'resolved_material_tension_count' => $structuredNegotiation['summary']['resolved_material_tension_count'] ?? null,
                'minor_bounded_tension_count' => $structuredNegotiation['summary']['minor_bounded_tension_count'] ?? null,
                'minor_bounded_caution_count' => $structuredNegotiation['summary']['minor_bounded_caution_count'] ?? ($structuredNegotiation['summary']['minor_bounded_tension_count'] ?? null),
                'partial_concession_count' => $structuredNegotiation['summary']['partial_concession_count'] ?? null,
                'safety_bounded_revision_count' => $structuredNegotiation['summary']['safety_bounded_revision_count'] ?? null,
            ],
            'evaluations' => [
                'has_forecast_evaluations' => !empty($evaluations['forecast_evaluations'] ?? []),
                'forecast_evaluated_count' => $evaluations['forecast_evaluations']['evaluated_count'] ?? null,
                'forecast_pending_count' => $evaluations['forecast_evaluations']['pending_count'] ?? null,
                'forecast_latest_quality' => $this->extractLatestForecastEvaluationQuality($evaluations['forecast_evaluations'] ?? []),
                'has_forecast_model_calibration' => !empty($evaluations['forecast_model_calibration'] ?? []),
                'forecast_trust_score' => $evaluations['forecast_model_calibration']['trust_score']['updated_score'] ?? null,
                'forecast_role' => $evaluations['forecast_model_calibration']['decision_instruction']['forecast_role'] ?? null,
            ],
        ];
    }

    private function buildScenarioSimulationContextTrace(array $context): array
    {
        $metricsContext = $context['metrics_context'] ?? [];
        $finalDecisionEnvelope = $context['final_decision'] ?? ($context['ai_final_decision_agent'] ?? []);
        $finalDecision = $finalDecisionEnvelope['result'] ?? $finalDecisionEnvelope;
        $tomorrowForecastMetrics = $context['tomorrow_forecast_metrics'] ?? ($metricsContext['tomorrow_forecast_metrics'] ?? []);
        $specialistAgents = $context['specialist_agents'] ?? [];
        $normalizedActionPlan = $this->normalizedActionPlan($finalDecision);
        $hasActionPlan = $this->hasActionPlan($finalDecision, $normalizedActionPlan);

        return [
            'has_final_decision_envelope' => !empty($finalDecisionEnvelope),
            'has_final_decision_result' => !empty($finalDecision),
            'final_decision_fields' => is_array($finalDecision) ? array_keys($finalDecision) : [],
            'business_verdict' => $finalDecision['business_verdict'] ?? null,
            'today_operator_summary' => $finalDecision['today_operator_summary'] ?? null,
            'has_operating_decision' => !empty($finalDecision['operating_decision'] ?? []),
            'has_prioritized_actions' => !empty($finalDecision['prioritized_actions'] ?? []),
            'has_action_plan' => $hasActionPlan,
            'normalized_action_plan' => $normalizedActionPlan,
            'has_tomorrow_forecast_metrics' => !empty($tomorrowForecastMetrics),
            'forecast_for_date' => $tomorrowForecastMetrics['forecast_for_date'] ?? null,
            'forecast_risk_flags' => $tomorrowForecastMetrics['risk_flags'] ?? ($tomorrowForecastMetrics['guardrails'] ?? []),
            'has_ai_tomorrow_forecast_agent' => !empty($context['ai_tomorrow_forecast_agent'] ?? []),
            'has_ai_ads_agent' => !empty($context['ai_ads_agent'] ?? []),
            'specialist_agent_keys' => array_keys($specialistAgents),
        ];
    }

    private function hasActionPlan(array $finalDecision, array $normalizedActionPlan): bool
    {
        if (!empty($normalizedActionPlan)) {
            return true;
        }

        foreach (['action_plan', 'action_plan_24_72h', 'operational_action_plan', 'prioritized_actions', 'recommended_actions', 'accepted_recommendations', 'today_operator_summary', 'operating_decision'] as $field) {
            $value = $finalDecision[$field] ?? null;
            if (is_array($value) && !empty($value)) {
                return true;
            }

            if (is_string($value) && $this->looksActionable($value)) {
                return true;
            }
        }

        return false;
    }

    private function normalizedActionPlan(array $finalDecision): array
    {
        $sourceText = strtolower(json_encode($finalDecision));
        $plan = [];

        if ($this->containsAny($sourceText, ['session', 'workspace', 'food-log', 'food log', 'food_add', 'onboarding', 'activation'])) {
            $plan[] = [
                'action_id' => 'fix_session_to_workspace_onboarding',
                'action' => 'Fix session to workspace to first food-log onboarding.',
                'owner_domain' => 'activation',
                'time_window' => '24-72h',
                'risk_level' => 'low',
                'why' => 'Session users convert to food_add_success far below workspace users, so the first-core-action path is the main activation constraint.',
            ];
        }

        if ($this->containsAny($sourceText, ['d1', 'day-1', 'day 1', 'next-day', 'repeat logging', 'habit'])) {
            $plan[] = [
                'action_id' => 'improve_day_1_reentry',
                'action' => 'Improve day-1 re-entry and repeat logging path.',
                'owner_domain' => 'retention',
                'time_window' => '24-72h',
                'risk_level' => 'low',
                'why' => 'D0 to D1 logging drops sharply.',
            ];
        }

        if ($this->containsAny($sourceText, ['reset campaign', 'volume install reset', 'guarded reset', 'cautious controlled', 'stable budget', 'ads'])) {
            $plan[] = [
                'action_id' => 'guarded_reset_campaign_test',
                'action' => 'Evaluate HK - ID - Volume Install Reset with stable budget and downstream quality checks.',
                'owner_domain' => 'ads',
                'time_window' => '24-72h',
                'risk_level' => 'medium',
                'why' => 'Ads efficiency improved but volume is small and downstream quality must be verified.',
            ];
        }

        if ($this->containsAny($sourceText, ['paywall', 'value-gated', 'value gated', 'monetization', 'purchase sample'])) {
            $plan[] = [
                'action_id' => 'value_gated_paywall',
                'action' => 'Keep paywall pressure value-gated; do not broaden paywall pressure.',
                'owner_domain' => 'monetization',
                'time_window' => '7d',
                'risk_level' => 'low',
                'why' => 'Purchase sample is low and paywall-to-purchase conversion is weak.',
            ];
        }

        if (!empty($plan)) {
            return $plan;
        }

        foreach (['action_plan', 'action_plan_24_72h', 'operational_action_plan', 'prioritized_actions', 'recommended_actions', 'accepted_recommendations'] as $field) {
            if (!empty($finalDecision[$field] ?? [])) {
                return [[
                    'action_id' => 'final_decision_action_plan',
                    'action' => is_string($finalDecision[$field]) ? $finalDecision[$field] : json_encode($finalDecision[$field], JSON_UNESCAPED_SLASHES),
                    'owner_domain' => 'final_decision',
                    'time_window' => '24-72h',
                    'risk_level' => 'medium',
                    'why' => 'Final Decision Agent provided actionable operating guidance.',
                ]];
            }
        }

        return [];
    }

    private function looksActionable(string $value): bool
    {
        return $this->containsAny(strtolower($value), ['fix', 'improve', 'keep', 'run', 'evaluate', 'prioritize', 'hold', 'optimize', 'test', 'do not', 'monitor']);
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

    private function agentStatusMap(array $agents): array
    {
        $statuses = [];

        foreach ($agents as $key => $agent) {
            $statuses[$key] = $agent['status'] ?? null;
        }

        return $statuses;
    }

    private function agentExecutionModeMap(array $agents): array
    {
        $modes = [];

        foreach ($agents as $key => $agent) {
            $modes[$key] = $agent['execution']['mode'] ?? null;
        }

        return $modes;
    }

    private function extractLatestForecastEvaluationQuality(array $forecastEvaluations): ?string
    {
        $evaluated = $forecastEvaluations['evaluated'] ?? [];

        if (empty($evaluated) || !is_array($evaluated)) {
            return null;
        }

        $latest = end($evaluated);

        if (!is_array($latest)) {
            return null;
        }

        return $latest['summary']['forecast_quality'] ?? null;
    }
}
