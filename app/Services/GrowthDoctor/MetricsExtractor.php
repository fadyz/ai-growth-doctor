<?php

namespace App\Services\GrowthDoctor;

class MetricsExtractor
{
    public function extract(array $checkpoint): array
    {
        $activationDaily = $checkpoint['activation_daily'] ?? [];
        $retentionDaily = $checkpoint['retention_daily'] ?? [];
        $adsDaily = $checkpoint['ads_daily'] ?? [];
        $adsCampaignContext = $checkpoint['ads_campaign_context'] ?? [];

        $activationMetrics = $this->analyzeActivation($activationDaily);
        $retentionMetrics = $this->analyzeRetention($retentionDaily);
        $monetizationMetrics = $this->analyzeMonetization($activationDaily);
        $versionMetrics = $this->analyzeVersions($activationDaily);
        $adsMetrics = $this->analyzeAds($adsDaily, $adsCampaignContext);
        $ruleDecision = $this->makeDecision($activationMetrics, $retentionMetrics, $monetizationMetrics, $adsMetrics);
        $charts = $this->buildChartData($activationDaily, $retentionDaily);
        $tomorrowForecastMetrics = $this->buildTomorrowForecastMetrics($activationDaily, $retentionDaily, $checkpoint['meta'] ?? []);

        return [
            'activation_metrics' => $activationMetrics,
            'retention_metrics' => $retentionMetrics,
            'monetization_metrics' => $monetizationMetrics,
            'version_metrics' => $versionMetrics,
            'ads_metrics' => $adsMetrics,
            'rule_based_decision' => $ruleDecision,
            'charts' => $charts,
            'tomorrow_forecast_metrics' => $tomorrowForecastMetrics,
        ];
    }

    public function analyzeActivation(array $activationDaily): array
    {
        $latest = $this->latestRowsByUniqueDates($activationDaily, 'event_date', 7);

        $sessionUsers = array_sum(array_column($latest, 'session_users'));
        $workspaceUsers = array_sum(array_column($latest, 'workspace_users'));
        $foodAddSuccessUsers = array_sum(array_column($latest, 'food_add_success_users'));
        $paywallViewUsers = array_sum(array_column($latest, 'paywall_view_users'));
        $purchaseSuccessUsers = array_sum(array_column($latest, 'purchase_success_users'));

        $foodFromSession = $this->percent($foodAddSuccessUsers, $sessionUsers);
        $foodFromWorkspace = $this->percent($foodAddSuccessUsers, $workspaceUsers);
        $paywallFromFood = $this->percent($paywallViewUsers, $foodAddSuccessUsers);
        $purchaseFromPaywall = $this->percent($purchaseSuccessUsers, $paywallViewUsers);

        $status = 'stable';
        $mainLeak = 'none';

        if ($foodFromSession !== null && $foodFromSession < 30) {
            $status = 'warning';
            $mainLeak = 'session_to_food_add_success';
        }

        if ($foodFromWorkspace !== null && $foodFromWorkspace < 60) {
            $status = 'warning';
            $mainLeak = 'workspace_to_food_add_success';
        }

        return [
            'agent' => 'Activation Metrics Extractor',
            'status' => $status,
            'main_leak' => $mainLeak,
            'metrics_7d' => [
                'session_users' => $sessionUsers,
                'workspace_users' => $workspaceUsers,
                'food_add_success_users' => $foodAddSuccessUsers,
                'food_add_success_rate_from_session' => $foodFromSession,
                'food_add_success_rate_from_workspace' => $foodFromWorkspace,
                'paywall_view_users' => $paywallViewUsers,
                'purchase_success_users' => $purchaseSuccessUsers,
                'paywall_rate_from_food_add_success' => $paywallFromFood,
                'purchase_success_rate_from_paywall' => $purchaseFromPaywall,
            ],
            'diagnosis' => $status === 'warning'
                ? 'Activation masih perlu perhatian. User sudah masuk funnel, tetapi food_add_success belum cukup kuat.'
                : 'Activation terlihat relatif stabil dalam 7 hari terakhir.',
        ];
    }

    public function analyzeRetention(array $retentionDaily): array
    {
        $latest = $this->latestRowsByUniqueDates($retentionDaily, 'join_date', 7);

        // For lagging retention metrics, filter maturity first and then take the latest
        // mature cohorts. If we take the latest 7 cohorts first, D1/habit can be based
        // on only 2-3 mature rows when the newest cohorts have not had time to mature.
        $matureD0Rows = $this->latestRetentionRowsByMaturity($retentionDaily, 0, 7);
        $matureD1Rows = $this->latestRetentionRowsByMaturity($retentionDaily, 1, 7);
        $matureHabitRows = $this->latestRetentionRowsByMaturity($retentionDaily, 6, 7);

        $avgD0 = $this->avg(array_column($matureD0Rows, 'd0_logged_rate'));
        $avgD1 = $this->avg(array_column($matureD1Rows, 'd1_logged_rate'));
        $avgHabit7d = $this->avg(array_column($matureHabitRows, 'habit_7d_rate'));
        $avgLogDays7d = $this->avg(array_column($matureHabitRows, 'avg_log_days_7d'));

        $status = 'stable';
        $pendingMaturity = [];

        if (count($matureD1Rows) === 0) {
            $pendingMaturity[] = 'd1_logged_rate';
        }

        if (count($matureHabitRows) === 0) {
            $pendingMaturity[] = 'habit_7d_rate';
            $pendingMaturity[] = 'avg_log_days_7d';
        }

        if ($avgD0 !== null && $avgD0 < 30) {
            $status = 'warning';
        }

        if ($avgD1 !== null && $avgD1 < 14) {
            $status = 'warning';
        }

        if ($status === 'stable' && !empty($pendingMaturity)) {
            $status = 'partial';
        }

        return [
            'agent' => 'Retention Metrics Extractor',
            'status' => $status,
            'metrics_7d_avg' => [
                'd0_logged_rate' => $avgD0,
                'd1_logged_rate' => $avgD1,
                'habit_7d_rate' => $avgHabit7d,
                'avg_log_days_7d' => $avgLogDays7d,
            ],
            'maturity' => [
                'pending_metrics' => array_values(array_unique($pendingMaturity)),
                'mature_rows_used' => [
                    'd0_logged_rate' => count($matureD0Rows),
                    'd1_logged_rate' => count($matureD1Rows),
                    'habit_7d_rate' => count($matureHabitRows),
                    'avg_log_days_7d' => count($matureHabitRows),
                ],
                'latest_join_date_seen' => $this->latestRetentionJoinDate($latest),
                'sampling_rule' => 'filter_mature_first_then_take_latest_7_mature_cohorts_per_metric',
                'mature_windows' => [
                    'd0_logged_rate' => $this->retentionDateRange($matureD0Rows),
                    'd1_logged_rate' => $this->retentionDateRange($matureD1Rows),
                    'habit_7d_rate' => $this->retentionDateRange($matureHabitRows),
                    'avg_log_days_7d' => $this->retentionDateRange($matureHabitRows),
                ],
                'today_date' => now()->format('Y-m-d'),
            ],
            'diagnosis' => $status === 'warning'
                ? 'D0/D1 mature rows belum cukup kuat. First-day action belum sepenuhnya berubah menjadi habit.'
                : (!empty($pendingMaturity)
                    ? 'Sebagian metric retention masih menunggu cohort maturity, jadi jangan dibaca sebagai penurunan final.'
                    : 'Retention awal terlihat cukup stabil.'),
        ];
    }

