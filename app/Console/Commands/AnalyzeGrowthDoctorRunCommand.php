<?php

namespace App\Console\Commands;

use App\Http\Controllers\AiGrowthDoctorController;
use App\Services\GrowthDoctor\RunProgressStore;
use Illuminate\Console\Command;

class AnalyzeGrowthDoctorRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'growth-doctor:analyze-run {runId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run AI Growth Doctor analysis for a tracked run id and update JSON progress.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(AiGrowthDoctorController $controller, RunProgressStore $runProgressStore)
    {
        $runId = (string) $this->argument('runId');

        if (!preg_match('/^agd_[A-Za-z0-9_\-]+$/', $runId)) {
            $this->error('Invalid run id: ' . $runId);
            return 1;
        }

        $this->info('Starting AI Growth Doctor run: ' . $runId);

        try {
            $controller->analyzeCheckpoint($runId);
            $this->info('AI Growth Doctor run completed: ' . $runId);
            return 0;
        } catch (\Throwable $e) {
            $runProgressStore->markFailed($runId, 'final_decision_agent', $e);
            report($e);

            $this->error('AI Growth Doctor run failed: ' . $e->getMessage());
            return 1;
        }
    }
}
