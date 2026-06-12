<?php

namespace App\Http\Controllers;

use App\Services\AiGrowthDoctor\AiGrowthDoctorGraphBuilder;
use Illuminate\Http\JsonResponse;

class AiGrowthDoctorGraphController extends Controller
{
    private $graphBuilder;

    public function __construct(AiGrowthDoctorGraphBuilder $graphBuilder)
    {
        $this->graphBuilder = $graphBuilder;
    }

    public function show(string $runId)
    {
        if (!$this->validRunId($runId)) {
            abort(404);
        }

        if (!file_exists($this->auditPath($runId))) {
            abort(404);
        }

        return view('ai-growth-doctor.graph-view', [
            'runId' => $runId,
        ]);
    }

    public function graph(string $runId): JsonResponse
    {
        if (!$this->validRunId($runId)) {
            return response()->json(['error' => 'Invalid run id.'], 422);
        }

        $path = $this->auditPath($runId);

        if (!file_exists($path)) {
            return response()->json([
                'error' => 'Full audit trace not found.',
                'run_id' => $runId,
            ], 404);
        }

        $audit = json_decode(file_get_contents($path), true);

        if (!is_array($audit)) {
            return response()->json([
                'error' => 'Full audit trace JSON is invalid.',
                'run_id' => $runId,
            ], 422);
        }

        return response()->json($this->graphBuilder->build($this->graphRunPayloadFromAudit($audit)));
    }

    private function graphRunPayloadFromAudit(array $audit): array
    {
        $metricsContext = $audit['orchestrator_evidence_assembly']['metrics_context'] ?? [];
        $fullMetrics = $audit['full_metrics'] ?? [];
        $metrics = array_merge(is_array($fullMetrics) ? $fullMetrics : [], [
            'guardrail_policy' => $metricsContext['guardrail_policy'] ?? ($fullMetrics['guardrail_policy'] ?? []),
        ]);

        $run = [
            'run_id' => $audit['run_id'] ?? null,
            'status' => 'done',
            'result' => [
                'meta' => [
                    'run_id' => $audit['run_id'] ?? null,
                    'architecture' => 'full_audit_trace_graph',
                ],
                'interaction_log' => $audit['interaction_log'] ?? [],
                'source_metric_refs' => $audit['source_metric_refs'] ?? ($metricsContext['source_metric_refs'] ?? []),
                'app_profile' => $metricsContext['app_profile'] ?? [],
                'metric_mapping' => $metricsContext['metric_mapping'] ?? [],
                'generic_metrics_context' => $metricsContext['generic_metrics_context'] ?? [],
                'mapping_validation' => $metricsContext['mapping_validation'] ?? [],
                'metrics' => $metrics,
                'evaluations' => $audit['full_evaluations'] ?? [],
                'agents' => $audit['full_agents'] ?? [],
                'structured_negotiation' => $audit['full_structured_negotiation'] ?? [],
                'conflict_matrix' => $audit['full_structured_negotiation']['conflict_matrix']
                    ?? ($audit['full_structured_negotiation']['conflicts'] ?? []),
                'negotiation_summary' => $audit['full_structured_negotiation']['summary'] ?? [],
                'orchestrator_evidence_assembly' => $audit['orchestrator_evidence_assembly'] ?? [],
                'quantitative_baseline_comparison' => $audit['quantitative_baseline_comparison']
                    ?? ($audit['full_structured_negotiation']['quantitative_baseline_comparison'] ?? []),
            ],
        ];

        return $run;
    }

    private function validRunId(string $runId): bool
    {
        return preg_match('/^[A-Za-z0-9_-]+$/', $runId) === 1;
    }

    private function auditPath(string $runId): string
    {
        return storage_path('app/ai-growth-doctor/audit/' . $runId . '.json');
    }
}
