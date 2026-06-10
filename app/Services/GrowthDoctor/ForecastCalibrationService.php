<?php

namespace App\Services\GrowthDoctor;

class ForecastCalibrationService
{
    private const DEFAULT_TRUST_SCORE = 70.0;
    private const HISTORY_LIMIT = 7;

    public function calibrate(int $limit = self::HISTORY_LIMIT): array
    {
        $evaluationFiles = $this->evaluationFiles($limit);

        if (empty($evaluationFiles)) {
            $calibration = [
                'status' => 'no_evaluation_history',
                'learning_window' => 'last_' . $limit . '_evaluated_forecasts',
                'mature_metrics_only' => true,
                'evaluations_used' => 0,
                'overall_mature_hit_rate' => null,
                'trust_score' => [
                    'previous_score' => self::DEFAULT_TRUST_SCORE,
                    'latest_mature_hit_rate' => null,
                    'updated_score' => self::DEFAULT_TRUST_SCORE,
                    'interpretation' => 'default_trust_until_history_exists',
                ],
                'metric_biases' => [],
                'group_accuracy' => [],
                'decision_instruction' => [
                    'forecast_role' => 'supporting_guardrail_only',
                    'how_final_agent_should_use_forecast' => 'No forecast evaluation history exists yet. Use forecast as a directional signal, not as the primary veto.',
                    'when_to_trust_more' => 'After at least 3 mature evaluations with hit rate >= 70%.',
                    'when_to_trust_less' => 'If mature hit rate is < 50% across the latest 3 evaluations.',
                ],
            ];

            $this->persistCalibration($calibration);

            return $calibration;
        }

        $evaluations = array_values(array_filter(array_map(function ($file) {
            return $this->readJsonFile($file);
        }, $evaluationFiles), function ($evaluation) {
            return is_array($evaluation) && !empty($evaluation);
        }));

        usort($evaluations, function ($a, $b) {
            return strcmp(
                (string) ($a['forecast_for_date'] ?? ''),
                (string) ($b['forecast_for_date'] ?? '')
            );
        });

        $matureRows = $this->collectMatureRows($evaluations);
        $overall = $this->summarizeRows($matureRows);
        $metricBiases = $this->buildMetricBiases($matureRows);
        $groupAccuracy = $this->buildGroupAccuracy($matureRows);
        $latestEvaluation = $this->latestEvaluation($evaluations);
        $latestHitRate = $latestEvaluation['summary']['hit_rate'] ?? $overall['hit_rate'];
        $previousTrust = $this->previousTrustScore();
        $updatedTrust = $this->updateTrustScore($previousTrust, $latestHitRate);

        $calibration = [
            'status' => count($evaluations) < 3 ? 'calibrated_with_limited_history' : 'calibrated',
            'learning_window' => 'last_' . $limit . '_evaluated_forecasts',
            'mature_metrics_only' => true,
            'evaluations_used' => count($evaluations),
            'evaluation_files_used' => $this->evaluationFilesUsedSummary($evaluationFiles, $evaluations),
            'overall_mature_hit_rate' => $overall['hit_rate'],
            'mature_metrics_total' => $overall['total'],
            'mature_metrics_hit' => $overall['hit'],
            'pending_maturity_total' => $this->countPendingMaturityRows($evaluations),
            'trust_score' => [
                'previous_score' => $this->formatNumber($previousTrust),
                'latest_mature_hit_rate' => $latestHitRate !== null ? $this->formatNumber((float) $latestHitRate) : null,
                'updated_score' => $this->formatNumber($updatedTrust),
                'interpretation' => $this->trustInterpretation($updatedTrust),
                'formula' => 'updated = previous_score * 0.7 + latest_mature_hit_rate * 0.3; defaults to previous_score when latest hit rate is unavailable.',
            ],
            'metric_biases' => $metricBiases,
            'group_accuracy' => $groupAccuracy,
            'bias_detection' => $this->detectSystematicBias($metricBiases),
            'confidence_adjustment' => $this->confidenceAdjustment($updatedTrust, $overall['hit_rate']),
            'decision_instruction' => $this->decisionInstruction($updatedTrust, $overall['hit_rate'], $metricBiases, $groupAccuracy),
            'latest_evaluation_summary' => [
                'forecast_for_date' => $latestEvaluation['forecast_for_date'] ?? null,
                'data_as_of_date' => $latestEvaluation['data_as_of_date'] ?? null,
                'forecast_quality' => $latestEvaluation['summary']['forecast_quality'] ?? null,
                'hit_rate' => $latestEvaluation['summary']['hit_rate'] ?? null,
                'metrics_pending_maturity' => $latestEvaluation['summary']['metrics_pending_maturity'] ?? 0,
            ],
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];

        $this->persistCalibration($calibration);

        return $calibration;
    }