    private function latestRetentionRowsByMaturity(array $rows, int $lagDays, int $limit = 7): array
    {
        $matureRows = $this->filterRetentionRowsByMaturity($rows, $lagDays);

        usort($matureRows, function ($a, $b) {
            return strcmp(
                $this->retentionRowDate($b) ?? '',
                $this->retentionRowDate($a) ?? ''
            );
        });

        return array_slice($matureRows, 0, $limit);
    }
    private function filterRetentionRowsByMaturity(array $rows, int $lagDays): array
    {
        $today = now()->format('Y-m-d');

        return array_values(array_filter($rows, function ($row) use ($lagDays, $today) {
            $joinDate = $this->retentionRowDate($row);

            if (!$joinDate) {
                return false;
            }

            $requiredDate = date('Y-m-d', strtotime($joinDate . ' +' . $lagDays . ' day'));

            return strcmp($today, $requiredDate) >= 0;
        }));
    }

    private function retentionRowDate(array $row): ?string
    {
        return $row['join_date']
            ?? $row['date']
            ?? $row['event_date']
            ?? $row['cohort_date']
            ?? null;
    }

    private function retentionDateRange(array $rows): array
    {
        $dates = array_values(array_filter(array_map(function ($row) {
            return $this->retentionRowDate($row);
        }, $rows)));

        sort($dates);

        return [
            'start' => $dates[0] ?? null,
            'end' => !empty($dates) ? $dates[count($dates) - 1] : null,
            'rows' => count($dates),
        ];
    }

    private function latestRetentionJoinDate(array $rows): ?string
    {
        $dates = array_values(array_filter(array_map(function ($row) {
            return $this->retentionRowDate($row);
        }, $rows)));

        if (empty($dates)) {
            return null;
        }

        sort($dates);

        return end($dates) ?: null;
    }

    public function analyzeMonetization(array $activationDaily): array
    {
        $latest = $this->latestRowsByUniqueDates($activationDaily, 'event_date', 7);

        $paywallViewUsers = array_sum(array_column($latest, 'paywall_view_users'));
        $purchaseStartUsers = array_sum(array_column($latest, 'purchase_start_users'));
        $purchaseSuccessUsers = array_sum(array_column($latest, 'purchase_success_users'));
        $foodAddSuccessUsers = array_sum(array_column($latest, 'food_add_success_users'));

        $purchaseFromPaywall = $this->percent($purchaseSuccessUsers, $paywallViewUsers);
        $paywallFromFood = $this->percent($paywallViewUsers, $foodAddSuccessUsers);

        $status = 'noisy';

        if ($paywallViewUsers >= 30 && $purchaseSuccessUsers >= 3) {
            $status = 'active_signal';
        }

        if ($paywallFromFood !== null && $paywallFromFood > 80 && $purchaseFromPaywall !== null && $purchaseFromPaywall < 3) {
            $status = 'risk';
        }

        return [
            'agent' => 'Monetization Metrics Extractor',
            'status' => $status,
            'metrics_7d' => [
                'paywall_view_users' => $paywallViewUsers,
                'purchase_start_users' => $purchaseStartUsers,
                'purchase_success_users' => $purchaseSuccessUsers,
                'paywall_rate_from_food_add_success' => $paywallFromFood,
                'purchase_success_rate_from_paywall' => $purchaseFromPaywall,
            ],
            'diagnosis' => $status === 'risk'
                ? 'Paywall exposure tinggi, tetapi purchase belum mengikuti. Monetization pressure berisiko mengganggu activation.'
                : 'Monetization signal masih perlu dibaca bersama activation karena sample purchase biasanya kecil.',
        ];
    }

    public function analyzeVersions(array $activationDaily): array
    {
        $versions = [];

        foreach ($activationDaily as $row) {
            $version = $row['app_version'] ?? null;

            if (!$version) {
                continue;
            }

            if (!isset($versions[$version])) {
                $versions[$version] = [
                    'app_version' => $version,
                    'session_users' => 0,
                    'workspace_users' => 0,
                    'food_add_success_users' => 0,
                    'paywall_view_users' => 0,
                    'purchase_success_users' => 0,
                ];
            }

            $versions[$version]['session_users'] += (int) ($row['session_users'] ?? 0);
            $versions[$version]['workspace_users'] += (int) ($row['workspace_users'] ?? 0);
            $versions[$version]['food_add_success_users'] += (int) ($row['food_add_success_users'] ?? 0);
            $versions[$version]['paywall_view_users'] += (int) ($row['paywall_view_users'] ?? 0);
            $versions[$version]['purchase_success_users'] += (int) ($row['purchase_success_users'] ?? 0);
        }

        foreach ($versions as $version => $row) {
            $versions[$version]['food_add_success_rate_from_session'] = $this->percent($row['food_add_success_users'], $row['session_users']);
            $versions[$version]['food_add_success_rate_from_workspace'] = $this->percent($row['food_add_success_users'], $row['workspace_users']);
            $versions[$version]['purchase_success_rate_from_paywall'] = $this->percent($row['purchase_success_users'], $row['paywall_view_users']);
        }

        usort($versions, function ($a, $b) {
            return ($b['session_users'] ?? 0) <=> ($a['session_users'] ?? 0);
        });

        return [
            'agent' => 'Version Metrics Extractor',
            'versions' => array_values($versions),
            'top_versions' => array_slice(array_values($versions), 0, 8),
        ];
    }

    public function analyzeAds(array $adsDaily, array $adsCampaignContext = []): array
    {
        $contextByCampaign = $this->adsContextByCampaign($adsCampaignContext);

        if (empty($adsDaily)) {
            return [
                'agent' => 'Ads Metrics Extractor',
                'status' => 'no_ads_data',
                'ads_verdict' => [
                    'decision' => 'no_ads_data',
                    'confidence' => 0,
                    'reason' => 'Belum ada data Google Ads di checkpoint.',
                    'final_decision_impact' => 'Ads evidence belum memengaruhi final decision.',
                ],
                'overall' => [],
                'campaigns' => [],
                'campaign_context' => $adsCampaignContext,
            ];
        }

        $normalizedRows = array_values(array_filter(array_map(function ($row) {
            return $this->normalizeAdsMetricRow($row);
        }, $adsDaily)));

        $overall = $this->summarizeAdsRows($normalizedRows);
        $campaigns = $this->summarizeAdsCampaigns($normalizedRows, $contextByCampaign);
        $verdict = $this->adsVerdict($overall, $campaigns);

        return [
            'agent' => 'Ads Metrics Extractor',
            'status' => 'ok',
            'rows_count' => count($normalizedRows),
            'date_range' => $this->adsDateRange($normalizedRows),
            'overall' => $overall,
            'campaigns' => $campaigns,
            'campaign_context' => $adsCampaignContext,
            'ads_verdict' => $verdict,
            'diagnosis' => $verdict['reason'] ?? 'Ads data tersedia dan perlu dibaca bersama activation/retention.',
        ];
    }

