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

    public function __construct(
        CheckpointRepository $checkpointRepository,
        MetricsExtractor $metricsExtractor,
        GuardrailPolicyEngine $guardrailPolicyEngine,
        ForecastEvaluationService $forecastEvaluationService,
        ForecastCalibrationService $forecastCalibrationService,
        AgentOrchestrator $agentOrchestrator,
        RunProgressStore $runProgressStore
    ) {
        $this->checkpointRepository = $checkpointRepository;
        $this->metricsExtractor = $metricsExtractor;
        $this->guardrailPolicyEngine = $guardrailPolicyEngine;
        $this->forecastEvaluationService = $forecastEvaluationService;
        $this->forecastCalibrationService = $forecastCalibrationService;
        $this->agentOrchestrator = $agentOrchestrator;
        $this->runProgressStore = $runProgressStore;
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

        $run = $this->runProgressStore->get($runId);

        if (!$run) {
            return response()->json([
                'error' => 'Run not found.',
                'run_id' => $runId,
            ], 404);
        }

        return response()->json($run);
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
                'ai_final_decision_agent' => [],
                'decision_scenario_simulator' => [],
            ],
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
                'ai_final_decision_agent' => $aiDecisionAgent,
                'decision_scenario_simulator' => $decisionScenarioSimulator,
            ],
            'charts' => $metrics['charts'] ?? $this->metricsExtractor->buildChartData($activationDaily, $retentionDaily),
        ];

        $this->persistForecastArtifact($analysis);

        if ($trackedRunId) {
            $this->runProgressStore->finish($trackedRunId, $analysis);
        }

        return $analysis;
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