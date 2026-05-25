<?php

namespace App\Console\Commands;

use App\Http\Controllers\AiGrowthDoctorController;
use App\Services\GrowthDoctor\RunProgressStore;
use Illuminate\Console\Command;

class WorkGrowthDoctorRunsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'growth-doctor:work {--once : Process only one pending run and exit} {--sleep=1 : Seconds to sleep between polling pending runs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending AI Growth Doctor runs from JSON pending queue.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(AiGrowthDoctorController $controller, RunProgressStore $runProgressStore)
    {
        $this->info('AI Growth Doctor worker started. Waiting for pending runs...');

        do {
            $pendingFile = $this->nextPendingFile();

            if (!$pendingFile) {
                if ($this->option('once')) {
                    $this->line('No pending runs found.');
                    return 0;
                }

                sleep((int) $this->option('sleep'));
                continue;
            }

            $payload = $this->readPendingPayload($pendingFile);
            $runId = $payload['run_id'] ?? null;

            if (!$runId || !preg_match('/^agd_[A-Za-z0-9_\-]+$/', $runId)) {
                $this->warn('Invalid pending run file: ' . basename($pendingFile));
                $this->movePendingFile($pendingFile, 'failed');

                if ($this->option('once')) {
                    return 1;
                }

                continue;
            }

            $this->info('Processing AI Growth Doctor run: ' . $runId);
            $this->movePendingFile($pendingFile, 'processing');

            try {
                $controller->analyzeCheckpoint($runId);
                $this->info('Completed AI Growth Doctor run: ' . $runId);
                $this->markProcessed($runId);
            } catch (\Throwable $e) {
                $runProgressStore->markFailed($runId, 'final_decision_agent', $e);
                report($e);
                $this->error('Failed AI Growth Doctor run ' . $runId . ': ' . $e->getMessage());
                $this->markFailed($runId, $e);

                if ($this->option('once')) {
                    return 1;
                }
            }

            if ($this->option('once')) {
                return 0;
            }
        } while (true);

        return 0;
    }

    private function nextPendingFile(): ?string
    {
        $pendingDir = $this->pendingDir();

        if (!is_dir($pendingDir)) {
            mkdir($pendingDir, 0775, true);
        }

        $files = glob($pendingDir . '/*.json') ?: [];

        if (empty($files)) {
            return null;
        }

        usort($files, function ($a, $b) {
            return filemtime($a) <=> filemtime($b);
        });

        return $files[0] ?? null;
    }

    private function readPendingPayload(string $file): array
    {
        $json = file_get_contents($file);
        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : [];
    }

    private function movePendingFile(string $file, string $status): void
    {
        $targetDir = storage_path('app/ai-growth-doctor/' . $status);

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $targetPath = $targetDir . '/' . basename($file);

        if (file_exists($targetPath)) {
            $targetPath = $targetDir . '/' . pathinfo($file, PATHINFO_FILENAME) . '_' . time() . '.json';
        }

        rename($file, $targetPath);
    }

    private function markProcessed(string $runId): void
    {
        $this->writeWorkerReceipt('processed', [
            'run_id' => $runId,
            'status' => 'processed',
            'processed_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function markFailed(string $runId, \Throwable $e): void
    {
        $this->writeWorkerReceipt('failed', [
            'run_id' => $runId,
            'status' => 'failed',
            'failed_at' => now()->format('Y-m-d H:i:s'),
            'error' => $e->getMessage(),
        ]);
    }

    private function writeWorkerReceipt(string $folder, array $payload): void
    {
        $dir = storage_path('app/ai-growth-doctor/' . $folder);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $dir . '/' . $payload['run_id'] . '.receipt.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function pendingDir(): string
    {
        return storage_path('app/ai-growth-doctor/pending');
    }
}
