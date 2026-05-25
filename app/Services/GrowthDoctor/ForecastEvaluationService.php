<?php

namespace App\Services\GrowthDoctor;

class ForecastEvaluationService
{
    private const EVALUATION_LOGIC_VERSION = 'v2_group_actual_availability';
    public function evaluateReadyForecasts(array $checkpoint): array
    {
        $checkpointWindowEnd = $checkpoint['meta']['window_end'] ?? null;
        $actualDataAvailableUntil = $this->actualDataCompleteUntil($checkpoint);
        $actualAvailabilityByGroup = $this->actualDataAvailabilityByGroup($checkpoint);

        if (!$actualDataAvailableUntil) {
            return [
                'status' => 'no_completed_actual_data',
                'checkpoint_window_end' => $checkpointWindowEnd,
                'actual_data_available_until' => null,
                'actual_availability_by_group' => $actualAvailabilityByGroup,
                'actual_data_interpretation' => 'No completed daily actual data is available for evaluation.',
                'evaluated' => [],
                'pending' => [],
                'skipped' => [],
            ];
        }

        $forecastFiles = $this->forecastFiles();
        $evaluated = [];
        $pending = [];
        $skipped = [];

        foreach ($forecastFiles as $forecastFile) {
            $forecast = $this->readJsonFile($forecastFile);
            $forecastForDate = $forecast['forecast_for_date'] ?? null;
            $dataAsOfDate = $forecast['data_as_of_date'] ?? null;

            if (!$forecastForDate || !$dataAsOfDate) {
                $skipped[] = [
                    'file' => basename($forecastFile),
                    'reason' => 'missing_forecast_for_date_or_data_as_of_date',
                ];
                continue;
            }

            if (strcmp($actualDataAvailableUntil, $forecastForDate) < 0) {
                $pending[] = [
                    'file' => basename($forecastFile),
                    'forecast_for_date' => $forecastForDate,
                    'data_as_of_date' => $dataAsOfDate,
                    'actual_data_available_until' => $actualDataAvailableUntil,
                    'checkpoint_window_end' => $checkpointWindowEnd,
                    'status' => 'pending_actual_data',
                ];
                continue;
            }

            $evaluationPath = $this->evaluationPath($forecastForDate, $dataAsOfDate);

            if (file_exists($evaluationPath)) {
                $cachedEvaluation = $this->readJsonFile($evaluationPath);

                if (!$this->shouldRecomputeEvaluation($cachedEvaluation, $actualDataAvailableUntil, $actualAvailabilityByGroup)) {
                    $evaluated[] = $cachedEvaluation;
                    continue;
                }
            }

            $actualMetrics = $this->actualMetricsForDate($checkpoint, $forecastForDate);

            if (empty($actualMetrics)) {
                $skipped[] = [
                    'file' => basename($forecastFile),
                    'forecast_for_date' => $forecastForDate,
                    'reason' => 'actual_metrics_not_found_for_forecast_date',
                ];
                continue;
            }

            $evaluation = $this->evaluateForecast($forecast, $actualMetrics, $actualDataAvailableUntil, $actualAvailabilityByGroup);
            $this->writeJsonFile($evaluationPath, $evaluation);
            $evaluated[] = $evaluation;
        }

        return [
            'status' => 'ok',
            'checkpoint_window_end' => $checkpointWindowEnd,
            'actual_data_available_until' => $actualDataAvailableUntil,
            'actual_availability_by_group' => $actualAvailabilityByGroup,
            'actual_data_interpretation' => 'Evaluation uses the latest completed daily data. If checkpoint window_end is the same day as generated_at, window_end is treated as partial and excluded.',
            'evaluated_count' => count($evaluated),
            'pending_count' => count($pending),
            'skipped_count' => count($skipped),
            'evaluated' => $evaluated,
            'pending' => $pending,
            'skipped' => $skipped,
        ];
    }

