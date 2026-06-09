<?php

namespace App\Services\AiGrowthDoctor;

class GenericMetricMapperService
{
    private $mappingValidationService;

    public function __construct(MappingValidationService $mappingValidationService)
    {
        $this->mappingValidationService = $mappingValidationService;
    }

    public function buildGenericContext(array $sourceMetricsContext, array $mapping, array $appProfile): array
    {
        $genericMetricsContext = [];
        $sourceMetricRefs = [];
        $missingMetrics = [];

        foreach ($mapping as $genericPath => $sourcePath) {
            $found = false;
            $value = $this->getByDotPath($sourceMetricsContext, $sourcePath, $found);
            $this->setByDotPath($genericMetricsContext, $genericPath, $found ? $value : null);

            if (!$found) {
                $missingMetrics[] = $genericPath;
            }

            $sourceMetricRefs[$genericPath] = [
                'source_path' => $sourcePath,
                'source_label' => $this->sourceLabel($sourcePath),
                'app_specific_meaning' => $this->appSpecificMeaning($genericPath, $appProfile),
                'found' => $found,
            ];
        }

        $mappingValidation = $this->mappingValidationService->validate($genericMetricsContext, $mapping, $appProfile);
        $mappingValidation['missing_mapped_metrics'] = $missingMetrics;

        return [
            'app_profile' => $appProfile,
            'metric_mapping' => $mapping,
            'generic_metrics_context' => $genericMetricsContext,
            'mapping_validation' => $mappingValidation,
            'source_metric_refs' => $sourceMetricRefs,
            'source_metrics_context' => $sourceMetricsContext,
        ];
    }

    private function getByDotPath(array $source, string $path, ?bool &$found = null)
    {
        $value = $source;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $found = false;
                return null;
            }
            $value = $value[$segment];
        }

        $found = true;
        return $value;
    }

    private function setByDotPath(array &$target, string $path, $value): void
    {
        $segments = explode('.', $path);
        $cursor =& $target;

        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $cursor[$segment] = $value;
                return;
            }

            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor =& $cursor[$segment];
        }
    }

    private function sourceLabel(string $sourcePath): string
    {
        $segments = explode('.', $sourcePath);
        return (string) end($segments);
    }

    private function appSpecificMeaning(string $genericPath, array $appProfile): string
    {
        $coreAction = $appProfile['core_action_name'] ?? 'core action';
        $coreActionLabel = $appProfile['core_action_success_label'] ?? 'core_action_success';

        if (strpos($genericPath, 'core_action_success') !== false) {
            return $coreAction . ' success' . ($coreActionLabel ? ' (' . $coreActionLabel . ')' : '');
        }

        if (strpos($genericPath, 'workspace') !== false) {
            return $appProfile['workspace_name'] ?? 'app workspace';
        }

        if (strpos($genericPath, 'monetization') === 0) {
            return ($appProfile['monetization_model'] ?? 'monetization') . ' monetization signal';
        }

        return str_replace('.', ' ', $genericPath);
    }
}