    public function makeDecision(array $activation, array $retention, array $monetization, array $ads = []): array
    {
        $actions = [];

        if (($activation['status'] ?? null) === 'warning') {
            $actions[] = 'Prioritaskan perbaikan first-log dan food_add_success flow.';
        }

        if (($retention['status'] ?? null) === 'warning') {
            $actions[] = 'Jangan scale ads agresif sebelum D0/D1 membaik.';
        }

        if (($monetization['status'] ?? null) === 'risk') {
            $actions[] = 'Kurangi atau tunda promo/paywall yang muncul terlalu awal.';
        }

        $adsDecision = $ads['ads_verdict']['decision'] ?? null;

        if ($adsDecision === 'shift_attention_to_reset_campaign') {
            $actions[] = 'Jangan membaca campaign Volume Stabil lama sebagai campaign utama; evaluasi Volume Install Reset sebagai replacement/recovery candidate.';
        }

        if ($adsDecision === 'hold_or_reduce_ads') {
            $actions[] = 'Tahan atau kurangi budget ads yang memburuk sampai CPI/conversion stabil.';
        }

        if ($adsDecision === 'allow_cautious_ads_test') {
            $actions[] = 'Ads boleh diuji kecil terkontrol hanya jika activation dan retention downstream tidak memburuk.';
        }

        if (empty($actions)) {
            $actions[] = 'Lanjut monitor. Belum ada sinyal risiko besar.';
        }

        $verdict = 'CONTINUE_MONITORING';

        if (
            ($activation['status'] ?? null) === 'warning' ||
            ($retention['status'] ?? null) === 'warning' ||
            ($monetization['status'] ?? null) === 'risk' ||
            (($ads['ads_verdict']['decision'] ?? null) === 'hold_or_reduce_ads')
        ) {
            $verdict = 'HOLD_AND_OPTIMIZE';
        }

        return [
            'agent' => 'Rule-Based Decision',
            'verdict' => $verdict,
            'business_status' => $verdict === 'HOLD_AND_OPTIMIZE'
                ? 'partial_recovery_or_risk'
                : 'stable',
            'summary' => $verdict === 'HOLD_AND_OPTIMIZE'
                ? 'Ada sinyal yang belum cukup sehat. Fokus utama adalah menjaga activation dan retention sebelum scaling.'
                : 'Metrik utama terlihat cukup stabil. Lanjutkan monitoring harian.',
            'recommended_actions' => $actions,
        ];
    }

    public function buildChartData(array $activationDaily, array $retentionDaily): array
    {
        $activationByDate = [];

        foreach ($activationDaily as $row) {
            $date = $row['event_date'] ?? $row['date'] ?? null;

            if (!$date) {
                continue;
            }

            if (!isset($activationByDate[$date])) {
                $activationByDate[$date] = [
                    'session_users' => 0,
                    'workspace_users' => 0,
                    'food_add_success_users' => 0,
                    'paywall_view_users' => 0,
                    'purchase_success_users' => 0,
                ];
            }

            $activationByDate[$date]['session_users'] += (int) ($row['session_users'] ?? 0);
            $activationByDate[$date]['workspace_users'] += (int) ($row['workspace_users'] ?? 0);
            $activationByDate[$date]['food_add_success_users'] += (int) ($row['food_add_success_users'] ?? 0);
            $activationByDate[$date]['paywall_view_users'] += (int) ($row['paywall_view_users'] ?? 0);
            $activationByDate[$date]['purchase_success_users'] += (int) ($row['purchase_success_users'] ?? 0);
        }

        ksort($activationByDate);

        $activationLabels = array_keys($activationByDate);
        $sessionUsers = [];
        $workspaceUsers = [];
        $foodAddSuccessUsers = [];
        $foodAddSuccessRateFromSession = [];
        $foodAddSuccessRateFromWorkspace = [];
        $paywallViewUsers = [];
        $purchaseSuccessUsers = [];

        foreach ($activationByDate as $row) {
            $sessionUsers[] = $row['session_users'];
            $workspaceUsers[] = $row['workspace_users'];
            $foodAddSuccessUsers[] = $row['food_add_success_users'];
            $foodAddSuccessRateFromSession[] = $this->percent($row['food_add_success_users'], $row['session_users']);
            $foodAddSuccessRateFromWorkspace[] = $this->percent($row['food_add_success_users'], $row['workspace_users']);
            $paywallViewUsers[] = $row['paywall_view_users'];
            $purchaseSuccessUsers[] = $row['purchase_success_users'];
        }

        $retentionRows = [];

        foreach ($retentionDaily as $row) {
            $date = $row['join_date']
                ?? $row['date']
                ?? $row['event_date']
                ?? $row['cohort_date']
                ?? null;

            if (!$date) {
                continue;
            }

            $retentionRows[] = [
                'join_date' => $date,
                'd0_logged_rate' => $this->numericOrNull(
                    $row['d0_logged_rate']
                    ?? $row['rate_d0_logged']
                    ?? $row['d0_rate']
                    ?? null
                ),
                'd1_logged_rate' => $this->numericOrNull(
                    $row['d1_logged_rate']
                    ?? $row['rate_d1_logged']
                    ?? $row['d1_rate']
                    ?? null
                ),
                'habit_7d_rate' => $this->numericOrNull(
                    $row['habit_7d_rate']
                    ?? $row['rate_habit_7d']
                    ?? $row['habit_rate']
                    ?? null
                ),
            ];
        }

        usort($retentionRows, function ($a, $b) {
            return strcmp($a['join_date'] ?? '', $b['join_date'] ?? '');
        });

        return [
            'activation_trend' => [
                'labels' => $activationLabels,
                'session_users' => $sessionUsers,
                'workspace_users' => $workspaceUsers,
                'food_add_success_users' => $foodAddSuccessUsers,
                'food_add_success_rate_from_session' => $foodAddSuccessRateFromSession,
                'food_add_success_rate_from_workspace' => $foodAddSuccessRateFromWorkspace,
                'paywall_view_users' => $paywallViewUsers,
                'purchase_success_users' => $purchaseSuccessUsers,
            ],
            'retention_trend' => [
                'labels' => array_map(function ($row) {
                    return $row['join_date'];
                }, $retentionRows),
                'd0_logged_rate' => array_map(function ($row) {
                    return $row['d0_logged_rate'];
                }, $retentionRows),
                'd1_logged_rate' => array_map(function ($row) {
                    return $row['d1_logged_rate'];
                }, $retentionRows),
                'habit_7d_rate' => array_map(function ($row) {
                    return $row['habit_7d_rate'];
                }, $retentionRows),
            ],
        ];
    }