    private function actualDataCompleteUntil(array $checkpoint): ?string
    {
        $meta = $checkpoint['meta'] ?? [];
        $windowEnd = $meta['window_end'] ?? null;

        if (!$windowEnd) {
            return null;
        }

        $generatedAt = $meta['generated_at'] ?? null;
        $generatedDate = $generatedAt ? substr((string) $generatedAt, 0, 10) : null;

        if ($generatedDate && strcmp($generatedDate, $windowEnd) <= 0) {
            return date('Y-m-d', strtotime($windowEnd . ' -1 day'));
        }

        return $windowEnd;
    }

    private function evaluateForecast(array $forecast, array $actualMetrics, string $actualDataAvailableUntil, array $actualAvailabilityByGroup = []): array
    {
        $forecastForDate = $forecast['forecast_for_date'] ?? null;
        $dataAsOfDate = $forecast['data_as_of_date'] ?? null;
        $predictedMetrics = $forecast['forecast_agent']['result']['predicted_metrics']
            ?? $forecast['forecast_metrics']['predicted_metrics']
            ?? [];
        $forecastMetricPolicy = $forecast['metric_maturity_policy']
            ?? ($forecast['forecast_metrics']['metric_maturity_policy'] ?? []);

        $metricEvaluations = [];

        foreach ($predictedMetrics as $groupName => $groupMetrics) {
            if (!is_array($groupMetrics)) {
                continue;
            }

            foreach ($groupMetrics as $metricName => $prediction) {
                if (!is_array($prediction)) {
                    continue;
                }

                $low = $this->numericOrNull($prediction['low'] ?? null);
                $point = $this->numericOrNull($prediction['point'] ?? null);
                $high = $this->numericOrNull($prediction['high'] ?? null);

                if ($point === null || $low === null || $high === null) {
                    $metricEvaluations[$groupName][$metricName] = [
                        'status' => 'invalid_forecast_metric',
                        'quality' => 'invalid_forecast_metric',
                        'actual' => null,
                        'forecast_point' => $point !== null ? $this->formatNumber($point) : null,
                        'forecast_low' => $low !== null ? $this->formatNumber($low) : null,
                        'forecast_high' => $high !== null ? $this->formatNumber($high) : null,
                        'range_hit' => null,
                        'absolute_error' => null,
                        'direction_vs_point' => null,
                        'reason_code' => 'forecast_point_low_or_high_missing',
                    ];
                    continue;
                }

                $groupActualDataAvailableUntil = $this->actualDataAvailableUntilForGroup(
                    (string) $groupName,
                    $actualAvailabilityByGroup,
                    $actualDataAvailableUntil
                );

                $maturity = $this->metricMaturityStatus(
                    $forecastMetricPolicy,
                    (string) $groupName,
                    (string) $metricName,
                    $forecastForDate ? (string) $forecastForDate : null,
                    $groupActualDataAvailableUntil
                );

                if (($maturity['status'] ?? null) !== 'mature') {
                    $metricEvaluations[$groupName][$metricName] = [
                        'status' => 'pending_maturity',
                        'quality' => 'pending_maturity',
                        'actual' => array_key_exists($metricName, $actualMetrics) ? $this->formatNullableNumber($this->numericOrNull($actualMetrics[$metricName] ?? null)) : null,
                        'forecast_point' => $this->formatNumber($point),
                        'forecast_low' => $this->formatNumber($low),
                        'forecast_high' => $this->formatNumber($high),
                        'range_hit' => null,
                        'absolute_error' => null,
                        'direction_vs_point' => null,
                        'reason_code' => $maturity['reason_code'] ?? 'metric_not_mature_yet',
                        'maturity' => $maturity,
                        'actual_data_available_until_for_group' => $groupActualDataAvailableUntil,
                    ];
                    continue;
                }

                if (!array_key_exists($metricName, $actualMetrics)) {
                    $sourceAvailable = $this->isActualSourceAvailableForForecastDate(
                        $forecastForDate ? (string) $forecastForDate : null,
                        $groupActualDataAvailableUntil
                    );
                    $quality = $sourceAvailable ? 'missing_actual_metric' : 'pending_actual_data';

                    $metricEvaluations[$groupName][$metricName] = [
                        'status' => $quality,
                        'quality' => $quality,
                        'actual' => null,
                        'forecast_point' => $this->formatNumber($point),
                        'forecast_low' => $this->formatNumber($low),
                        'forecast_high' => $this->formatNumber($high),
                        'range_hit' => null,
                        'absolute_error' => null,
                        'direction_vs_point' => null,
                        'reason_code' => $sourceAvailable
                            ? 'actual_metric_not_found_for_' . $groupName . '.' . $metricName
                            : 'actual_source_not_available_for_' . $groupName . '_on_forecast_date',
                        'actual_data_available_until_for_group' => $groupActualDataAvailableUntil,
                        'maturity' => $maturity,
                    ];
                    continue;
                }

                $actual = $this->numericOrNull($actualMetrics[$metricName] ?? null);

                if ($actual === null) {
                    $metricEvaluations[$groupName][$metricName] = [
                        'status' => 'missing_actual_metric',
                        'quality' => 'missing_actual_metric',
                        'actual' => null,
                        'forecast_point' => $this->formatNumber($point),
                        'forecast_low' => $this->formatNumber($low),
                        'forecast_high' => $this->formatNumber($high),
                        'range_hit' => null,
                        'absolute_error' => null,
                        'direction_vs_point' => null,
                        'reason_code' => 'actual_metric_value_null_for_' . $groupName . '.' . $metricName,
                        'actual_data_available_until_for_group' => $groupActualDataAvailableUntil,
                        'maturity' => $maturity,
                    ];
                    continue;
                }

                $absoluteError = abs($actual - $point);
                $rangeHit = $actual >= $low && $actual <= $high;
                $direction = $actual > $point ? 'above_point' : ($actual < $point ? 'below_point' : 'equal_point');

                $metricEvaluations[$groupName][$metricName] = [
                    'status' => $rangeHit ? 'hit' : ($actual < $low ? 'miss_low' : 'miss_high'),
                    'actual' => $this->formatNumber($actual),
                    'forecast_point' => $this->formatNumber($point),
                    'forecast_low' => $this->formatNumber($low),
                    'forecast_high' => $this->formatNumber($high),
                    'range_hit' => $rangeHit,
                    'absolute_error' => $this->formatNumber($absoluteError),
                    'direction_vs_point' => $direction,
                    'quality' => $rangeHit ? 'hit' : ($actual < $low ? 'miss_low' : 'miss_high'),
                    'maturity' => $maturity,
                    'actual_data_available_until_for_group' => $groupActualDataAvailableUntil,
                ];
            }
        }

        $flatEvaluations = $this->flattenMetricEvaluations($metricEvaluations);
        $comparableEvaluations = array_values(array_filter($flatEvaluations, function ($row) {
            return in_array($row['quality'] ?? null, ['hit', 'miss_low', 'miss_high'], true);
        }));
        $pendingMaturityCount = $this->countMetricEvaluationsByQuality($flatEvaluations, ['pending_maturity']);
        $pendingActualDataCount = $this->countMetricEvaluationsByQuality($flatEvaluations, ['pending_actual_data']);
        $missingActualCount = $this->countMetricEvaluationsByQuality($flatEvaluations, ['missing_actual_metric']);
        $invalidForecastCount = $this->countMetricEvaluationsByQuality($flatEvaluations, ['invalid_forecast_metric']);
        $hitCount = count(array_filter($comparableEvaluations, function ($row) {
            return ($row['range_hit'] ?? false) === true;
        }));
        $totalCount = count($comparableEvaluations);
        $metricTotalCount = count($flatEvaluations);
        $hitRate = $totalCount > 0 ? round(($hitCount / $totalCount) * 100, 2) : null;

        return [
            'artifact_type' => 'forecast_evaluation',
            'created_at' => now()->format('Y-m-d H:i:s'),
            'forecast_for_date' => $forecastForDate,
            'data_as_of_date' => $dataAsOfDate,
            'actual_data_available_until' => $actualDataAvailableUntil,
            'actual_availability_by_group' => $actualAvailabilityByGroup,
            'evaluation_logic_version' => self::EVALUATION_LOGIC_VERSION,
            'evaluation_status' => 'evaluated',
            'forecast_file' => $this->forecastFileName($forecastForDate, $dataAsOfDate),
            'summary' => [
                'metric_total_count' => $metricTotalCount,
                'metrics_evaluated' => $totalCount,
                'metrics_hit' => $hitCount,
                'metrics_pending_maturity' => $pendingMaturityCount,
                'metrics_pending_actual_data' => $pendingActualDataCount,
                'metrics_missing_actual' => $missingActualCount,
                'metrics_invalid_forecast' => $invalidForecastCount,
                'hit_rate' => $hitRate,
                'forecast_quality' => $this->forecastQuality($hitRate, $totalCount),
                'main_misses' => $this->mainMisses($metricEvaluations),
            ],
            'actual_metrics' => $actualMetrics,
            'metric_evaluations' => $metricEvaluations,
        ];
    }

