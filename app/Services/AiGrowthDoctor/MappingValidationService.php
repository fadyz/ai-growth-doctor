<?php

namespace App\Services\AiGrowthDoctor;

class MappingValidationService
{
    private const REQUIRED_METRICS = [
        'activation.entry_users',
        'activation.core_action_success_users',
        'activation.core_action_success_rate_from_entry',
        'retention.d0_rate',
        'retention.d1_rate',
        'retention.habit_7d_rate',
        'monetization.exposure_users',
        'monetization.purchase_success_users',
    ];

    private const OPTIONAL_METRICS = [
        'ads.cost',
        'ads.conversion_rate',
        'ads.cost_per_conversion',
        'retention.avg_active_days_7d',
        'monetization.purchase_start_users',
        'monetization.purchase_success_rate_from_exposure',
    ];

    public function validate(array $genericMetricsContext, array $mapping, array $appProfile): array
    {
        $missingRequired = $this->missingMetrics(self::REQUIRED_METRICS, $genericMetricsContext);
        $missingOptional = $this->missingMetrics(self::OPTIONAL_METRICS, $genericMetricsContext);
        $dataQualityWarnings = [];
        $lowSampleWarnings = $this->lowSampleWarnings($genericMetricsContext);
        $profileMissing = $this->missingProfileFields($appProfile);

        if (!empty($profileMissing)) {
            $dataQualityWarnings[] = 'app_profile missing fields: ' . implode(', ', $profileMissing);
        }

        $status = 'valid';
        if (!empty($missingRequired)) {
            $status = 'invalid';
        } elseif (!empty($missingOptional) || !empty($dataQualityWarnings) || !empty($lowSampleWarnings)) {
            $status = 'valid_with_warnings';
        }

        return [
            'status' => $status,
            'mapped_metric_count' => count(array_filter($mapping, function ($path) {
                return $path !== null && $path !== '';
            })),
            'required_metric_count' => count(self::REQUIRED_METRICS),
            'missing_required_metrics' => $missingRequired,
            'missing_optional_metrics' => $missingOptional,
            'data_quality_warnings' => $dataQualityWarnings,
            'low_sample_warnings' => $lowSampleWarnings,
            'app_profile_complete' => empty($profileMissing),
        ];
    }

    private function missingMetrics(array $metricKeys, array $genericMetricsContext): array
    {
        $missing = [];

        foreach ($metricKeys as $key) {
            $value = $this->getByDotPath($genericMetricsContext, $key);
            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    private function lowSampleWarnings(array $context): array
    {
        $warnings = [];
        $purchaseSuccessUsers = $this->numericValue($this->getByDotPath($context, 'monetization.purchase_success_users'));
        $exposureUsers = $this->numericValue($this->getByDotPath($context, 'monetization.exposure_users'));
        $entryUsers = $this->numericValue($this->getByDotPath($context, 'activation.entry_users'));
        $adsConversions = $this->numericValue($this->getByDotPath($context, 'ads.conversions'));

        if ($purchaseSuccessUsers !== null && $purchaseSuccessUsers < 20) {
            $warnings[] = 'monetization.purchase_success_users below 20';
        }

        if ($exposureUsers !== null && $exposureUsers < 100) {
            $warnings[] = 'monetization.exposure_users below 100';
        }

        if ($entryUsers !== null && $entryUsers < 100) {
            $warnings[] = 'activation.entry_users below 100';
        }

        if ($adsConversions !== null && $adsConversions < 100) {
            $warnings[] = 'ads.conversions below 100';
        }

        return $warnings;
    }

    private function missingProfileFields(array $profile): array
    {
        $required = ['tenant_id', 'app_id', 'app_name', 'app_category', 'core_action_name', 'core_action_success_label', 'monetization_model', 'timezone', 'data_mode'];
        $missing = [];

        foreach ($required as $field) {
            if (!isset($profile[$field]) || $profile[$field] === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    private function getByDotPath(array $source, string $path)
    {
        $value = $source;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private function numericValue($value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