    public function buildTomorrowForecastMetrics(array $activationDaily, array $retentionDaily, array $checkpointMeta = []): array
    {
        $activationByDateRaw = $this->aggregateActivationByDate($activationDaily);
        $retentionByDateRaw = $this->aggregateRetentionByDate($retentionDaily);

        $checkpointWindowEnd = $checkpointMeta['window_end'] ?? null;
        $forecastCompleteness = $this->resolveForecastCompletenessDate($activationByDateRaw, $retentionByDateRaw, $checkpointMeta);
        $dataAsOfDate = $forecastCompleteness['data_as_of_date']
            ?: ($this->actualDataCompleteUntilFromMeta($checkpointMeta)
                ?: ($this->latestDateFromRows($activationByDateRaw) ?: $this->latestDateFromRows($retentionByDateRaw)));

        $activationByDate = $dataAsOfDate
            ? $this->filterRowsUntilDate($activationByDateRaw, $dataAsOfDate)
            : $activationByDateRaw;
        $retentionByDate = $dataAsOfDate
            ? $this->filterRowsUntilDate($retentionByDateRaw, $dataAsOfDate)
            : $retentionByDateRaw;

        $forecastForDate = $dataAsOfDate
            ? date('Y-m-d', strtotime($dataAsOfDate . ' +1 day'))
            : $this->tomorrowDateFromHistoricalData($activationByDate, $retentionByDate);
        $evaluationReadyAfter = $forecastForDate
            ? date('Y-m-d', strtotime($forecastForDate . ' +1 day'))
            : null;

        $activationForecasts = [
            'session_users' => $this->forecastSeries($this->seriesFromRows($activationByDate, 'session_users'), 'count'),
            'workspace_users' => $this->forecastSeries($this->seriesFromRows($activationByDate, 'workspace_users'), 'count'),
            'food_add_success_users' => $this->forecastSeries($this->seriesFromRows($activationByDate, 'food_add_success_users'), 'count'),
            'workspace_rate_from_session' => $this->forecastSeries($this->seriesFromRows($activationByDate, 'workspace_rate_from_session'), 'rate'),
            'food_add_success_rate_from_session' => $this->forecastSeries($this->seriesFromRows($activationByDate, 'food_add_success_rate_from_session'), 'rate'),
            'food_add_success_rate_from_workspace' => $this->forecastSeries($this->seriesFromRows($activationByDate, 'food_add_success_rate_from_workspace'), 'rate'),
        ];

        $monetizationForecasts = [
            'paywall_view_users' => $this->forecastSeries($this->seriesFromRows($activationByDate, 'paywall_view_users'), 'count'),
            'purchase_success_users' => $this->forecastSeries($this->seriesFromRows($activationByDate, 'purchase_success_users'), 'count'),
            'purchase_success_rate_from_paywall' => $this->forecastSeries($this->seriesFromRows($activationByDate, 'purchase_success_rate_from_paywall'), 'rate'),
        ];

        $retentionForecasts = [
            'new_users' => $this->forecastSeries($this->seriesFromRows($retentionByDate, 'new_users'), 'count'),
            'd0_logged_rate' => $this->forecastSeries($this->seriesFromRows($retentionByDate, 'd0_logged_rate'), 'rate'),
            'd1_logged_rate' => $this->forecastSeries($this->seriesFromRows($retentionByDate, 'd1_logged_rate'), 'rate'),
            'habit_7d_rate' => $this->forecastSeries($this->seriesFromRows($retentionByDate, 'habit_7d_rate'), 'rate'),
            'avg_log_days_7d' => $this->forecastSeries($this->seriesFromRows($retentionByDate, 'avg_log_days_7d'), 'decimal'),
        ];

        $activationLatestDate = $this->latestDateFromRows($activationByDate);
        $retentionLatestDate = $this->latestDateFromRows($retentionByDate);
        $rawActivationLatestDate = $this->latestDateFromRows($activationByDateRaw);
        $rawRetentionLatestDate = $this->latestDateFromRows($retentionByDateRaw);
        $metricMaturityPolicy = $this->buildForecastMetricMaturityPolicy($forecastForDate);

        return [
            'agent' => 'Tomorrow Forecast Metrics Extractor',
            'run_date' => now()->format('Y-m-d'),
            'run_timestamp' => now()->format('Y-m-d H:i:s'),
            'data_as_of_date' => $dataAsOfDate,
            'checkpoint_window_end' => $checkpointWindowEnd,
            'actual_data_available_until' => $dataAsOfDate,
            'forecast_for_date' => $forecastForDate,
            'evaluation_ready_after' => $evaluationReadyAfter,
            'evaluation_status' => 'pending_actual_data',
            'evaluation_rule' => 'Evaluate only when actual_data_available_until >= forecast_for_date in a later checkpoint.',
            'forecast_lanes' => [
                'daily' => [
                    'activation.session_users',
                    'activation.workspace_users',
                    'activation.food_add_success_users',
                    'activation.workspace_rate_from_session',
                    'activation.food_add_success_rate_from_session',
                    'activation.food_add_success_rate_from_workspace',
                    'monetization.paywall_view_users',
                    'monetization.purchase_success_users',
                    'monetization.purchase_success_rate_from_paywall',
                    'retention.new_users',
                    'retention.d0_logged_rate',
                    'retention.d1_logged_rate',
                ],
                'retention_delayed' => [
                    'retention.habit_7d_rate',
                    'retention.avg_log_days_7d',
                ],
            ],
            'metric_maturity_policy' => $metricMaturityPolicy,
            'method' => 'weighted_historical_forecast_v1',
            'formula' => 'point = 50% avg_last_3d + 30% avg_last_7d + 20% yesterday; range = point ± clamped std_last_7d with minimum and maximum band.',
            'data_windows' => [
                'activation_days_available' => count($activationByDate),
                'retention_days_available' => count($retentionByDate),
                'activation_latest_date' => $activationLatestDate,
                'retention_latest_date' => $retentionLatestDate,
                'raw_activation_latest_date' => $rawActivationLatestDate,
                'raw_retention_latest_date' => $rawRetentionLatestDate,
                'checkpoint_window_end' => $checkpointWindowEnd,
                'partial_window_excluded' => $checkpointWindowEnd !== null && $dataAsOfDate !== null && strcmp($dataAsOfDate, $checkpointWindowEnd) < 0,
                'completeness_guard' => $forecastCompleteness,
            ],
            'predicted_metrics' => [
                'activation' => $activationForecasts,
                'retention' => $retentionForecasts,
                'monetization' => $monetizationForecasts,
            ],
            'risk_flags' => $this->buildTomorrowForecastRiskFlags($activationForecasts, $retentionForecasts, $monetizationForecasts),
            'guardrails' => $this->buildTomorrowForecastRiskFlags($activationForecasts, $retentionForecasts, $monetizationForecasts), // deprecated alias; use risk_flags instead
        ];
    }

