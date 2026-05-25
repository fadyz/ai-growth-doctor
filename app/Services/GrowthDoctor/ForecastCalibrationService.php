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
                    'how_final_agent_should_use_forecast' => 'Belum ada histori evaluasi forecast. Gunakan forecast sebagai sinyal directional, bukan veto utama.',
                    'when_to_trust_more' => 'Setelah ada minimal 3 evaluation mature dengan hit rate >= 70%.',
                    'when_to_trust_less' => 'Jika mature hit rate < 50% pada 3 evaluation terakhir.',
                ],
            ];

            $this->persistCalibration($calibration);

            return $calibration;
        }

        $evaluations = array_map(function ($file) {
            return $this->readJsonFile($file);
        }, $evaluationFiles);

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
            'evaluation_files_used' => array_map('basename', $evaluationFiles),
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

        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        return array_reverse(array_slice($files, 0, $limit));
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
                'evidence' => 'Belum ada metric dengan bias berulang minimal 2 evaluation.',
            ];
        }

        $top = $biased[0];

        return [
            'systematic_bias_detected' => true,
            'bias_type' => $top['bias'] . '_' . $top['metric'],
            'evidence' => $top['metric'] . ' memiliki bias ' . $top['bias'] . ' pada ' . $top['evaluated_count'] . ' evaluation, hit rate ' . $top['hit_rate'] . '%.',
        ];
    }

    private function confidenceAdjustment(float $trustScore, ?float $hitRate): array
    {
        if ($hitRate === null) {
            return [
                'forecast_confidence_multiplier' => 0.75,
                'range_adjustment' => 'widen',
                'guardrail_adjustment' => 'use_directionally_only',
                'reason' => 'Belum ada mature forecast hit rate.',
            ];
        }

        if ($trustScore >= 75) {
            return [
                'forecast_confidence_multiplier' => 1.0,
                'range_adjustment' => 'keep',
                'guardrail_adjustment' => 'forecast_can_strengthen_guardrail',
                'reason' => 'Trust score cukup tinggi berdasarkan histori mature metrics.',
            ];
        }

        if ($trustScore >= 55) {
            return [
                'forecast_confidence_multiplier' => 0.85,
                'range_adjustment' => 'slightly_widen',
                'guardrail_adjustment' => 'supporting_guardrail_only',
                'reason' => 'Trust score medium; forecast berguna tetapi jangan menjadi veto tunggal.',
            ];
        }

        return [
            'forecast_confidence_multiplier' => 0.65,
            'range_adjustment' => 'widen',
            'guardrail_adjustment' => 'do_not_use_as_primary_veto',
            'reason' => 'Trust score rendah; forecast perlu dikalahkan oleh actual mature metrics.',
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
            'when_to_trust_more' => 'Jika minimal 3 evaluation terakhir memiliki mature hit rate >= 70% dan pending maturity rendah.',
            'when_to_trust_less' => 'Jika mature hit rate < 50% atau metric penting berulang kali miss_low/miss_high.',
            'weak_metrics_to_treat_carefully' => $weakMetricNames,
            'group_accuracy_snapshot' => $groupAccuracy,
        ];
    }

    private function decisionInstructionText(string $forecastRole, ?float $hitRate, array $weakMetricNames): string
    {
        $hitRateText = $hitRate === null ? 'belum tersedia' : $hitRate . '%';
        $weakText = empty($weakMetricNames) ? 'belum ada weak metric berulang' : implode(', ', $weakMetricNames);

        if ($forecastRole === 'can_strengthen_guardrail') {
            return 'Forecast boleh memperkuat guardrail keputusan, tetapi tetap tidak boleh menggantikan actual mature metrics. Mature hit rate historis: ' . $hitRateText . '. Weak metrics: ' . $weakText . '.';
        }

        if ($forecastRole === 'supporting_guardrail') {
            return 'Gunakan forecast sebagai supporting guardrail. Jangan jadikan forecast sebagai veto utama jika actual mature metrics bertentangan. Mature hit rate historis: ' . $hitRateText . '. Weak metrics: ' . $weakText . '.';
        }

        return 'Gunakan forecast hanya sebagai directional signal. Final decision harus lebih berat ke actual mature metrics dan specialist agent evidence. Mature hit rate historis: ' . $hitRateText . '. Weak metrics: ' . $weakText . '.';
    }

    private function latestEvaluation(array $evaluations): array
    {
        if (empty($evaluations)) {
            return [];
        }

        $latest = end($evaluations);

        return is_array($latest) ? $latest : [];
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