    private function actualMetricsForDate(array $checkpoint, string $date): array
    {
        $activationRows = $checkpoint['activation_daily'] ?? [];
        $retentionRows = $checkpoint['retention_daily'] ?? [];

        $activationActual = $this->aggregateActivationActual($activationRows, $date);
        $retentionActual = $this->aggregateRetentionActual($retentionRows, $date);

        return array_merge($activationActual, $retentionActual);
    }

    private function aggregateActivationActual(array $rows, string $date): array
    {
        $actual = [
            'session_users' => 0,
            'workspace_users' => 0,
            'food_add_success_users' => 0,
            'paywall_view_users' => 0,
            'purchase_success_users' => 0,
        ];

        foreach ($rows as $row) {
            $rowDate = $row['event_date'] ?? $row['date'] ?? null;

            if ($rowDate !== $date) {
                continue;
            }

            $actual['session_users'] += (int) ($row['session_users'] ?? 0);
            $actual['workspace_users'] += (int) ($row['workspace_users'] ?? 0);
            $actual['food_add_success_users'] += (int) ($row['food_add_success_users'] ?? 0);
            $actual['paywall_view_users'] += (int) ($row['paywall_view_users'] ?? 0);
            $actual['purchase_success_users'] += (int) ($row['purchase_success_users'] ?? 0);
        }

        if (
            $actual['session_users'] === 0
            && $actual['workspace_users'] === 0
            && $actual['food_add_success_users'] === 0
            && $actual['paywall_view_users'] === 0
            && $actual['purchase_success_users'] === 0
        ) {
            return [];
        }

        $actual['workspace_rate_from_session'] = $this->percent($actual['workspace_users'], $actual['session_users']);
        $actual['food_add_success_rate_from_session'] = $this->percent($actual['food_add_success_users'], $actual['session_users']);
        $actual['food_add_success_rate_from_workspace'] = $this->percent($actual['food_add_success_users'], $actual['workspace_users']);
        $actual['purchase_success_rate_from_paywall'] = $this->percent($actual['purchase_success_users'], $actual['paywall_view_users']);

        return $actual;
    }
    private function actualDataAvailabilityByGroup(array $checkpoint): array
    {
        $activationUntil = $this->latestDateInRows($checkpoint['activation_daily'] ?? [], ['event_date', 'date']);
        $retentionUntil = $this->latestDateInRows($checkpoint['retention_daily'] ?? [], ['join_date', 'date', 'event_date', 'cohort_date']);

        return [
            'activation' => $activationUntil,
            'monetization' => $activationUntil,
            'retention' => $retentionUntil,
        ];
    }