    private function resolveForecastCompletenessDate(array $activationByDateRaw, array $retentionByDateRaw, array $checkpointMeta): array
    {
        $metaCompleteUntil = $this->actualDataCompleteUntilFromMeta($checkpointMeta);
        $latestActivationDate = $this->latestDateFromRows($activationByDateRaw);
        $latestRetentionDate = $this->latestDateFromRows($retentionByDateRaw);
        $candidateDate = $metaCompleteUntil ?: ($latestActivationDate ?: $latestRetentionDate);

        $dates = array_values(array_unique(array_filter(array_merge(
            array_map(function ($row) {
                return $row['date'] ?? null;
            }, $activationByDateRaw),
            array_map(function ($row) {
                return $row['date'] ?? null;
            }, $retentionByDateRaw)
        ))));
        sort($dates);

        if (!$candidateDate || empty($dates)) {
            return [
                'status' => 'no_date_available',
                'data_as_of_date' => $candidateDate,
                'candidate_date' => $candidateDate,
                'excluded_dates' => [],
                'reason_codes' => ['no_forecast_date_available'],
            ];
        }

        $eligibleDates = array_values(array_filter($dates, function ($date) use ($candidateDate) {
            return strcmp($date, $candidateDate) <= 0;
        }));

        if (empty($eligibleDates)) {
            return [
                'status' => 'no_eligible_date',
                'data_as_of_date' => $candidateDate,
                'candidate_date' => $candidateDate,
                'excluded_dates' => [],
                'reason_codes' => ['no_date_before_candidate'],
            ];
        }

        $activationByDate = [];
        foreach ($activationByDateRaw as $row) {
            if (!empty($row['date'])) {
                $activationByDate[$row['date']] = $row;
            }
        }

        $excludedDates = [];
        $reasonCodes = [];
        $selectedDate = end($eligibleDates);

        for ($i = count($eligibleDates) - 1; $i >= 0; $i--) {
            $date = $eligibleDates[$i];
            $activationRow = $activationByDate[$date] ?? [];
            $previousRows = [];

            for ($j = max(0, $i - 7); $j < $i; $j++) {
                $previousDate = $eligibleDates[$j];
                if (isset($activationByDate[$previousDate])) {
                    $previousRows[] = $activationByDate[$previousDate];
                }
            }

            $quality = $this->activationDateQualityForForecast($activationRow, $previousRows);

            if (($quality['status'] ?? null) === 'complete_enough') {
                $selectedDate = $date;
                break;
            }

            $excludedDates[] = [
                'date' => $date,
                'reason_codes' => $quality['reason_codes'] ?? ['unknown_quality_issue'],
                'session_users' => $activationRow['session_users'] ?? null,
                'workspace_users' => $activationRow['workspace_users'] ?? null,
                'food_add_success_users' => $activationRow['food_add_success_users'] ?? null,
            ];
            $reasonCodes = array_values(array_unique(array_merge($reasonCodes, $quality['reason_codes'] ?? [])));

            if ($i === 0) {
                $selectedDate = $date;
            }
        }

        return [
            'status' => empty($excludedDates) ? 'complete' : 'latest_dates_excluded',
            'data_as_of_date' => $selectedDate,
            'candidate_date' => $candidateDate,
            'meta_complete_until' => $metaCompleteUntil,
            'latest_activation_date' => $latestActivationDate,
            'latest_retention_date' => $latestRetentionDate,
            'excluded_dates' => $excludedDates,
            'reason_codes' => $reasonCodes,
        ];
    }

    private function activationDateQualityForForecast(array $row, array $previousRows): array
    {
        $sessionUsers = (int) ($row['session_users'] ?? 0);
        $workspaceUsers = (int) ($row['workspace_users'] ?? 0);
        $foodAddSuccessUsers = (int) ($row['food_add_success_users'] ?? 0);
        $foodRateFromSession = $this->numericOrNull($row['food_add_success_rate_from_session'] ?? null);
        $workspaceRateFromSession = $this->numericOrNull($row['workspace_rate_from_session'] ?? null);

        $reasonCodes = [];

        if ($sessionUsers === 0 && ($workspaceUsers > 0 || $foodAddSuccessUsers > 0)) {
            $reasonCodes[] = 'session_zero_but_downstream_events_present';
        }

        if ($sessionUsers > 0 && $workspaceUsers > ($sessionUsers * 1.5)) {
            $reasonCodes[] = 'workspace_users_exceed_session_users_materially';
        }

        if ($sessionUsers > 0 && $foodAddSuccessUsers > ($sessionUsers * 1.5)) {
            $reasonCodes[] = 'food_success_users_exceed_session_users_materially';
        }

        if ($foodRateFromSession !== null && $foodRateFromSession > 120) {
            $reasonCodes[] = 'food_success_rate_from_session_above_120_pct';
        }

        if ($workspaceRateFromSession !== null && $workspaceRateFromSession > 120) {
            $reasonCodes[] = 'workspace_rate_from_session_above_120_pct';
        }

        $previousSessions = array_values(array_filter(array_map(function ($previousRow) {
            return (int) ($previousRow['session_users'] ?? 0);
        }, $previousRows), function ($value) {
            return $value > 0;
        }));

        $previousMedianSessions = $this->median($previousSessions);

        if ($previousMedianSessions !== null && $previousMedianSessions >= 20 && $sessionUsers < max(5, $previousMedianSessions * 0.35)) {
            $reasonCodes[] = 'session_users_drop_more_than_65_pct_vs_recent_median';
        }

        return [
            'status' => empty($reasonCodes) ? 'complete_enough' : 'suspect_or_partial',
            'reason_codes' => $reasonCodes,
            'previous_median_session_users' => $previousMedianSessions,
        ];
    }

    private function actualDataCompleteUntilFromMeta(array $checkpointMeta): ?string
    {
        $windowEnd = $checkpointMeta['window_end'] ?? null;

        if (!$windowEnd) {
            return null;
        }

        $generatedAt = $checkpointMeta['generated_at'] ?? null;
        $generatedDate = $generatedAt ? substr((string) $generatedAt, 0, 10) : null;

        if ($generatedDate && strcmp($generatedDate, $windowEnd) <= 0) {
            return date('Y-m-d', strtotime($windowEnd . ' -1 day'));
        }

        return $windowEnd;
    }

    private function filterRowsUntilDate(array $rows, string $maxDate): array
    {
        return array_values(array_filter($rows, function ($row) use ($maxDate) {
            $date = $row['date'] ?? null;

            return $date && strcmp($date, $maxDate) <= 0;
        }));
    }

    private function aggregateActivationByDate(array $activationDaily): array
    {
        $byDate = [];

        foreach ($activationDaily as $row) {
            $date = $row['event_date'] ?? $row['date'] ?? null;

            if (!$date) {
                continue;
            }

            if (!isset($byDate[$date])) {
                $byDate[$date] = [
                    'date' => $date,
                    'session_users' => 0,
                    'workspace_users' => 0,
                    'food_add_success_users' => 0,
                    'paywall_view_users' => 0,
                    'purchase_start_users' => 0,
                    'purchase_success_users' => 0,
                ];
            }

            $byDate[$date]['session_users'] += (int) ($row['session_users'] ?? 0);
            $byDate[$date]['workspace_users'] += (int) ($row['workspace_users'] ?? 0);
            $byDate[$date]['food_add_success_users'] += (int) ($row['food_add_success_users'] ?? 0);
            $byDate[$date]['paywall_view_users'] += (int) ($row['paywall_view_users'] ?? 0);
            $byDate[$date]['purchase_start_users'] += (int) ($row['purchase_start_users'] ?? 0);
            $byDate[$date]['purchase_success_users'] += (int) ($row['purchase_success_users'] ?? 0);
        }

        ksort($byDate);

        foreach ($byDate as $date => $row) {
            $byDate[$date]['workspace_rate_from_session'] = $this->percent($row['workspace_users'], $row['session_users']);
            $byDate[$date]['food_add_success_rate_from_session'] = $this->percent($row['food_add_success_users'], $row['session_users']);
            $byDate[$date]['food_add_success_rate_from_workspace'] = $this->percent($row['food_add_success_users'], $row['workspace_users']);
            $byDate[$date]['purchase_success_rate_from_paywall'] = $this->percent($row['purchase_success_users'], $row['paywall_view_users']);
        }

        return array_values($byDate);
    }

    private function aggregateRetentionByDate(array $retentionDaily): array
    {
        $rows = [];

        foreach ($retentionDaily as $row) {
            $date = $row['join_date']
                ?? $row['date']
                ?? $row['event_date']
                ?? $row['cohort_date']
                ?? null;

            if (!$date) {
                continue;
            }

            $rows[$date] = [
                'date' => $date,
                'new_users' => $this->numericOrNull($row['new_users'] ?? null),
                'd0_logged_rate' => $this->numericOrNull($row['d0_logged_rate'] ?? $row['rate_d0_logged'] ?? $row['d0_rate'] ?? null),
                'd1_logged_rate' => $this->numericOrNull($row['d1_logged_rate'] ?? $row['rate_d1_logged'] ?? $row['d1_rate'] ?? null),
                'habit_7d_rate' => $this->numericOrNull($row['habit_7d_rate'] ?? $row['rate_habit_7d'] ?? $row['habit_rate'] ?? null),
                'avg_log_days_7d' => $this->numericOrNull($row['avg_log_days_7d'] ?? null),
            ];
        }

        ksort($rows);

        return array_values($rows);
    }

