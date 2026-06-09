<?php

namespace App\Services\AiGrowthDoctor;

class AppProfileService
{
    public function defaultHitungKaloriProfile(): array
    {
        return $this->configuredDefaultProfile();
    }

    public function resolve(array $checkpointOrRun): array
    {
        $result = $checkpointOrRun['result'] ?? $checkpointOrRun;
        $existing = $result['app_profile'] ?? ($checkpointOrRun['app_profile'] ?? []);

        if (is_array($existing) && !empty($existing)) {
            return array_merge($this->fallbackProfile($result), $existing);
        }

        $profile = $this->configuredDefaultProfile();
        $appName = $result['meta']['app_name'] ?? ($checkpointOrRun['meta']['app_name'] ?? null);

        if ($appName && $appName !== ($profile['app_name'] ?? null)) {
            $profile = array_merge($this->fallbackProfile($result), [
                'app_name' => $appName,
            ]);
        }

        return $profile;
    }

    private function configuredDefaultProfile(): array
    {
        $profile = config('ai_growth_doctor.default_app_profile', []);

        if (is_array($profile) && !empty($profile)) {
            return array_merge($this->fallbackProfile([]), $profile);
        }

        return $this->fallbackProfile([]);
    }

    private function fallbackProfile(array $result): array
    {
        return [
            'tenant_id' => 'tenant_demo_001',
            'app_id' => 'demo_mobile_app',
            'app_name' => $result['meta']['app_name'] ?? 'Demo Mobile App',
            'app_category' => 'mobile_app',
            'core_action_name' => 'core action',
            'core_action_success_label' => 'core_action_success',
            'workspace_name' => 'app workspace',
            'monetization_model' => 'unknown',
            'timezone' => 'UTC',
            'data_mode' => 'aggregated_metrics',
        ];
    }
}