    private function actualDataAvailableUntilForGroup(string $groupName, array $actualAvailabilityByGroup, string $fallback): string
    {
        if ($groupName === 'monetization') {
            return $actualAvailabilityByGroup['monetization']
                ?? $actualAvailabilityByGroup['activation']
                ?? $fallback;
        }

        return $actualAvailabilityByGroup[$groupName] ?? $fallback;
    }

    private function isActualSourceAvailableForForecastDate(?string $forecastForDate, ?string $actualDataAvailableUntilForGroup): bool
    {
        if (!$forecastForDate || !$actualDataAvailableUntilForGroup) {
            return false;
        }

        return strcmp($actualDataAvailableUntilForGroup, $forecastForDate) >= 0;
    }

    private function latestDateInRows(array $rows, array $dateKeys): ?string
    {
        $dates = [];

        foreach ($rows as $row) {
            foreach ($dateKeys as $dateKey) {
                if (!empty($row[$dateKey])) {
                    $dates[] = substr((string) $row[$dateKey], 0, 10);
                    break;
                }
            }
        }

        $dates = array_values(array_filter($dates));

        if (empty($dates)) {
            return null;
        }

        sort($dates);

        return end($dates) ?: null;
    }

    private function aggregateRetentionActual(array $rows, string $date): array
    {
        foreach ($rows as $row) {
            $rowDate = $row['join_date']
                ?? $row['date']
                ?? $row['event_date']
                ?? $row['cohort_date']
                ?? null;

            if ($rowDate !== $date) {
                continue;
            }

            return [
                'new_users' => $this->numericOrNull($row['new_users'] ?? null),
                'd0_logged_rate' => $this->numericOrNull($row['d0_logged_rate'] ?? $row['rate_d0_logged'] ?? $row['d0_rate'] ?? null),
                'd1_logged_rate' => $this->numericOrNull($row['d1_logged_rate'] ?? $row['rate_d1_logged'] ?? $row['d1_rate'] ?? null),
                'habit_7d_rate' => $this->numericOrNull($row['habit_7d_rate'] ?? $row['rate_habit_7d'] ?? $row['habit_rate'] ?? null),
                'avg_log_days_7d' => $this->numericOrNull($row['avg_log_days_7d'] ?? null),
            ];
        }

        return [];
    }