    private function seriesFromRows(array $rows, string $key): array
    {
        $series = [];

        foreach ($rows as $row) {
            $value = $this->numericOrNull($row[$key] ?? null);

            if ($value === null) {
                continue;
            }

            $series[] = $value;
        }

        return $series;
    }

    private function forecastSeries(array $series, string $type): array
    {
        $series = array_values(array_filter($series, function ($value) {
            return $value !== null && is_numeric($value);
        }));

        $n = count($series);

        if ($n === 0) {
            return [
                'point' => null,
                'low' => null,
                'high' => null,
                'confidence' => 'none',
                'basis' => 'no_data',
            ];
        }

        $last7 = array_slice($series, -7);
        $last3 = array_slice($series, -3);
        $yesterday = $series[$n - 1];
        $avg3 = $this->avg($last3) ?? $yesterday;
        $avg7 = $this->avg($last7) ?? $avg3;

        $point = (0.5 * $avg3) + (0.3 * $avg7) + (0.2 * $yesterday);
        $std7 = $this->stddev($last7);

        $minBand = $this->minimumForecastBand($point, $type);
        $maxBand = $this->maximumForecastBand($point, $type);
        $band = min(max($std7, $minBand), $maxBand);

        $low = $point - $band;
        $high = $point + $band;

        if ($type === 'rate') {
            $low = max(0, min(100, $low));
            $high = max(0, min(100, $high));
            $point = max(0, min(100, $point));
        } else {
            $low = max(0, $low);
            $high = max(0, $high);
            $point = max(0, $point);
        }

        return [
            'point' => $this->formatForecastNumber($point, $type),
            'low' => $this->formatForecastNumber($low, $type),
            'high' => $this->formatForecastNumber($high, $type),
            'confidence' => $this->forecastConfidence($n, $std7, $point, $type),
            'basis' => [
                'days_available' => $n,
                'avg_last_3d' => round($avg3, 2),
                'avg_last_7d' => round($avg7, 2),
                'yesterday' => round($yesterday, 2),
                'std_last_7d' => round($std7, 2),
            ],
        ];
    }

    private function buildForecastMetricMaturityPolicy(?string $forecastForDate): array
    {
        return [
            'activation' => [
                'session_users' => $this->maturityRuleRow('daily', 'Daily operational metric. Evaluate against the next-day actual row as soon as it is available.', $forecastForDate, 0),
                'workspace_users' => $this->maturityRuleRow('daily', 'Daily operational metric. Evaluate against the next-day actual row as soon as it is available.', $forecastForDate, 0),
                'food_add_success_users' => $this->maturityRuleRow('daily', 'Daily operational metric. Evaluate against the next-day actual row as soon as it is available.', $forecastForDate, 0),
                'workspace_rate_from_session' => $this->maturityRuleRow('daily', 'Daily operational metric. Evaluate against the next-day actual row as soon as it is available.', $forecastForDate, 0),
                'food_add_success_rate_from_session' => $this->maturityRuleRow('daily', 'Daily operational metric. Evaluate against the next-day actual row as soon as it is available.', $forecastForDate, 0),
                'food_add_success_rate_from_workspace' => $this->maturityRuleRow('daily', 'Daily operational metric. Evaluate against the next-day actual row as soon as it is available.', $forecastForDate, 0),
            ],
            'monetization' => [
                'paywall_view_users' => $this->maturityRuleRow('daily', 'Daily operational metric. Evaluate against the next-day actual row as soon as it is available.', $forecastForDate, 0),
                'purchase_success_users' => $this->maturityRuleRow('daily', 'Daily operational metric. Evaluate against the next-day actual row as soon as it is available.', $forecastForDate, 0),
                'purchase_success_rate_from_paywall' => $this->maturityRuleRow('daily', 'Daily operational metric. Evaluate against the next-day actual row as soon as it is available.', $forecastForDate, 0),
            ],
            'retention' => [
                'new_users' => $this->maturityRuleRow('daily', 'Daily cohort count. Evaluate as soon as the actual row for forecast date is available.', $forecastForDate, 0),
                'd0_logged_rate' => $this->maturityRuleRow('daily', 'Daily cohort metric. Evaluate as soon as the actual row for forecast date is available.', $forecastForDate, 0),
                'd1_logged_rate' => $this->maturityRuleRow('daily_plus_1', 'Needs one extra day after forecast date because D1 requires join_date + 1 actual logging behavior.', $forecastForDate, 1),
                'habit_7d_rate' => $this->maturityRuleRow('retention_delayed', 'Retention/cohort metric. Treat separately from daily forecast evaluation and only evaluate when the cohort snapshot is mature enough.', $forecastForDate, 6),
                'avg_log_days_7d' => $this->maturityRuleRow('retention_delayed', 'Retention/cohort metric. Treat separately from daily forecast evaluation and only evaluate when the cohort snapshot is mature enough.', $forecastForDate, 6),
            ],
        ];
    }

    private function maturityRuleRow(string $lane, string $rule, ?string $forecastForDate, int $lagDays): array
    {
        return [
            'lane' => $lane,
            'lag_days' => $lagDays,
            'required_actual_until' => $this->maturityRequiredActualUntil($forecastForDate, $lagDays),
            'rule' => $rule,
            'exclude_from_hard_guardrail_until_mature' => in_array($lane, ['daily_plus_1', 'retention_delayed'], true),
        ];
    }

    private function maturityRequiredActualUntil(?string $forecastForDate, int $lagDays): ?string
    {
        if (!$forecastForDate) {
            return null;
        }

        return date('Y-m-d', strtotime($forecastForDate . ' +' . $lagDays . ' day'));
    }

    private function buildTomorrowForecastRiskFlags(array $activationForecasts, array $retentionForecasts, array $monetizationForecasts = []): array
    {
        $foodFromSession = $activationForecasts['food_add_success_rate_from_session']['point'] ?? null;
        $foodFromWorkspace = $activationForecasts['food_add_success_rate_from_workspace']['point'] ?? null;
        $d1 = $retentionForecasts['d1_logged_rate']['point'] ?? null;
        $habit = $retentionForecasts['habit_7d_rate']['point'] ?? null;
        $purchaseUsers = $monetizationForecasts['purchase_success_users']['point'] ?? null;

        return [
            'activation_risk' => $foodFromSession !== null && $foodFromSession < 40 ? 'at_risk' : 'watch',
            'workspace_quality' => $foodFromWorkspace !== null && $foodFromWorkspace >= 80 ? 'healthy' : 'watch',
            'retention_risk' => $d1 !== null && $d1 < 15 ? 'at_risk' : 'watch',
            'habit_risk' => $habit !== null && $habit < 16 ? 'at_risk' : 'watch',
            'monetization_sample' => $purchaseUsers !== null && $purchaseUsers < 3 ? 'low_sample' : 'enough_to_watch',
            'scaling_caution' => ($d1 !== null && $d1 < 15) || ($foodFromSession !== null && $foodFromSession < 40)
                ? 'block_aggressive_scaling'
                : 'allow_cautious_test',
        ];
    }

