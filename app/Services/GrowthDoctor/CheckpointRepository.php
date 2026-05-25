<?php

namespace App\Services\GrowthDoctor;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CheckpointRepository
{
    private $path = 'checkpoints/daily_growth_checkpoint_30d.json';

    public function exists(): bool
    {
        return Storage::exists($this->path);
    }

    public function expectedPath(): string
    {
        return storage_path('app/' . $this->path);
    }

    public function loadLatest(): array
    {
        if (!$this->exists()) {
            throw new RuntimeException('Checkpoint JSON not found: ' . $this->expectedPath());
        }

        $checkpoint = json_decode(Storage::get($this->path), true);

        if (!$checkpoint) {
            throw new RuntimeException('Invalid checkpoint JSON: ' . json_last_error_msg());
        }

        return $checkpoint;
    }
}