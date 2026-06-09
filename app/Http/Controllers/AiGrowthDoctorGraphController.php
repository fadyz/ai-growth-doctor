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

        if (!file_exists($this->runPath($runId))) {
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

        $path = $this->runPath($runId);

        if (!file_exists($path)) {
            return response()->json([
                'error' => 'Run not found.',
                'run_id' => $runId,
            ], 404);
        }

        $run = json_decode(file_get_contents($path), true);

        if (!is_array($run)) {
            return response()->json([
                'error' => 'Run JSON is invalid.',
                'run_id' => $runId,
            ], 422);
        }

        return response()->json($this->graphBuilder->build($run));
    }

    private function validRunId(string $runId): bool
    {
        return preg_match('/^[A-Za-z0-9_-]+$/', $runId) === 1;
    }

    private function runPath(string $runId): string
    {
        return storage_path('app/ai-growth-doctor/runs/' . $runId . '.json');
    }
}