    private function tomorrowDateFromHistoricalData(array $activationRows, array $retentionRows): ?string
    {
        $latest = $this->latestDateFromRows($activationRows) ?: $this->latestDateFromRows($retentionRows);

        if (!$latest) {
            return null;
        }

        return date('Y-m-d', strtotime($latest . ' +1 day'));
    }

    private function latestDateFromRows(array $rows): ?string
    {
        $dates = array_values(array_filter(array_map(function ($row) {
            return $row['date'] ?? null;
        }, $rows)));

        if (empty($dates)) {
            return null;
        }

        sort($dates);

        return end($dates) ?: null;
    }

    private function minimumForecastBand(float $point, string $type): float
    {
        if ($type === 'rate') {
            return 2.0;
        }

        if ($type === 'decimal') {
            return 0.1;
        }

        return max(1.0, $point * 0.1);
    }

    private function maximumForecastBand(float $point, string $type): float
    {
        if ($type === 'rate') {
            return 5.0;
        }

        if ($type === 'decimal') {
            return 0.35;
        }

        return max(3.0, $point * 0.18);
    }

    private function formatForecastNumber(float $value, string $type)
    {
        if ($type === 'count') {
            return (int) round($value);
        }

        return round($value, 2);
    }

    private function forecastConfidence(int $daysAvailable, float $std, float $point, string $type): string
    {
        if ($daysAvailable < 3) {
            return 'low';
        }

        if ($daysAvailable < 7) {
            return 'medium_low';
        }

        if ($point == 0) {
            return 'medium';
        }

        $relativeVolatility = abs($std / $point);

        if ($type === 'rate') {
            return $std <= 5 ? 'medium_high' : 'medium';
        }

        if ($relativeVolatility <= 0.2) {
            return 'medium_high';
        }

        if ($relativeVolatility <= 0.4) {
            return 'medium';
        }

        return 'low';
    }

    private function stddev(array $values): float
    {
        $values = array_values(array_filter($values, function ($value) {
            return $value !== null && is_numeric($value);
        }));

        $count = count($values);

        if ($count <= 1) {
            return 0.0;
        }

        $avg = array_sum($values) / $count;
        $variance = array_sum(array_map(function ($value) use ($avg) {
            return pow($value - $avg, 2);
        }, $values)) / $count;

        return sqrt($variance);
    }

    private function latestRowsByUniqueDates(array $rows, string $dateKey, int $days = 7): array
    {
        $dates = [];

        foreach ($rows as $row) {
            if (!empty($row[$dateKey])) {
                $dates[$row[$dateKey]] = true;
            }
        }

        $dates = array_keys($dates);
        rsort($dates);

        $latestDates = array_slice($dates, 0, $days);
        $latestDateMap = array_flip($latestDates);

        return array_values(array_filter($rows, function ($row) use ($dateKey, $latestDateMap) {
            return isset($row[$dateKey]) && isset($latestDateMap[$row[$dateKey]]);
        }));
    }

