<?php

namespace App\Services\AiGrowthDoctor;

class MetricMappingService
{
    public function defaultHitungKaloriMapping(): array
    {
        return $this->configuredDefaultMapping();
    }

    public function resolve(array $checkpointOrRun, array $appProfile): array
    {
        $result = $checkpointOrRun['result'] ?? $checkpointOrRun;
        $existing = $result['metric_mapping'] ?? ($checkpointOrRun['metric_mapping'] ?? []);

        if (is_array($existing) && !empty($existing)) {
            return $existing;
        }

        return $this->configuredDefaultMapping();
    }

    private function configuredDefaultMapping(): array
    {
        $mapping = config('ai_growth_doctor.default_metric_mapping', []);

        return is_array($mapping) ? $mapping : [];
    }
}
