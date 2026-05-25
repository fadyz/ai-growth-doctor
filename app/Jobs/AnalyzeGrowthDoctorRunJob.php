<?php

namespace App\Jobs;

use App\Http\Controllers\AiGrowthDoctorController;
use App\Services\GrowthDoctor\RunProgressStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeGrowthDoctorRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 300;

    private $runId;

    public function __construct(string $runId)
    {
        $this->runId = $runId;
    }

    public function handle(AiGrowthDoctorController $controller, RunProgressStore $runProgressStore): void
    {
        try {
            $controller->analyzeCheckpoint($this->runId);
        } catch (\Throwable $e) {
            $runProgressStore->markFailed($this->runId, 'final_decision_agent', $e);
            report($e);

            throw $e;
        }
    }
}
