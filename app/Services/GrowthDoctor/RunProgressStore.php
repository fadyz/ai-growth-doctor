<?php

namespace App\Services\GrowthDoctor;

use Illuminate\Support\Facades\Storage;

class RunProgressStore
{
    public function create(string $runId): array
    {
        $run = [
            'run_id' => $runId,
            'status' => 'queued',
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
            'current_step' => 'queued',
            'progress_percent' => 0,
            'steps' => $this->initialSteps(),
            'result' => null,
            'error' => null,
            'note' => 'Run created. Waiting for orchestrator to start.',
        ];

        $this->put($runId, $run);

        return $run;
    }

    public function get(string $runId): ?array
    {
        $path = $this->path($runId);

        if (!Storage::disk('local')->exists($path)) {
            return null;
        }

        $json = Storage::disk('local')->get($path);
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    public function latestCompletedResult(): ?array
    {
        $latest = $this->latestCompletedRunId();

        if ($latest) {
            $run = $this->get($latest);

            if (($run['status'] ?? null) === 'done' && !empty($run['result']) && is_array($run['result'])) {
                return $this->dashboardResult($run['result']);
            }
        }

        $directory = 'ai-growth-doctor/runs';

        if (!Storage::disk('local')->exists($directory)) {
            return null;
        }

        $files = Storage::disk('local')->files($directory);

        if (empty($files)) {
            return null;
        }

        usort($files, function (string $a, string $b) {
            return strcmp(basename($b), basename($a));
        });

        $latestRunFile = null;

        foreach ($files as $file) {
            if (substr($file, -5) === '.json') {
                $latestRunFile = $file;
                break;
            }
        }

        if (!$latestRunFile) {
            return null;
        }

        $run = json_decode(Storage::disk('local')->get($latestRunFile), true);

        if (($run['status'] ?? null) !== 'done' || empty($run['result']) || !is_array($run['result'])) {
            return null;
        }

        return $this->dashboardResult($run['result']);
    }

    public function getStatus(string $runId): ?array
    {
        $status = $this->getStatusFromIndex($runId);

        if ($status) {
            return $status;
        }

        $headerStatus = $this->getStatusFromRunHeader($runId);

        if ($headerStatus) {
            return $headerStatus;
        }

        $run = $this->get($runId);

        if (!$run) {
            return null;
        }

        return $this->statusPayload($runId, $run);
    }

    public function markRunning(string $runId, string $stepKey, ?string $note = null): array
    {
        $run = $this->getOrCreateSkeleton($runId);

        if (isset($run['steps'][$stepKey])) {
            $run['steps'][$stepKey]['status'] = 'running';
            $run['steps'][$stepKey]['started_at'] = $run['steps'][$stepKey]['started_at'] ?: $this->now();
            $run['steps'][$stepKey]['finished_at'] = null;
        }

        $run['status'] = 'running';
        $run['current_step'] = $stepKey;
        $run['updated_at'] = $this->now();
        $run['progress_percent'] = $this->calculateProgress($run);

        if ($note !== null) {
            $run['note'] = $note;
        }

        $this->put($runId, $run);

        return $run;
    }

    public function markDone(string $runId, string $stepKey, $stepResult = null, ?string $note = null): array
    {
        $run = $this->getOrCreateSkeleton($runId);

        if (isset($run['steps'][$stepKey])) {
            $execution = $this->extractExecution($stepResult);

            $finishedAt = $execution['request_finished_at'] ?? $this->now();
            $startedAt = $execution['request_started_at'] ?? $finishedAt;

            // Parallel specialist agents are launched together, so their real request_started_at
            // can be nearly identical. For the live progress cards, make the visible active time
            // follow the callback completion time so cards light up in the order agents finish.
            if (($execution['parallel_pool'] ?? false) === true) {
                $startedAt = $finishedAt;
            }

            $run['steps'][$stepKey]['status'] = 'done';
            $run['steps'][$stepKey]['started_at'] = $startedAt;
            $run['steps'][$stepKey]['finished_at'] = $finishedAt;

            if (!empty($execution)) {
                $run['steps'][$stepKey]['execution'] = $execution;
            }

            if ($stepResult !== null) {
                $run['steps'][$stepKey]['result_summary'] = $this->summarizeStepResult($stepResult);
            }
        }

        $run['status'] = 'running';
        $run['current_step'] = $stepKey;
        $run['updated_at'] = $this->now();
        $run['progress_percent'] = $this->calculateProgress($run);

        if ($note !== null) {
            $run['note'] = $note;
        }

        $this->put($runId, $run);

        return $run;
    }

    public function markFailed(string $runId, string $stepKey, $error): array
    {
        $run = $this->getOrCreateSkeleton($runId);

        if (isset($run['steps'][$stepKey])) {
            $execution = $this->extractExecution($error);

            $finishedAt = $execution['request_finished_at'] ?? $this->now();
            $startedAt = $execution['request_started_at'] ?? $finishedAt;

            if (($execution['parallel_pool'] ?? false) === true) {
                $startedAt = $finishedAt;
            }

            $run['steps'][$stepKey]['status'] = 'failed';
            $run['steps'][$stepKey]['started_at'] = $startedAt;
            $run['steps'][$stepKey]['finished_at'] = $finishedAt;
            $run['steps'][$stepKey]['error'] = $this->normalizeError($error);

            if (!empty($execution)) {
                $run['steps'][$stepKey]['execution'] = $execution;
            }
        }

        $run['status'] = 'failed';
        $run['current_step'] = $stepKey;
        $run['updated_at'] = $this->now();
        $run['error'] = $this->normalizeError($error);
        $run['progress_percent'] = $this->calculateProgress($run);

        $this->put($runId, $run);

        return $run;
    }

    public function finish(string $runId, array $result): array
    {
        $run = $this->getOrCreateSkeleton($runId);

        if (isset($run['steps']['done'])) {
            $run['steps']['done']['status'] = 'done';
            $run['steps']['done']['started_at'] = $run['steps']['done']['started_at'] ?: $this->now();
            $run['steps']['done']['finished_at'] = $this->now();
        }

        $run['status'] = 'done';
        $run['current_step'] = 'done';
        $run['updated_at'] = $this->now();
        $run['progress_percent'] = 100;
        $run['result'] = $result;
        $run['error'] = null;
        $run['note'] = 'Run completed successfully.';

        $this->put($runId, $run);

        return $run;
    }

    public function initialSteps(): array
    {
        return [
            'checkpoint_load' => $this->step('Load Checkpoint'),
            'metrics_extraction' => $this->step('Extract Metrics'),
            'activation_agent' => $this->step('Activation Agent'),
            'retention_agent' => $this->step('Retention Agent'),
            'monetization_agent' => $this->step('Monetization Agent'),
            'version_agent' => $this->step('Version Agent'),
            'ads_agent' => $this->step('Ads Agent'),
            'tomorrow_forecast_agent' => $this->step('Tomorrow Forecast Agent'),
            'structured_negotiation' => $this->step('Structured Negotiation'),
            'final_decision_agent' => $this->step('Final Decision Agent'),
            'decision_scenario_simulator' => $this->step('Decision Scenario Simulator'),
            'done' => $this->step('Done'),
        ];
    }

    private function step(string $label): array
    {
        return [
            'label' => $label,
            'status' => 'waiting',
            'started_at' => null,
            'finished_at' => null,
            'execution' => null,
        ];
    }

    private function getOrCreateSkeleton(string $runId): array
    {
        $run = $this->get($runId);

        if ($run) {
            return $run;
        }

        return $this->create($runId);
    }

    private function put(string $runId, array $run): void
    {
        Storage::disk('local')->put(
            $this->path($runId),
            json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $this->putStatus($runId, $run);

        if (($run['status'] ?? null) === 'done') {
            $this->putLatestCompleted($runId, $run);
        }
    }

    private function path(string $runId): string
    {
        return 'ai-growth-doctor/runs/' . $runId . '.json';
    }

    private function statusPath(string $runId): string
    {
        return 'ai-growth-doctor/run-status/' . $runId . '.json';
    }

    private function latestCompletedPath(): string
    {
        return 'ai-growth-doctor/latest_completed.json';
    }

    private function putStatus(string $runId, array $run): void
    {
        Storage::disk('local')->put(
            $this->statusPath($runId),
            json_encode($this->statusPayload($runId, $run), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function getStatusFromIndex(string $runId): ?array
    {
        $path = $this->statusPath($runId);

        if (!Storage::disk('local')->exists($path)) {
            return null;
        }

        $data = json_decode(Storage::disk('local')->get($path), true);

        return is_array($data) ? $data : null;
    }

    private function getStatusFromRunHeader(string $runId): ?array
    {
        $path = storage_path('app/' . $this->path($runId));

        if (!is_file($path)) {
            return null;
        }

        $handle = fopen($path, 'rb');

        if (!$handle) {
            return null;
        }

        $chunk = fread($handle, 65536);
        fclose($handle);

        if ($chunk === false || $chunk === '') {
            return null;
        }

        $extract = function (string $key) use ($chunk): ?string {
            return preg_match('/"' . preg_quote($key, '/') . '"\s*:\s*"([^"]*)"/', $chunk, $matches) === 1
                ? $matches[1]
                : null;
        };

        $progress = preg_match('/"progress_percent"\s*:\s*([0-9]+)/', $chunk, $progressMatch) === 1
            ? (int) $progressMatch[1]
            : 0;

        return [
            'run_id' => $extract('run_id') ?? $runId,
            'status' => $extract('status') ?? 'unknown',
            'created_at' => $extract('created_at'),
            'updated_at' => $extract('updated_at'),
            'current_step' => $extract('current_step'),
            'progress_percent' => $progress,
            'steps' => [],
            'error' => null,
            'note' => $extract('note'),
            'has_result' => strpos($chunk, '"result"') !== false,
        ];
    }

    private function statusPayload(string $runId, array $run): array
    {
        return [
            'run_id' => $run['run_id'] ?? $runId,
            'status' => $run['status'] ?? 'unknown',
            'created_at' => $run['created_at'] ?? null,
            'updated_at' => $run['updated_at'] ?? null,
            'current_step' => $run['current_step'] ?? null,
            'progress_percent' => $run['progress_percent'] ?? 0,
            'steps' => $run['steps'] ?? [],
            'error' => $run['error'] ?? null,
            'note' => $run['note'] ?? null,
            'has_result' => !empty($run['result']),
        ];
    }

    private function putLatestCompleted(string $runId, array $run): void
    {
        Storage::disk('local')->put(
            $this->latestCompletedPath(),
            json_encode([
                'run_id' => $runId,
                'updated_at' => $run['updated_at'] ?? $this->now(),
                'result_path' => $this->path($runId),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function latestCompletedRunId(): ?string
    {
        $path = $this->latestCompletedPath();

        if (!Storage::disk('local')->exists($path)) {
            return null;
        }

        $data = json_decode(Storage::disk('local')->get($path), true);

        return is_array($data) ? ($data['run_id'] ?? null) : null;
    }

    private function dashboardResult(array $result): array
    {
        if (!isset($result['compact_summary_payload']) || !is_array($result['compact_summary_payload'])) {
            return $result;
        }

        $compact = $result['compact_summary_payload'];

        if (isset($result['decision_package'])) {
            $compact['decision_package'] = $result['decision_package'];
        }

        if (isset($result['full_audit_trace'])) {
            $compact['full_audit_trace'] = $result['full_audit_trace'];
        }

        return $compact;
    }

    private function calculateProgress(array $run): int
    {
        $steps = $run['steps'] ?? [];

        if (empty($steps)) {
            return 0;
        }

        $done = 0;
        $total = count($steps);

        foreach ($steps as $step) {
            if (($step['status'] ?? null) === 'done') {
                $done++;
            }
        }

        return (int) floor(($done / $total) * 100);
    }

    private function extractExecution($result): array
    {
        if (!is_array($result)) {
            return [];
        }

        $execution = $result['execution']
            ?? ($result['payload']['execution'] ?? null)
            ?? ($result['result']['execution'] ?? null);

        if (!is_array($execution)) {
            return [];
        }

        $normalized = [
            'mode' => $execution['mode'] ?? null,
            'request_key' => $execution['request_key'] ?? null,
            'parallel_pool' => (bool) ($execution['parallel_pool'] ?? false),
            'request_started_at' => $execution['request_started_at'] ?? null,
            'request_finished_at' => $execution['request_finished_at'] ?? null,
            'request_duration_ms' => $execution['request_duration_ms'] ?? null,
        ];

        foreach (['max_rounds', 'rounds_completed', 'early_exit', 'early_exit_reason', 'agent_response_count', 'conflict_count', 'total_conflict_count', 'material_conflict_count', 'critical_conflict_count', 'material_or_higher_conflict_count'] as $key) {
            if (array_key_exists($key, $execution)) {
                $normalized[$key] = $execution[$key];
            }
        }

        return array_filter($normalized, function ($value) {
            return $value !== null;
        });
    }

    private function summarizeStepResult($result): ?string
    {
        if (!is_array($result)) {
            return null;
        }

        if (in_array($result['negotiation_type'] ?? null, ['adaptive_structured_cross_examination', 'single_round_structured_cross_examination'], true)) {
            $summary = $result['summary'] ?? [];
            $total = (int) ($summary['total_conflict_count'] ?? count($result['conflicts'] ?? []));
            $critical = (int) ($summary['critical_conflict_count'] ?? 0);
            $material = (int) ($summary['material_conflict_count'] ?? 0);
            $roundsCompleted = $result['execution']['rounds_completed'] ?? ($result['rounds_completed'] ?? null);

            if ($total === 0) {
                return ($roundsCompleted ? $roundsCompleted . ' round(s); ' : '') . '0 conflicts detected; no material objection';
            }

            $conflictLabel = $total === 1 ? 'conflict' : 'conflicts';

            return ($roundsCompleted ? $roundsCompleted . ' round(s); ' : '') . $total . ' ' . $conflictLabel . ' detected: ' . $critical . ' critical, ' . $material . ' material';
        }

        if (!empty($result['status'])) {
            return (string) $result['status'];
        }

        if (!empty($result['result']['status'])) {
            return (string) $result['result']['status'];
        }

        if (!empty($result['result']['business_verdict'])) {
            return (string) $result['result']['business_verdict'];
        }

        if (!empty($result['agent'])) {
            return (string) $result['agent'];
        }

        return null;
    }

    private function normalizeError($error): string
    {
        if ($error instanceof \Throwable) {
            return $error->getMessage();
        }

        if (is_string($error)) {
            return $error;
        }

        return json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'Unknown error';
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }
}