    private function numericOrNull($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace('%', '', trim($value));
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function percent($numerator, $denominator): ?float
    {
        if (!$denominator || $denominator == 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    private function avg(array $values): ?float
    {
        $values = array_values(array_filter($values, function ($value) {
            return $value !== null && is_numeric($value);
        }));

        if (count($values) === 0) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }

    private function median(array $values): ?float
    {
        $values = array_values(array_filter($values, function ($value) {
            return $value !== null && is_numeric($value);
        }));

        if (empty($values)) {
            return null;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return round(((float) $values[$middle - 1] + (float) $values[$middle]) / 2, 2);
    }

    private function normalizeAdsMetricRow(array $row): ?array
    {
        $campaign = $this->normalizeCampaignDisplayName((string)($row['campaign'] ?? $row['Campaign'] ?? ''));

        if ($campaign === '') {
            return null;
        }

        return [
            'campaign' => $campaign,
            'day' => $row['day'] ?? $row['Day'] ?? $row['date'] ?? null,
            'clicks' => $this->numericOrZero($row['clicks'] ?? $row['Clicks'] ?? null),
            'impressions' => $this->numericOrZero($row['impressions'] ?? $row['Impr.'] ?? $row['Impressions'] ?? null),
            'ctr' => $this->numericOrNull($row['ctr'] ?? $row['CTR'] ?? null),
            'avg_cpc' => $this->numericOrZero($row['avg_cpc'] ?? $row['Avg. CPC'] ?? null),
            'cost' => $this->numericOrZero($row['cost'] ?? $row['Cost'] ?? null),
            'conversions' => $this->numericOrZero($row['conversions'] ?? $row['Conversions'] ?? null),
            'cost_per_conversion' => $this->numericOrZero($row['cost_per_conversion'] ?? $row['Cost / conv.'] ?? null),
            'conversion_rate' => $this->numericOrNull($row['conversion_rate'] ?? $row['Conv. rate'] ?? null),
            'cost_per_install' => $this->numericOrZero($row['cost_per_install'] ?? $row['Cost / Install'] ?? $row['Cost / conv.'] ?? null),
        ];
    }

    private function summarizeAdsCampaigns(array $rows, array $contextByCampaign): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $campaign = $row['campaign'];

            if (!isset($grouped[$campaign])) {
                $grouped[$campaign] = [];
            }

            $grouped[$campaign][] = $row;
        }

        $hasResetSuccessor = false;

        foreach (array_keys($grouped) as $campaignName) {
            $contextKey = $this->normalizeCampaignKey($campaignName);
            $context = $contextByCampaign[$contextKey] ?? [];

            if (($context['lifecycle_status'] ?? null) === 'reset_successor') {
                $hasResetSuccessor = true;
                break;
            }
        }

        $result = [];

        foreach ($grouped as $campaign => $campaignRows) {
            $summary = $this->summarizeAdsRows($campaignRows);
            $recent = $this->summarizeAdsRecentVsPrevious($campaignRows);
            $contextKey = $this->normalizeCampaignKey($campaign);
            $context = $contextByCampaign[$contextKey] ?? [
                'campaign_name' => $campaign,
                'campaign_family' => 'unknown',
                'lifecycle_status' => 'unknown',
                'role' => 'unmapped_campaign',
                'decision_rule' => 'Treat as unmapped campaign; do not make strong lifecycle assumptions.',
            ];

            $result[$campaign] = [
                'campaign' => $campaign,
                'summary' => $summary,
                'recent_vs_previous' => $recent,
                'lifecycle_context' => $context,
                'health' => $this->adsCampaignHealth($summary, $recent, $context, $hasResetSuccessor),
            ];
        }

        uasort($result, function ($a, $b) {
            return ($b['summary']['cost'] ?? 0) <=> ($a['summary']['cost'] ?? 0);
        });

        return $result;
    }

    private function summarizeAdsRows(array $rows): array
    {
        $cost = array_sum(array_column($rows, 'cost'));
        $clicks = array_sum(array_column($rows, 'clicks'));
        $impressions = array_sum(array_column($rows, 'impressions'));
        $conversions = array_sum(array_column($rows, 'conversions'));

        return [
            'days' => count(array_unique(array_filter(array_column($rows, 'day')))),
            'cost' => round($cost, 2),
            'clicks' => round($clicks, 2),
            'impressions' => round($impressions, 2),
            'conversions' => round($conversions, 2),
            'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : null,
            'avg_cpc' => $clicks > 0 ? round($cost / $clicks, 2) : null,
            'cost_per_install' => $conversions > 0 ? round($cost / $conversions, 2) : null,
            'conversion_rate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : null,
        ];
    }

    private function summarizeAdsRecentVsPrevious(array $rows): array
    {
        usort($rows, function ($a, $b) {
            return strcmp((string)($a['day'] ?? ''), (string)($b['day'] ?? ''));
        });

        $dates = array_values(array_unique(array_filter(array_column($rows, 'day'))));
        sort($dates);

        $recentDates = array_slice($dates, -3);
        $previousDates = array_slice($dates, -10, 7);

        $recentRows = array_values(array_filter($rows, function ($row) use ($recentDates) {
            return in_array($row['day'] ?? null, $recentDates, true);
        }));

        $previousRows = array_values(array_filter($rows, function ($row) use ($previousDates) {
            return in_array($row['day'] ?? null, $previousDates, true);
        }));

        $recent = $this->summarizeAdsRows($recentRows);
        $previous = $this->summarizeAdsRows($previousRows);

        return [
            'recent_3d' => $recent,
            'previous_7d' => $previous,
            'cost_per_install_change_pct' => $this->percentageChange($previous['cost_per_install'] ?? null, $recent['cost_per_install'] ?? null),
            'conversion_change_pct' => $this->percentageChange($previous['conversions'] ?? null, $recent['conversions'] ?? null),
            'cost_change_pct' => $this->percentageChange($previous['cost'] ?? null, $recent['cost'] ?? null),
        ];
    }

    private function adsCampaignHealth(array $summary, array $recent, array $context, bool $hasResetSuccessor = false): array
    {
        $lifecycle = $context['lifecycle_status'] ?? 'unknown';
        $cpiChange = $recent['cost_per_install_change_pct'] ?? null;
        $conversionChange = $recent['conversion_change_pct'] ?? null;

        if ($lifecycle === 'degraded_legacy') {
            if ($hasResetSuccessor) {
                return [
                    'status' => 'legacy_context',
                    'risk' => 'historical_campaign_crash_context',
                    'recommendation' => 'do_not_use_legacy_campaign_as_active_acquisition_guardrail_when_reset_successor_exists',
                ];
            }

            return [
                'status' => 'legacy_degraded',
                'risk' => 'known_campaign_crash_context',
                'recommendation' => 'do_not_scale_legacy_campaign_unless_recovery_is_proven',
            ];
        }

        if ($lifecycle === 'reset_successor') {
            return [
                'status' => 'reset_candidate',
                'risk' => 'new_recovery_campaign_needs_downstream_validation',
                'recommendation' => 'evaluate_as_replacement_candidate_not_as_old_campaign_continuation',
            ];
        }

        if ($cpiChange !== null && $cpiChange > 25 && $conversionChange !== null && $conversionChange < -20) {
            return [
                'status' => 'deteriorating',
                'risk' => 'cpi_up_conversions_down',
                'recommendation' => 'hold_or_reduce_budget',
            ];
        }

        if ($cpiChange !== null && $cpiChange < -15 && $conversionChange !== null && $conversionChange > 10) {
            return [
                'status' => 'improving',
                'risk' => 'low',
                'recommendation' => 'allow_cautious_test_if_downstream_activation_is_safe',
            ];
        }

        return [
            'status' => 'monitor',
            'risk' => 'not_enough_ads_signal',
            'recommendation' => 'monitor_campaign',
        ];
    }

    private function adsVerdict(array $overall, array $campaigns): array
    {
        $hasResetCandidate = false;
        $hasLegacyDegraded = false;
        $hasLegacyContext = false;
        $deterioratingCount = 0;
        $improvingCount = 0;

        foreach ($campaigns as $campaign) {
            $status = $campaign['health']['status'] ?? null;

            if ($status === 'reset_candidate') {
                $hasResetCandidate = true;
            }

            if ($status === 'legacy_degraded') {
                $hasLegacyDegraded = true;
            }

            if ($status === 'legacy_context') {
                $hasLegacyContext = true;
            }

            if ($status === 'deteriorating') {
                $deterioratingCount++;
            }

            if ($status === 'improving') {
                $improvingCount++;
            }
        }

        if (($hasLegacyDegraded || $hasLegacyContext) && $hasResetCandidate) {
            return [
                'decision' => 'shift_attention_to_reset_campaign',
                'confidence' => 75,
                'reason' => 'Ada campaign legacy yang diketahui degraded dan ada reset successor. Jangan membaca pause legacy sebagai mematikan acquisition; itu recovery strategy.',
                'final_decision_impact' => 'Final Decision boleh hold legacy campaign, sambil mengevaluasi reset campaign sebagai candidate utama dengan downstream activation/retention guardrail.',
            ];
        }

        if ($deterioratingCount > 0) {
            return [
                'decision' => 'hold_or_reduce_ads',
                'confidence' => 70,
                'reason' => 'Ada campaign yang menunjukkan CPI memburuk dan conversion turun.',
                'final_decision_impact' => 'Ads evidence memperkuat keputusan hold budget.',
            ];
        }

        if ($improvingCount > 0) {
            return [
                'decision' => 'allow_cautious_ads_test',
                'confidence' => 65,
                'reason' => 'Ada campaign yang membaik secara cost/conversion, tetapi tetap perlu validasi downstream activation dan retention.',
                'final_decision_impact' => 'Ads evidence boleh melembutkan keputusan hold menjadi small controlled test jika app metrics aman.',
            ];
        }

        return [
            'decision' => 'monitor_ads',
            'confidence' => 55,
            'reason' => 'Belum ada sinyal ads yang cukup kuat untuk scale atau reduce.',
            'final_decision_impact' => 'Ads evidence menjadi supporting context saja.',
        ];
    }

    private function adsContextByCampaign(array $adsCampaignContext): array
    {
        $result = [];

        foreach (($adsCampaignContext['campaign_lineage'] ?? []) as $context) {
            $campaign = $this->normalizeCampaignKey((string)($context['campaign_name'] ?? ''));

            if ($campaign !== '') {
                $result[$campaign] = $context;
            }
        }

        return $result;
    }

    private function adsDateRange(array $rows): array
    {
        $dates = array_values(array_unique(array_filter(array_column($rows, 'day'))));
        sort($dates);

        return [
            'start' => $dates[0] ?? null,
            'end' => !empty($dates) ? $dates[count($dates) - 1] : null,
        ];
    }

    private function normalizeCampaignDisplayName(string $campaign): string
    {
        $campaign = trim($campaign);
        $campaign = str_replace(["–", "—", "−"], '-', $campaign);
        $campaign = preg_replace('/\s+/', ' ', $campaign);
        $campaign = preg_replace('/\s*-\s*/', ' - ', $campaign);

        return trim($campaign);
    }

    private function normalizeCampaignKey(string $campaign): string
    {
        $campaign = $this->normalizeCampaignDisplayName($campaign);

        return mb_strtolower(trim($campaign), 'UTF-8');
    }

    private function percentageChange($old, $new): ?float
    {
        if ($old === null || $new === null || (float)$old == 0.0) {
            return null;
        }

        return round((($new - $old) / $old) * 100, 2);
    }

    private function numericOrZero($value): float
    {
        return $this->numericOrNull($value) ?? 0.0;
    }
}