<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GrowthDoctor\AgentOrchestrator;
use App\Services\GrowthDoctor\CheckpointRepository;
use App\Services\GrowthDoctor\MetricsExtractor;
use App\Services\GrowthDoctor\ForecastEvaluationService;
use App\Services\GrowthDoctor\ForecastCalibrationService;
use App\Services\GrowthDoctor\GuardrailPolicyEngine;
use App\Services\GrowthDoctor\RunProgressStore;
use App\Services\AiGrowthDoctor\AppProfileService;
use App\Services\AiGrowthDoctor\GenericMetricMapperService;
use App\Services\AiGrowthDoctor\MetricMappingService;
use Illuminate\Support\Str;

class AiGrowthDoctorController extends Controller
{
    private $checkpointRepository;
    private $metricsExtractor;
    private $guardrailPolicyEngine;
    private $forecastEvaluationService;
    private $forecastCalibrationService;
    private $agentOrchestrator;
    private $runProgressStore;
    private $appProfileService;
    private $metricMappingService;
    private $genericMetricMapperService;

    public function __construct(
        CheckpointRepository $checkpointRepository,
        MetricsExtractor $metricsExtractor,
        GuardrailPolicyEngine $guardrailPolicyEngine,
        ForecastEvaluationService $forecastEvaluationService,
        ForecastCalibrationService $forecastCalibrationService,
        AgentOrchestrator $agentOrchestrator,
        RunProgressStore $runProgressStore,
        AppProfileService $appProfileService,
        MetricMappingService $metricMappingService,
        GenericMetricMapperService $genericMetricMapperService
    ) {
        $this->checkpointRepository = $checkpointRepository;
        $this->metricsExtractor = $metricsExtractor;
        $this->guardrailPolicyEngine = $guardrailPolicyEngine;
        $this->forecastEvaluationService = $forecastEvaluationService;
        $this->forecastCalibrationService = $forecastCalibrationService;
        $this->agentOrchestrator = $agentOrchestrator;
        $this->runProgressStore = $runProgressStore;
        $this->appProfileService = $appProfileService;
        $this->metricMappingService = $metricMappingService;
        $this->genericMetricMapperService = $genericMetricMapperService;
    }

    public function dashboard(Request $request)
    {
        if ($request->boolean('sync')) {
            $analysis = $this->analyzeCheckpoint();

            return view('ai-growth-doctor.dashboard', [
                'analysis' => $analysis,
                'autoStartAsync' => false,
                'hasCachedAnalysis' => true,
            ]);
        }

        $latestAnalysis = $this->runProgressStore->latestCompletedResult();
        $analysis = $latestAnalysis ?: $this->emptyDashboardAnalysis();

        return view('ai-growth-doctor.dashboard', [
            'analysis' => $analysis,
            'autoStartAsync' => !$request->boolean('no_auto'),
            'hasCachedAnalysis' => $latestAnalysis !== null,
        ]);
    }

    public function analyze(Request $request)
    {
        return response()->json($this->analyzeCheckpoint());
    }