    private function evaluationFiles(int $limit): array
    {
        $dir = storage_path('app/ai-growth-doctor/evaluations');

        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/evaluation_for_*_created_*.json') ?: [];

        $files = array_values(array_filter($files, function ($file) {
            $date = $this->forecastDateFromEvaluationFilename($file);

            return $date !== null;
        }));

        usort($files, function ($a, $b) {
            $dateCompare = strcmp(
                (string) $this->forecastDateFromEvaluationFilename($b),
                (string) $this->forecastDateFromEvaluationFilename($a)
            );

            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return filemtime($b) <=> filemtime($a);
        });

        $latestByForecastDate = [];

        foreach ($files as $file) {
            $forecastDate = $this->forecastDateFromEvaluationFilename($file);

            if ($forecastDate === null) {
                continue;
            }

            if (!isset($latestByForecastDate[$forecastDate])) {
                $latestByForecastDate[$forecastDate] = $file;
            }
        }

        $selected = array_slice(array_values($latestByForecastDate), 0, $limit);

        usort($selected, function ($a, $b) {
            return strcmp(
                (string) $this->forecastDateFromEvaluationFilename($a),
                (string) $this->forecastDateFromEvaluationFilename($b)
            );
        });

        return $selected;
    }

    private function collectMatureRows(array $evaluations): array
    {
        $rows = [];

        foreach ($evaluations as $evaluation) {
            $forecastForDate = $evaluation['forecast_for_date'] ?? null;
            $dataAsOfDate = $evaluation['data_as_of_date'] ?? null;
            $metricEvaluations = $evaluation['metric_evaluations'] ?? [];

            foreach ($metricEvaluations as $groupName => $metrics) {
                if (!is_array($metrics)) {
                    continue;
                }

                foreach ($metrics as $metricName => $metricRow) {
                    if (!is_array($metricRow)) {
                        continue;
                    }

                    if (($metricRow['quality'] ?? null) === 'pending_maturity') {
                        continue;
                    }

                    if (!array_key_exists('range_hit', $metricRow)) {
                        continue;
                    }

                    $actual = $this->numericOrNull($metricRow['actual'] ?? null);
                    $point = $this->numericOrNull($metricRow['forecast_point'] ?? null);
                    $low = $this->numericOrNull($metricRow['forecast_low'] ?? null);
                    $high = $this->numericOrNull($metricRow['forecast_high'] ?? null);

                    if ($actual === null || $point === null || $low === null || $high === null) {
                        continue;
                    }

                    $rows[] = [
                        'forecast_for_date' => $forecastForDate,
                        'data_as_of_date' => $dataAsOfDate,
                        'group' => $groupName,
                        'metric' => $metricName,
                        'actual' => $actual,
                        'forecast_point' => $point,
                        'forecast_low' => $low,
                        'forecast_high' => $high,
                        'range_hit' => ($metricRow['range_hit'] ?? false) === true,
                        'quality' => $metricRow['quality'] ?? null,
                        'direction_vs_point' => $metricRow['direction_vs_point'] ?? null,
                    ];
                }
            }
        }

        return $rows;
    }

    private function summarizeRows(array $rows): array
    {
        $total = count($rows);
        $hit = count(array_filter($rows, function ($row) {
            return ($row['range_hit'] ?? false) === true;
        }));

        return [
            'total' => $total,
            'hit' => $hit,
            'miss' => max(0, $total - $hit),
            'hit_rate' => $total > 0 ? round(($hit / $total) * 100, 2) : null,
        ];
    }