    private function metricMaturityStatus(array $forecastMetricPolicy, string $groupName, string $metricName, ?string $forecastForDate, string $actualDataAvailableUntil): array
    {
        $policy = $forecastMetricPolicy[$groupName][$metricName] ?? null;
        $lagDays = (int)($policy['lag_days'] ?? $this->metricDefaultLagDays($metricName));
        $requiredDate = $policy['required_actual_until']
            ?? ($forecastForDate ? date('Y-m-d', strtotime($forecastForDate . ' +' . $lagDays . ' day')) : null);

        if (!$forecastForDate || !$requiredDate) {
            return [
                'status' => 'pending_maturity',
                'is_mature' => false,
                'lag_days' => $lagDays,
                'required_actual_until' => $requiredDate,
                'actual_data_available_until' => $actualDataAvailableUntil,
                'reason_code' => 'forecast_date_missing',
                'policy' => $policy,
            ];
        }

        $isMature = strcmp($actualDataAvailableUntil, $requiredDate) >= 0;

        return [
            'status' => $isMature ? 'mature' : 'pending_maturity',
            'is_mature' => $isMature,
            'lag_days' => $lagDays,
            'required_actual_until' => $requiredDate,
            'actual_data_available_until' => $actualDataAvailableUntil,
            'reason_code' => $isMature ? 'metric_mature' : 'actual_data_not_available_until_required_maturity_date',
            'policy' => $policy,
        ];
    }

    private function metricDefaultLagDays(string $metricName): int
    {
        if ($metricName === 'd1_logged_rate') {
            return 1;
        }

        if (in_array($metricName, ['habit_7d_rate', 'avg_log_days_7d'], true)) {
            return 6;
        }

        return 0;
    }

    private function forecastFiles(): array
    {
        $dir = storage_path('app/ai-growth-doctor/forecasts');

        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/forecast_for_*_created_*.json') ?: [];

        usort($files, function ($a, $b) {
            return filemtime($a) <=> filemtime($b);
        });

        return $files;
    }

    private function evaluationPath(string $forecastForDate, string $dataAsOfDate): string
    {
        $dir = storage_path('app/ai-growth-doctor/evaluations');

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir . '/evaluation_for_' . $forecastForDate . '_created_' . $dataAsOfDate . '.json';
    }