    public function startAsync(Request $request)
    {
        $runId = 'agd_' . now()->format('Ymd_His') . '_' . Str::lower(Str::random(8));

        $run = $this->runProgressStore->create($runId);

        $pendingDir = storage_path('app/ai-growth-doctor/pending');

        if (!is_dir($pendingDir)) {
            mkdir($pendingDir, 0775, true);
        }

        $pendingPayload = [
            'run_id' => $runId,
            'status' => 'pending',
            'created_at' => now()->format('Y-m-d H:i:s'),
            'source' => 'api_start_async',
        ];

        file_put_contents(
            $pendingDir . '/' . $runId . '.json',
            json_encode($pendingPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        return response()->json([
            'run_id' => $runId,
            'status' => $run['status'],
            'poll_url' => url('/api/ai-growth-doctor/runs/' . $runId),
            'message' => 'Async run initialized. Poll the run endpoint for progress.',
            'queued_to_worker' => true,
            'worker_hint' => 'Run php artisan growth-doctor:work in a separate terminal to process pending runs.',
        ]);
    }

    public function getRunStatus($runId)
    {
        if (!preg_match('/^agd_[A-Za-z0-9_\-]+$/', $runId)) {
            return response()->json([
                'error' => 'Invalid run id.',
            ], 422);
        }

        $run = $this->runProgressStore->getStatus($runId);

        if (!$run) {
            return response()->json([
                'error' => 'Run not found.',
                'run_id' => $runId,
            ], 404);
        }

        return response()->json($run);
    }

    public function downloadAuditTrace(string $runId)
    {
        if (!preg_match('/^agd_[A-Za-z0-9_\-]+$/', $runId)) {
            abort(404);
        }

        $path = storage_path('app/ai-growth-doctor/audit/' . $runId . '.json');

        if (!is_file($path)) {
            abort(404);
        }

        return response()->download($path, $runId . '-full-audit-trace.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    private function emptyDashboardAnalysis(): array
    {
        return [
            'meta' => [
                'app_name' => 'AI Growth Doctor',
                'window_start' => null,
                'window_end' => null,
                'analyzed_at' => null,
                'architecture' => 'async_multi_agent_progress',
                'run_id' => null,
            ],
            'workflow' => [],
            'interaction_log' => [],
            'metrics' => [
                'activation_metrics' => [],
                'retention_metrics' => [],
                'monetization_metrics' => [],
                'version_metrics' => [],
                'ads_metrics' => [],
                'tomorrow_forecast_metrics' => [],
                'rule_based_decision' => [],
                'guardrail_policy' => [],
            ],
            'app_profile' => $this->appProfileService->defaultHitungKaloriProfile(),
            'metric_mapping' => $this->metricMappingService->defaultHitungKaloriMapping(),
            'generic_metrics_context' => [],
            'mapping_validation' => [],
            'source_metric_refs' => [],
            'evaluations' => [
                'forecast_evaluations' => [
                    'status' => 'empty',
                    'evaluated' => [],
                    'pending' => [],
                    'skipped' => [],
                ],
                'forecast_model_calibration' => [
                    'status' => 'empty',
                    'evaluations_used' => 0,
                    'trust_score' => [
                        'updated_score' => null,
                        'interpretation' => 'empty',
                    ],
                    'decision_instruction' => [],
                ],
            ],
            'agents' => [
                'activation_agent' => [],
                'retention_agent' => [],
                'monetization_agent' => [],
                'decision_agent' => [],
                'ai_decision_agent' => [],
                'ai_activation_agent' => [],
                'ai_retention_agent' => [],
                'ai_monetization_agent' => [],
                'ai_version_agent' => [],
                'ai_ads_agent' => [],
                'ai_tomorrow_forecast_agent' => [],
                'structured_negotiation' => [],
                'orchestrator_evidence_assembly' => [],
                'ai_final_decision_agent' => [],
                'decision_scenario_simulator' => [],
            ],
            'structured_negotiation' => [],
            'conflict_matrix' => [],
            'negotiation_summary' => [],
            'orchestrator_evidence_assembly' => [],
            'charts' => [
                'activation_trend' => [],
                'retention_trend' => [],
            ],
        ];
    }

    public function analyzeCheckpoint(?string $trackedRunId = null): array
    {
        if (!$this->checkpointRepository->exists()) {
            $error = [
                'error' => 'Checkpoint JSON not found.',
                'expected_path' => $this->checkpointRepository->expectedPath(),
            ];

            if ($trackedRunId) {
                $this->runProgressStore->markFailed($trackedRunId, 'checkpoint_load', $error);
            }

            return $error;
        }

        if ($trackedRunId) {
            $this->runProgressStore->markRunning($trackedRunId, 'checkpoint_load', 'Loading latest checkpoint JSON.');
        }

        try {
            $checkpoint = $this->checkpointRepository->loadLatest();
            $this->archiveCheckpointSnapshot($checkpoint);
        } catch (\Throwable $e) {
            $error = [
                'error' => 'Failed to load checkpoint JSON.',
                'message' => $e->getMessage(),
            ];

            if ($trackedRunId) {
                $this->runProgressStore->markFailed($trackedRunId, 'checkpoint_load', $e);
            }

            return $error;
        }

        if ($trackedRunId) {
            $this->runProgressStore->markDone($trackedRunId, 'checkpoint_load', ['status' => 'loaded'], 'Checkpoint loaded.');
            $this->runProgressStore->markRunning($trackedRunId, 'metrics_extraction', 'Extracting activation, retention, monetization, and version metrics.');
        }

        $activationDaily = $checkpoint['activation_daily'] ?? [];
        $retentionDaily = $checkpoint['retention_daily'] ?? [];

        $metrics = $this->metricsExtractor->extract($checkpoint);
        $forecastEvaluations = $this->forecastEvaluationService->evaluateReadyForecasts($checkpoint);
        $forecastCalibration = $this->forecastCalibrationService->calibrate();

        if ($trackedRunId) {
            $this->runProgressStore->markDone($trackedRunId, 'metrics_extraction', ['status' => 'extracted'], 'Metrics extracted.');
        }

        $activationMetrics = $metrics['activation_metrics'];
        $retentionMetrics = $metrics['retention_metrics'];
        $monetizationMetrics = $metrics['monetization_metrics'];
        $versionMetrics = $metrics['version_metrics'];
        $adsMetrics = $metrics['ads_metrics'] ?? [];
        $tomorrowForecastMetrics = $metrics['tomorrow_forecast_metrics'] ?? [];
        $ruleDecision = $metrics['rule_based_decision'];

        $metricsContext = [
            'checkpoint_meta' => $checkpoint['meta'] ?? [],
            'activation_metrics' => $activationMetrics,
            'retention_metrics' => $retentionMetrics,
            'monetization_metrics' => $monetizationMetrics,
            'version_metrics' => $versionMetrics,
            'ads_metrics' => $adsMetrics,
            'tomorrow_forecast_metrics' => $tomorrowForecastMetrics,
            'forecast_evaluations' => $forecastEvaluations,
            'forecast_model_calibration' => $forecastCalibration,
            'evaluations' => [
                'forecast_evaluations' => $forecastEvaluations,
                'forecast_model_calibration' => $forecastCalibration,
            ],
            'rule_based_decision' => $ruleDecision,
        ];

        $appProfile = $this->appProfileService->resolve($checkpoint);
        $metricMapping = $this->metricMappingService->resolve($checkpoint, $appProfile);
        $genericMappingContext = $this->genericMetricMapperService->buildGenericContext($metricsContext, $metricMapping, $appProfile);

        $metricsContext['app_profile'] = $genericMappingContext['app_profile'];
        $metricsContext['metric_mapping'] = $genericMappingContext['metric_mapping'];
        $metricsContext['generic_metrics_context'] = $genericMappingContext['generic_metrics_context'];
        $metricsContext['mapping_validation'] = $genericMappingContext['mapping_validation'];
        $metricsContext['source_metric_refs'] = $genericMappingContext['source_metric_refs'];
        $metricsContext['source_metrics_context'] = $genericMappingContext['source_metrics_context'];

        $guardrailPolicy = $this->guardrailPolicyEngine->evaluate($metricsContext, [
            'forecast_evaluations' => $forecastEvaluations,
            'forecast_model_calibration' => $forecastCalibration,
        ]);

        $metricsContext['guardrail_policy'] = $guardrailPolicy;

        try {
            $orchestration = $this->agentOrchestrator->run($metricsContext, $trackedRunId);
        } catch (\Throwable $e) {
            if ($trackedRunId) {
                $this->runProgressStore->markFailed($trackedRunId, 'final_decision_agent', $e);
            }

            throw $e;
        }

        $aiActivationAgent = $orchestration['agents']['ai_activation_agent'] ?? [];
        $aiRetentionAgent = $orchestration['agents']['ai_retention_agent'] ?? [];
        $aiMonetizationAgent = $orchestration['agents']['ai_monetization_agent'] ?? [];
        $aiVersionAgent = $orchestration['agents']['ai_version_agent'] ?? [];
        $aiAdsAgent = $orchestration['agents']['ai_ads_agent'] ?? [];
        $aiTomorrowForecastAgent = $orchestration['agents']['ai_tomorrow_forecast_agent'] ?? [];
        $structuredNegotiation = $orchestration['structured_negotiation'] ?? [];
        $orchestratorEvidenceAssembly = $orchestration['orchestrator_evidence_assembly'] ?? [];
        $aiDecisionAgent = $orchestration['agents']['ai_final_decision_agent'] ?? [];
        $decisionScenarioSimulator = $orchestration['agents']['decision_scenario_simulator'] ?? [];

        $analysis = [
            'meta' => [
                'app_name' => $checkpoint['meta']['app_name'] ?? 'Unknown',
                'window_start' => $checkpoint['meta']['window_start'] ?? null,
                'window_end' => $checkpoint['meta']['window_end'] ?? null,
                'analyzed_at' => now()->format('Y-m-d H:i:s'),
                'architecture' => 'modular_services_plus_multi_agent_ai',
                'run_id' => $orchestration['run_id'] ?? null,
            ],
            'workflow' => $orchestration['workflow'] ?? [],
            'interaction_log' => $orchestration['interaction_log'] ?? [],
            'app_profile' => $genericMappingContext['app_profile'],
            'metric_mapping' => $genericMappingContext['metric_mapping'],
            'generic_metrics_context' => $genericMappingContext['generic_metrics_context'],
            'mapping_validation' => $genericMappingContext['mapping_validation'],
            'source_metric_refs' => $genericMappingContext['source_metric_refs'],
            'structured_negotiation' => $structuredNegotiation,
            'conflict_matrix' => $orchestration['conflict_matrix'] ?? ($structuredNegotiation['conflicts'] ?? []),
            'negotiation_summary' => $orchestration['negotiation_summary'] ?? ($structuredNegotiation['summary'] ?? []),
            'orchestrator_evidence_assembly' => $orchestratorEvidenceAssembly,
            'metrics' => [
                'activation_metrics' => $activationMetrics,
                'retention_metrics' => $retentionMetrics,
                'monetization_metrics' => $monetizationMetrics,
                'version_metrics' => $versionMetrics,
                'ads_metrics' => $adsMetrics,
                'tomorrow_forecast_metrics' => $tomorrowForecastMetrics,
                'rule_based_decision' => $ruleDecision,
                'guardrail_policy' => $guardrailPolicy,
            ],
            'evaluations' => [
                'forecast_evaluations' => $forecastEvaluations,
                'forecast_model_calibration' => $forecastCalibration,
            ],
            'agents' => [
                'activation_agent' => $activationMetrics,
                'retention_agent' => $retentionMetrics,
                'monetization_agent' => $monetizationMetrics,
                'decision_agent' => $ruleDecision,
                'ai_decision_agent' => $aiDecisionAgent,
                'ai_activation_agent' => $aiActivationAgent,
                'ai_retention_agent' => $aiRetentionAgent,
                'ai_monetization_agent' => $aiMonetizationAgent,
                'ai_version_agent' => $aiVersionAgent,
                'ai_ads_agent' => $aiAdsAgent,
                'ai_tomorrow_forecast_agent' => $aiTomorrowForecastAgent,
                'structured_negotiation' => [
                    'agent' => 'Structured Negotiation Service',
                    'status' => !empty($structuredNegotiation) ? 'active' : 'empty',
                    'result' => $structuredNegotiation,
                ],
                'orchestrator_evidence_assembly' => [
                    'agent' => 'Orchestrator Evidence Assembly',
                    'status' => !empty($orchestratorEvidenceAssembly) ? 'active' : 'empty',
                    'result' => $orchestratorEvidenceAssembly,
                ],
                'ai_final_decision_agent' => $aiDecisionAgent,
                'decision_scenario_simulator' => $decisionScenarioSimulator,
            ],
            'charts' => $metrics['charts'] ?? $this->metricsExtractor->buildChartData($activationDaily, $retentionDaily),
        ];

        $this->persistForecastArtifact($analysis);

        $defaultRunPayload = $this->prepareDefaultRunPayload($analysis, $trackedRunId);

        if ($trackedRunId) {
            $this->runProgressStore->finish($trackedRunId, $defaultRunPayload);
        }

        return $this->dashboardPayloadFromDefaultRunPayload($defaultRunPayload);
    }

    private function prepareDefaultRunPayload(array $analysis, ?string $trackedRunId = null): array
    {
        $runId = $trackedRunId ?? ($analysis['meta']['run_id'] ?? null);
        $auditPath = $runId ? $this->persistFullAuditTrace($runId, $analysis) : null;
        $compact = $this->compactSummaryPayload($analysis, $auditPath);
        $decisionPackage = $this->decisionPackage($analysis);

        return [
            'compact_summary_payload' => $compact,
            'decision_package' => $decisionPackage,
            'full_audit_trace' => [
                'available' => $auditPath !== null,
                'path' => $auditPath,
                'download_url' => $runId ? route('ai-growth-doctor.runs.audit', ['runId' => $runId], false) : null,
                'lazy_load_only' => true,
            ],
        ];
    }

    private function dashboardPayloadFromDefaultRunPayload(array $payload): array
    {
        $compact = $payload['compact_summary_payload'] ?? $payload;

        if (isset($payload['decision_package'])) {
            $compact['decision_package'] = $payload['decision_package'];
        }

        if (isset($payload['full_audit_trace'])) {
            $compact['full_audit_trace'] = $payload['full_audit_trace'];
        }

        return $compact;
    }

    private function compactSummaryPayload(array $analysis, ?string $auditPath): array
    {
        $agents = $analysis['agents'] ?? [];
        $structuredNegotiation = $analysis['structured_negotiation'] ?? [];

        return [
            'meta' => $analysis['meta'] ?? [],
            'workflow' => $analysis['workflow'] ?? [],
            'interaction_log' => [],
            'app_profile' => $analysis['app_profile'] ?? [],
            'metric_mapping' => $analysis['metric_mapping'] ?? [],
            'generic_metrics_context' => $analysis['generic_metrics_context'] ?? [],
            'mapping_validation' => $analysis['mapping_validation'] ?? [],
            'source_metric_refs' => [],
            'structured_negotiation' => $this->compactStructuredNegotiation($structuredNegotiation),
            'conflict_matrix' => $analysis['conflict_matrix'] ?? ($structuredNegotiation['conflict_matrix'] ?? []),
            'negotiation_summary' => $analysis['negotiation_summary'] ?? ($structuredNegotiation['summary'] ?? []),
            'orchestrator_evidence_assembly' => $this->compactOrchestratorEvidenceAssembly($analysis['orchestrator_evidence_assembly'] ?? []),
            'metrics' => $analysis['metrics'] ?? [],
            'evaluations' => $this->compactEvaluations($analysis['evaluations'] ?? []),
            'agents' => $this->compactAgents($agents),
            'charts' => $analysis['charts'] ?? [],
            'audit_trace_ref' => [
                'available' => $auditPath !== null,
                'path' => $auditPath,
            ],
        ];
    }

    private function decisionPackage(array $analysis): array
    {
        $structuredNegotiation = $analysis['structured_negotiation'] ?? [];
        $finalDecision = $analysis['agents']['ai_final_decision_agent']['result'] ?? ($analysis['agents']['ai_decision_agent']['result'] ?? []);

        return [
            'meta' => $analysis['meta'] ?? [],
            'guardrail_policy' => $analysis['metrics']['guardrail_policy'] ?? [],
            'structured_negotiation' => [
                'execution' => $structuredNegotiation['execution'] ?? [],
                'round_summaries' => $structuredNegotiation['round_summaries'] ?? [],
                'summary' => $structuredNegotiation['summary'] ?? [],
            ],
            'conflict_matrix' => $analysis['conflict_matrix'] ?? ($structuredNegotiation['conflict_matrix'] ?? []),
            'revised_recommendations' => $structuredNegotiation['revised_recommendations'] ?? [],
            'final_decision' => $finalDecision,
            'scenario_simulator' => $analysis['agents']['decision_scenario_simulator']['result'] ?? [],
        ];
    }

    private function persistFullAuditTrace(string $runId, array $analysis): ?string
    {
        $dir = storage_path('app/ai-growth-doctor/audit');

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $relativePath = 'ai-growth-doctor/audit/' . $runId . '.json';
        $path = storage_path('app/' . $relativePath);
        $payload = [
            'run_id' => $runId,
            'created_at' => now()->format('Y-m-d H:i:s'),
            'interaction_log' => $analysis['interaction_log'] ?? [],
            'source_metric_refs' => $analysis['source_metric_refs'] ?? [],
            'orchestrator_evidence_assembly' => $analysis['orchestrator_evidence_assembly'] ?? [],
            'full_evaluations' => $analysis['evaluations'] ?? [],
            'full_structured_negotiation' => $analysis['structured_negotiation'] ?? [],
            'full_agents' => $analysis['agents'] ?? [],
            'full_metrics' => $analysis['metrics'] ?? [],
        ];

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $relativePath;
    }

    private function compactAgents(array $agents): array
    {
        $compact = [];
        $hasFinalDecisionAgent = isset($agents['ai_final_decision_agent']);

        foreach ($agents as $key => $agent) {
            if (!is_array($agent)) {
                $compact[$key] = $agent;
                continue;
            }

            $compact[$key] = [
                'agent' => $agent['agent'] ?? $key,
                'status' => $agent['status'] ?? null,
                'execution' => $agent['execution'] ?? null,
                'model' => $agent['model'] ?? null,
                'cache' => $agent['cache'] ?? null,
                'result' => $this->compactAgentResult($key, $agent['result'] ?? [], $hasFinalDecisionAgent),
            ];
        }

        return $compact;
    }

    private function compactAgentResult(string $key, array $result, bool $hasFinalDecisionAgent): array
    {
        if ($key === 'orchestrator_evidence_assembly') {
            return $this->compactOrchestratorEvidenceAssembly($result);
        }

        if ($key === 'structured_negotiation') {
            return $this->compactStructuredNegotiation($result);
        }

        if ($key === 'ai_decision_agent' && $hasFinalDecisionAgent) {
            return [
                'mirrors' => 'ai_final_decision_agent',
                'audit_note' => 'Duplicate final decision result is stored only in full_audit_trace.',
            ];
        }

        if ($key === 'ai_final_decision_agent') {
            return [
                'available_in' => 'decision_package.final_decision',
                'audit_note' => 'Final decision payload is stored in decision_package; full request context is stored only in full_audit_trace.',
            ];
        }

        return $result;
    }

    private function compactStructuredNegotiation(array $negotiation): array
    {
        return [
            'round' => $negotiation['round'] ?? null,
            'rounds_completed' => $negotiation['rounds_completed'] ?? ($negotiation['execution']['rounds_completed'] ?? null),
            'negotiation_type' => $negotiation['negotiation_type'] ?? null,
            'rules' => $negotiation['rules'] ?? [],
            'execution' => $negotiation['execution'] ?? [],
            'round_summaries' => $negotiation['round_summaries'] ?? [],
            'negotiation_timeline' => $negotiation['negotiation_timeline'] ?? [],
            'negotiation_transcript' => array_slice($negotiation['negotiation_transcript'] ?? [], 0, 12),
            'conflict_matrix' => $negotiation['conflict_matrix'] ?? ($negotiation['conflicts'] ?? []),
            'conflicts' => $negotiation['conflict_matrix'] ?? ($negotiation['conflicts'] ?? []),
            'revised_recommendations' => $negotiation['revised_recommendations'] ?? [],
            'summary' => $negotiation['summary'] ?? [],
            'baseline_comparison' => $negotiation['baseline_comparison'] ?? [],
        ];
    }

    private function compactOrchestratorEvidenceAssembly(array $assembly): array
    {
        return [
            'present' => !empty($assembly),
            'conflict_matrix' => $assembly['conflict_matrix'] ?? [],
            'negotiation_summary' => $assembly['negotiation_summary'] ?? [],
            'final_decision_context_available_in_audit' => !empty($assembly['final_decision_context']),
            'audit_note' => 'Full orchestrator evidence assembly is stored in full_audit_trace.',
        ];
    }

    private function compactEvaluations(array $evaluations): array
    {
        $forecastEvaluations = $evaluations['forecast_evaluations'] ?? [];
        $calibration = $evaluations['forecast_model_calibration'] ?? [];

        return [
            'forecast_evaluations' => [
                'status' => $forecastEvaluations['status'] ?? null,
                'actual_data_available_until' => $forecastEvaluations['actual_data_available_until'] ?? null,
                'evaluated_count' => $forecastEvaluations['evaluated_count'] ?? null,
                'pending_count' => $forecastEvaluations['pending_count'] ?? null,
                'skipped_count' => $forecastEvaluations['skipped_count'] ?? null,
                'latest_evaluation' => $forecastEvaluations['latest_evaluation'] ?? null,
                'evaluated' => array_slice($forecastEvaluations['evaluated'] ?? [], 0, 3),
                'pending' => array_slice($forecastEvaluations['pending'] ?? [], 0, 3),
                'skipped' => array_slice($forecastEvaluations['skipped'] ?? [], 0, 3),
            ],
            'forecast_model_calibration' => [
                'status' => $calibration['status'] ?? null,
                'evaluations_used' => $calibration['evaluations_used'] ?? null,
                'overall_mature_hit_rate' => $calibration['overall_mature_hit_rate'] ?? null,
                'trust_score' => $calibration['trust_score'] ?? [],
                'decision_instruction' => $calibration['decision_instruction'] ?? [],
                'bias_detection' => $calibration['bias_detection'] ?? [],
            ],
        ];
    }
    private function archiveCheckpointSnapshot(array $checkpoint): void
    {
        $meta = $checkpoint['meta'] ?? [];
        $windowEnd = $meta['window_end'] ?? null;

        if (!$windowEnd) {
            return;
        }

        $historyDir = storage_path('app/checkpoints/history');

        if (!is_dir($historyDir)) {
            mkdir($historyDir, 0775, true);
        }

        $snapshotPath = $historyDir . '/daily_growth_checkpoint_' . $windowEnd . '.json';

        if (file_exists($snapshotPath)) {
            return;
        }

        file_put_contents(
            $snapshotPath,
            json_encode($checkpoint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function persistForecastArtifact(array $analysis): void
    {
        $forecastMetrics = $analysis['metrics']['tomorrow_forecast_metrics'] ?? [];
        $forecastAgent = $analysis['agents']['ai_tomorrow_forecast_agent'] ?? [];
        $forecastResult = $forecastAgent['result'] ?? [];

        $forecastForDate = $forecastResult['forecast_for_date'] ?? ($forecastMetrics['forecast_for_date'] ?? null);
        $dataAsOfDate = $forecastResult['data_as_of_date'] ?? ($forecastMetrics['data_as_of_date'] ?? null);

        if (!$forecastForDate || !$dataAsOfDate) {
            return;
        }

        $forecastDir = storage_path('app/ai-growth-doctor/forecasts');

        if (!is_dir($forecastDir)) {
            mkdir($forecastDir, 0775, true);
        }

        $forecastPath = $forecastDir . '/forecast_for_' . $forecastForDate . '_created_' . $dataAsOfDate . '.json';

        $payload = [
            'artifact_type' => 'tomorrow_forecast',
            'created_at' => now()->format('Y-m-d H:i:s'),
            'data_as_of_date' => $dataAsOfDate,
            'forecast_for_date' => $forecastForDate,
            'evaluation_status' => $forecastResult['evaluation_status'] ?? ($forecastMetrics['evaluation_status'] ?? 'pending_actual_data'),
            'evaluation_ready_after' => $forecastResult['evaluation_ready_after'] ?? ($forecastMetrics['evaluation_ready_after'] ?? null),
            'evaluation_rule' => $forecastResult['evaluation_rule'] ?? ($forecastMetrics['evaluation_rule'] ?? null),
            'forecast_metrics' => $forecastMetrics,
            'forecast_agent' => $forecastAgent,
        ];

        file_put_contents(
            $forecastPath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}