    private function buildMetricBiases(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $key = $row['group'] . '.' . $row['metric'];

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'group' => $row['group'],
                    'metric' => $row['metric'],
                    'evaluated_count' => 0,
                    'hit_count' => 0,
                    'miss_low_count' => 0,
                    'miss_high_count' => 0,
                    'total_error' => 0.0,
                    'total_signed_error' => 0.0,
                ];
            }

            $grouped[$key]['evaluated_count']++;

            if (($row['range_hit'] ?? false) === true) {
                $grouped[$key]['hit_count']++;
            } elseif (($row['quality'] ?? null) === 'miss_low') {
                $grouped[$key]['miss_low_count']++;
            } elseif (($row['quality'] ?? null) === 'miss_high') {
                $grouped[$key]['miss_high_count']++;
            }

            $error = abs($row['actual'] - $row['forecast_point']);
            $signedError = $row['actual'] - $row['forecast_point'];
            $grouped[$key]['total_error'] += $error;
            $grouped[$key]['total_signed_error'] += $signedError;
        }

        $biases = [];

        foreach ($grouped as $key => $item) {
            $count = max(1, $item['evaluated_count']);
            $hitRate = round(($item['hit_count'] / $count) * 100, 2);
            $avgSignedError = $item['total_signed_error'] / $count;
            $bias = $this->biasLabel($item['miss_low_count'], $item['miss_high_count'], $avgSignedError, $count);

            $biases[$key] = [
                'group' => $item['group'],
                'metric' => $item['metric'],
                'evaluated_count' => $item['evaluated_count'],
                'hit_rate' => $hitRate,
                'miss_low_count' => $item['miss_low_count'],
                'miss_high_count' => $item['miss_high_count'],
                'avg_absolute_error' => $this->formatNumber($item['total_error'] / $count),
                'avg_signed_error' => $this->formatNumber($avgSignedError),
                'bias' => $bias,
                'recommendation' => $this->metricRecommendation($bias, $hitRate, $item['evaluated_count']),
            ];
        }

        uasort($biases, function ($a, $b) {
            return ($b['evaluated_count'] <=> $a['evaluated_count']) ?: ($a['hit_rate'] <=> $b['hit_rate']);
        });

        return $biases;
    }

    private function buildGroupAccuracy(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $group = $row['group'];

            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            $grouped[$group][] = $row;
        }

        $result = [];

        foreach ($grouped as $group => $groupRows) {
            $summary = $this->summarizeRows($groupRows);
            $result[$group] = [
                'evaluated_count' => $summary['total'],
                'hit_count' => $summary['hit'],
                'hit_rate' => $summary['hit_rate'],
                'interpretation' => $this->hitRateInterpretation($summary['hit_rate'], $summary['total']),
            ];
        }

        return $result;
    }

    private function detectSystematicBias(array $metricBiases): array
    {
        $biased = array_values(array_filter($metricBiases, function ($metric) {
            return ($metric['evaluated_count'] ?? 0) >= 2
                && in_array($metric['bias'] ?? null, ['underpredicted', 'overpredicted'], true);
        }));

        if (empty($biased)) {
            return [
                'systematic_bias_detected' => false,
                'bias_type' => 'not_enough_repeated_evidence',
                'evidence' => 'No metric has repeated bias across at least 2 evaluations yet.',
            ];
        }

        $top = $biased[0];

        return [
            'systematic_bias_detected' => true,
            'bias_type' => $top['bias'] . '_' . $top['metric'],
            'evidence' => $top['metric'] . ' has ' . $top['bias'] . ' bias across ' . $top['evaluated_count'] . ' evaluations, hit rate ' . $top['hit_rate'] . '%.',
        ];
    }

    private function confidenceAdjustment(float $trustScore, ?float $hitRate): array
    {
        if ($hitRate === null) {
            return [
                'forecast_confidence_multiplier' => 0.75,
                'range_adjustment' => 'widen',
                'guardrail_adjustment' => 'use_directionally_only',
                'reason' => 'No mature forecast hit rate is available yet.',
            ];
        }

        if ($trustScore >= 75) {
            return [
                'forecast_confidence_multiplier' => 1.0,
                'range_adjustment' => 'keep',
                'guardrail_adjustment' => 'forecast_can_strengthen_guardrail',
                'reason' => 'Trust score is high enough based on mature metric history.',
            ];
        }

        if ($trustScore >= 55) {
            return [
                'forecast_confidence_multiplier' => 0.85,
                'range_adjustment' => 'slightly_widen',
                'guardrail_adjustment' => 'supporting_guardrail_only',
                'reason' => 'Trust score is medium; forecast is useful but should not be the sole veto.',
            ];
        }

        return [
            'forecast_confidence_multiplier' => 0.65,
            'range_adjustment' => 'widen',
            'guardrail_adjustment' => 'do_not_use_as_primary_veto',
            'reason' => 'Trust score is low; forecast should be outweighed by actual mature metrics.',
        ];
    }

    private function decisionInstruction(float $trustScore, ?float $hitRate, array $metricBiases, array $groupAccuracy): array
    {
        $forecastRole = $trustScore >= 75
            ? 'can_strengthen_guardrail'
            : ($trustScore >= 55 ? 'supporting_guardrail' : 'directional_signal_only');

        $weakMetrics = array_slice(array_values(array_filter($metricBiases, function ($metric) {
            return ($metric['hit_rate'] ?? 100) < 50;
        })), 0, 3);

        $weakMetricNames = array_map(function ($metric) {
            return $metric['metric'] . ' (' . $metric['bias'] . ')';
        }, $weakMetrics);

        return [
            'forecast_role' => $forecastRole,
            'how_final_agent_should_use_forecast' => $this->decisionInstructionText($forecastRole, $hitRate, $weakMetricNames),
            'when_to_trust_more' => 'If at least the latest 3 evaluations have mature hit rate >= 70% and low pending maturity.',
            'when_to_trust_less' => 'If mature hit rate is < 50% or important metrics repeatedly miss_low/miss_high.',
            'weak_metrics_to_treat_carefully' => $weakMetricNames,
            'group_accuracy_snapshot' => $groupAccuracy,
        ];
    }

    private function decisionInstructionText(string $forecastRole, ?float $hitRate, array $weakMetricNames): string
    {
        $hitRateText = $hitRate === null ? 'not available yet' : $hitRate . '%';
        $weakText = empty($weakMetricNames) ? 'no repeated weak metric yet' : implode(', ', $weakMetricNames);

        if ($forecastRole === 'can_strengthen_guardrail') {
            return 'Forecast may strengthen the decision guardrail, but it still must not replace actual mature metrics. Historical mature hit rate: ' . $hitRateText . '. Weak metrics: ' . $weakText . '.';
        }

        if ($forecastRole === 'supporting_guardrail') {
            return 'Use forecast as a supporting guardrail. Do not make forecast the primary veto if actual mature metrics disagree. Historical mature hit rate: ' . $hitRateText . '. Weak metrics: ' . $weakText . '.';
        }

        return 'Use forecast only as a directional signal. Final decision should weigh actual mature metrics and specialist agent evidence more heavily. Historical mature hit rate: ' . $hitRateText . '. Weak metrics: ' . $weakText . '.';
    }

    private function latestEvaluation(array $evaluations): array
    {
        if (empty($evaluations)) {
            return [];
        }

        $valid = array_values(array_filter($evaluations, function ($evaluation) {
            return is_array($evaluation) && !empty($evaluation['forecast_for_date']);
        }));

        if (empty($valid)) {
            return [];
        }

        usort($valid, function ($a, $b) {
            return strcmp(
                (string) ($b['forecast_for_date'] ?? ''),
                (string) ($a['forecast_for_date'] ?? '')
            );
        });

        return $valid[0];
    }

    private function forecastDateFromEvaluationFilename(string $path): ?string
    {
        $basename = basename($path);

        if (preg_match('/evaluation_for_(\d{4}-\d{2}-\d{2})_created_/', $basename, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function evaluationFilesUsedSummary(array $evaluationFiles, array $evaluations): array
    {
        $byForecastDate = [];

        foreach ($evaluationFiles as $file) {
            $forecastDate = $this->forecastDateFromEvaluationFilename($file);

            if ($forecastDate === null) {
                continue;
            }

            $byForecastDate[$forecastDate] = basename($file);
        }

        $result = [];

        foreach ($evaluations as $evaluation) {
            $forecastDate = $evaluation['forecast_for_date'] ?? null;

            if (!$forecastDate) {
                continue;
            }

            $result[] = [
                'forecast_for_date' => $forecastDate,
                'data_as_of_date' => $evaluation['data_as_of_date'] ?? null,
                'file' => $byForecastDate[$forecastDate] ?? null,
            ];
        }

        return $result;
    }

    private function previousTrustScore(): float
    {
        $path = storage_path('app/ai-growth-doctor/calibrations/latest_forecast_calibration.json');

        if (!file_exists($path)) {
            return self::DEFAULT_TRUST_SCORE;
        }

        $previous = $this->readJsonFile($path);

        return $this->numericOrNull($previous['trust_score']['updated_score'] ?? null) ?? self::DEFAULT_TRUST_SCORE;
    }

    private function updateTrustScore(float $previousTrust, $latestHitRate): float
    {
        $latest = $this->numericOrNull($latestHitRate);

        if ($latest === null) {
            return $previousTrust;
        }

        return max(0.0, min(100.0, ($previousTrust * 0.7) + ($latest * 0.3)));
    }

    private function trustInterpretation(float $trustScore): string
    {
        if ($trustScore >= 80) {
            return 'high_trust';
        }

        if ($trustScore >= 65) {
            return 'medium_high_trust';
        }

        if ($trustScore >= 50) {
            return 'medium_trust';
        }

        return 'low_trust';
    }

    private function hitRateInterpretation(?float $hitRate, int $count): string
    {
        if ($count === 0 || $hitRate === null) {
            return 'not_enough_data';
        }

        if ($hitRate >= 75) {
            return 'reliable_directionally';
        }

        if ($hitRate >= 55) {
            return 'mixed_but_usable';
        }

        return 'weak_or_uncalibrated';
    }

    private function biasLabel(int $missLowCount, int $missHighCount, float $avgSignedError, int $count): string
    {
        if ($count < 2) {
            if ($missHighCount > $missLowCount) {
                return 'underpredicted_once';
            }

            if ($missLowCount > $missHighCount) {
                return 'overpredicted_once';
            }

            return 'not_enough_repeated_evidence';
        }

        if ($missHighCount > $missLowCount) {
            return 'underpredicted';
        }

        if ($missLowCount > $missHighCount) {
            return 'overpredicted';
        }

        if (abs($avgSignedError) < 0.01) {
            return 'well_calibrated';
        }

        return $avgSignedError > 0 ? 'slightly_underpredicted' : 'slightly_overpredicted';
    }

    private function metricRecommendation(string $bias, float $hitRate, int $count): string
    {
        if ($count < 2) {
            return 'collect_more_evaluations_before_changing_weight';
        }

        if ($hitRate >= 75) {
            return 'trust_directionally';
        }

        if (in_array($bias, ['underpredicted', 'slightly_underpredicted'], true)) {
            return 'watch_upper_bound_or_widen_range';
        }

        if (in_array($bias, ['overpredicted', 'slightly_overpredicted'], true)) {
            return 'watch_lower_bound_or_reduce_weight';
        }

        return 'use_directionally_only';
    }

    private function countPendingMaturityRows(array $evaluations): int
    {
        $count = 0;

        foreach ($evaluations as $evaluation) {
            foreach (($evaluation['metric_evaluations'] ?? []) as $metrics) {
                foreach (($metrics ?? []) as $row) {
                    if (($row['quality'] ?? null) === 'pending_maturity') {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    private function persistCalibration(array $calibration): void
    {
        $dir = storage_path('app/ai-growth-doctor/calibrations');

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $date = now()->format('Y-m-d');

        file_put_contents(
            $dir . '/latest_forecast_calibration.json',
            json_encode($calibration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        file_put_contents(
            $dir . '/forecast_calibration_' . $date . '.json',
            json_encode($calibration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function readJsonFile(string $path): array
    {
        $json = file_get_contents($path);
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
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

    private function formatNumber(float $value): float
    {
        return round($value, 2);
    }
}