    private function forecastFileName(?string $forecastForDate, ?string $dataAsOfDate): ?string
    {
        if (!$forecastForDate || !$dataAsOfDate) {
            return null;
        }

        return 'forecast_for_' . $forecastForDate . '_created_' . $dataAsOfDate . '.json';
    }

    private function readJsonFile(string $path): array
    {
        $json = file_get_contents($path);
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    private function writeJsonFile(string $path, array $data): void
    {
        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function shouldRecomputeEvaluation(array $cachedEvaluation, string $actualDataAvailableUntil, array $actualAvailabilityByGroup): bool
    {
        if (($cachedEvaluation['evaluation_logic_version'] ?? null) !== self::EVALUATION_LOGIC_VERSION) {
            return true;
        }

        $cachedActualUntil = $cachedEvaluation['actual_data_available_until'] ?? null;

        if (!$cachedActualUntil || strcmp($actualDataAvailableUntil, $cachedActualUntil) > 0) {
            return true;
        }

        $cachedAvailability = $cachedEvaluation['actual_availability_by_group'] ?? [];

        foreach ($actualAvailabilityByGroup as $group => $availableUntil) {
            $cachedGroupUntil = $cachedAvailability[$group] ?? null;

            if ($availableUntil && (!$cachedGroupUntil || strcmp($availableUntil, $cachedGroupUntil) > 0)) {
                return true;
            }
        }

        $summary = $cachedEvaluation['summary'] ?? [];

        if (($summary['metrics_pending_maturity'] ?? 0) > 0 || ($summary['metrics_pending_actual_data'] ?? 0) > 0) {
            return true;
        }

        return false;
    }

    private function flattenMetricEvaluations(array $metricEvaluations): array
    {
        $flat = [];

        foreach ($metricEvaluations as $group => $metrics) {
            foreach ($metrics as $metric => $row) {
                $row['group'] = $group;
                $row['metric'] = $metric;
                $flat[] = $row;
            }
        }

        return $flat;
    }

    private function countMetricEvaluationsByQuality(array $flatEvaluations, array $qualities): int
    {
        return count(array_filter($flatEvaluations, function ($row) use ($qualities) {
            return in_array($row['quality'] ?? null, $qualities, true);
        }));
    }

    private function mainMisses(array $metricEvaluations): array
    {
        $misses = [];

        foreach ($metricEvaluations as $group => $metrics) {
            foreach ($metrics as $metric => $row) {
                if (
                    ($row['range_hit'] ?? false) === true ||
                    in_array($row['quality'] ?? null, ['pending_maturity', 'pending_actual_data', 'missing_actual_metric', 'invalid_forecast_metric'], true)
                ) {
                    continue;
                }

                $misses[] = [
                    'group' => $group,
                    'metric' => $metric,
                    'quality' => $row['quality'] ?? 'miss',
                    'actual' => $row['actual'] ?? null,
                    'forecast_low' => $row['forecast_low'] ?? null,
                    'forecast_point' => $row['forecast_point'] ?? null,
                    'forecast_high' => $row['forecast_high'] ?? null,
                ];
            }
        }

        return array_slice($misses, 0, 5);
    }

    private function formatNullableNumber(?float $value)
    {
        return $value === null ? null : $this->formatNumber($value);
    }

    private function forecastQuality(?float $hitRate, int $totalCount): string
    {
        if ($totalCount === 0) {
            return 'no_comparable_metrics';
        }

        if ($hitRate >= 80) {
            return 'good';
        }

        if ($hitRate >= 60) {
            return 'partially_correct';
        }

        return 'poor';
    }

    private function percent($numerator, $denominator): ?float
    {
        $denominator = (float) $denominator;

        if ($denominator == 0.0) {
            return null;
        }

        return round(((float) $numerator / $denominator) * 100, 2);
    }

    private function numericOrNull($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function formatNumber(float $value)
    {
        return round($value, 2);
    }
}