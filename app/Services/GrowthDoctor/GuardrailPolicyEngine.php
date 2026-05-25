<?php

namespace App\Services\GrowthDoctor;

class GuardrailPolicyEngine
{
    private const POLICY_VERSION = 'v1';

    private const PRIORITIES = [
        'data_quality_guardrail' => 100,
        'retention_guardrail' => 90,
        'activation_guardrail' => 85,
        'forecast_guardrail' => 75,
        'ads_acquisition_guardrail' => 70,
        'monetization_guardrail' => 60,
        'release_guardrail' => 55,
    ];

    private const RELEASE_RELEVANT_MAJOR_MINOR = ['3.5', '3.6'];
    private const RELEASE_COMPATIBLE_MIN_MAJOR_MINOR = 3.5;
    private const MIN_RELEASE_GUARDRAIL_SESSION_USERS = 100;
    private const MIN_RELEASE_GUARDRAIL_SESSION_SHARE = 3.0;

    public function evaluate(array $metricsContext, array $evaluationContext = []): array
    {
        $guardrails = [
            'data_quality_guardrail' => $this->dataQualityGuardrail($metricsContext),
            'retention_guardrail' => $this->retentionGuardrail($metricsContext),
            'activation_guardrail' => $this->activationGuardrail($metricsContext),
            'forecast_guardrail' => $this->forecastGuardrail($metricsContext, $evaluationContext),
            'ads_acquisition_guardrail' => $this->adsAcquisitionGuardrail($metricsContext),
            'monetization_guardrail' => $this->monetizationGuardrail($metricsContext),
            'release_guardrail' => $this->releaseGuardrail($metricsContext),
        ];

        $triggeredGuardrails = array_filter($guardrails, function ($guardrail) {
            return (bool)($guardrail['triggered'] ?? false);
        });

        $winningGuardrail = $this->winningGuardrail($triggeredGuardrails);
        $deterministicDecision = $this->deterministicDecision($triggeredGuardrails, $winningGuardrail);

        return [
            'status' => 'ok',
            'policy_version' => self::POLICY_VERSION,
            'policy_type' => 'deterministic_guardrail_priority',
            'priority_model' => self::PRIORITIES,
            'guardrails' => $guardrails,
            'triggered_guardrails' => $triggeredGuardrails,
            'winning_guardrail' => $winningGuardrail,
            'deterministic_decision' => $deterministicDecision,
            'reproducibility_note' => 'Same metrics_context and policy_version will produce the same guardrail triggers, winning guardrail, blocked actions, allowed actions, and deterministic decision.',
        ];
    }

    private function dataQualityGuardrail(array $metricsContext): array
    {
        $reasonCodes = [];
        $blockedActions = [];
        $allowedActions = ['continue_monitoring'];

        $retentionStatus = $metricsContext['retention_metrics']['status'] ?? null;
        $activationStatus = $metricsContext['activation_metrics']['status'] ?? null;
        $adsStatus = $metricsContext['ads_metrics']['status'] ?? null;

        if ($retentionStatus === null) {
            $reasonCodes[] = 'retention_metrics_missing';
        }

        if ($activationStatus === null) {
            $reasonCodes[] = 'activation_metrics_missing';
        }

        if ($adsStatus === 'no_ads_data') {
            $reasonCodes[] = 'ads_metrics_missing';
        }

        if (!empty($reasonCodes)) {
            $blockedActions = ['aggressive_ads_scale', 'increase_paywall_pressure', 'release_rollout_expansion'];
        }

        return $this->guardrail(
            'data_quality_guardrail',
            !empty($reasonCodes),
            'high',
            $reasonCodes,
            $blockedActions,
            $allowedActions,
            'Missing or incomplete metrics reduce confidence and block aggressive operating changes.'
        );
    }

    private function retentionGuardrail(array $metricsContext): array
    {
        $retention = $metricsContext['retention_metrics'] ?? [];
        $status = $retention['status'] ?? null;
        $summary = $retention['summary'] ?? [];
        $keyMetrics = $retention['key_metrics'] ?? [];
        $metrics7dAvg = $retention['metrics_7d_avg'] ?? [];

        $d1 = $this->number(
            $keyMetrics['d1_logged_rate']
            ?? $summary['d1_logged_rate']
            ?? $metrics7dAvg['d1_logged_rate']
            ?? null
        );
        $habit = $this->number(
            $keyMetrics['habit_7d_rate']
            ?? $summary['habit_7d_rate']
            ?? $metrics7dAvg['habit_7d_rate']
            ?? null
        );
        $avgLogDays = $this->number(
            $keyMetrics['avg_log_days_7d']
            ?? $summary['avg_log_days_7d']
            ?? $metrics7dAvg['avg_log_days_7d']
            ?? null
        );

        $reasonCodes = [];
        $ignoredReasonCodes = [];

        $d1Mature = $this->isForecastMetricMature($metricsContext, 'retention', 'd1_logged_rate');
        $habitMature = $this->isForecastMetricMature($metricsContext, 'retention', 'habit_7d_rate');
        $avgLogDaysMature = $this->isForecastMetricMature($metricsContext, 'retention', 'avg_log_days_7d');

        if ($status === 'warning' || $status === 'risk') {
            $reasonCodes[] = 'retention_status_' . $status;
        }

        if ($d1 !== null && $d1 < 12) {
            if ($d1Mature) {
                $reasonCodes[] = 'd1_logged_rate_below_scale_guardrail';
            } else {
                $ignoredReasonCodes[] = 'd1_logged_rate_pending_maturity_ignored';
            }
        }

        if ($habit !== null && $habit < 16) {
            if ($habitMature) {
                $reasonCodes[] = 'habit_7d_rate_below_scale_guardrail';
            } else {
                $ignoredReasonCodes[] = 'habit_7d_rate_pending_maturity_ignored';
            }
        }

        if ($avgLogDays !== null && $avgLogDays < 0.75) {
            if ($avgLogDaysMature) {
                $reasonCodes[] = 'avg_log_days_7d_below_recovery_target';
            } else {
                $ignoredReasonCodes[] = 'avg_log_days_7d_pending_maturity_ignored';
            }
        }

        $triggered = !empty($reasonCodes);

        return $this->guardrail(
            'retention_guardrail',
            $triggered,
            $triggered ? 'high' : 'low',
            array_merge($reasonCodes, $ignoredReasonCodes),
            $triggered ? ['aggressive_ads_scale', 'increase_paywall_pressure'] : [],
            $triggered ? ['hold_aggressive_scale', 'prioritize_retention', 'small_controlled_test_only'] : ['continue_monitoring'],
            'Retention weakness blocks aggressive scaling because new acquisition may become wasted spend if users do not return. Reads d1_logged_rate, habit_7d_rate, and avg_log_days_7d from key_metrics, summary, or metrics_7d_avg, but ignores hard blocking for metrics marked pending maturity by forecast metric_maturity_policy.'
        );
    }

    private function isForecastMetricMature(array $metricsContext, string $groupName, string $metricName): bool
    {
        $forecast = $metricsContext['tomorrow_forecast_metrics'] ?? [];
        $policy = $forecast['metric_maturity_policy'][$groupName][$metricName] ?? null;

        if (!is_array($policy) || empty($policy)) {
            return true;
        }

        $excludeUntilMature = (bool)($policy['exclude_from_hard_guardrail_until_mature'] ?? false);

        if (!$excludeUntilMature) {
            return true;
        }

        $requiredActualUntil = (string)($policy['required_actual_until'] ?? '');
        $actualDataAvailableUntil = (string)($forecast['actual_data_available_until'] ?? '');

        if ($requiredActualUntil === '' || $actualDataAvailableUntil === '') {
            return false;
        }

        return strcmp($actualDataAvailableUntil, $requiredActualUntil) >= 0;
    }

    private function activationGuardrail(array $metricsContext): array
    {
        $activation = $metricsContext['activation_metrics'] ?? [];
        $status = $activation['status'] ?? null;
        $summary = $activation['summary'] ?? [];
        $keyMetrics = $activation['key_metrics'] ?? [];
        $metrics7d = $activation['metrics_7d'] ?? [];

        $foodAddFromWorkspace = $this->number(
            $keyMetrics['food_add_success_rate_from_workspace']
            ?? $summary['food_add_success_rate_from_workspace']
            ?? $metrics7d['food_add_success_rate_from_workspace']
            ?? null
        );
        $foodAddFromSession = $this->number(
            $keyMetrics['food_add_success_rate_from_session']
            ?? $summary['food_add_success_rate_from_session']
            ?? $metrics7d['food_add_success_rate_from_session']
            ?? null
        );

        $reasonCodes = [];

        if ($status === 'warning' || $status === 'risk') {
            $reasonCodes[] = 'activation_status_' . $status;
        }

        if ($foodAddFromWorkspace !== null && $foodAddFromWorkspace < 55) {
            $reasonCodes[] = 'food_add_success_from_workspace_below_guardrail';
        }

        if ($foodAddFromSession !== null && $foodAddFromSession < 25) {
            $reasonCodes[] = 'food_add_success_from_session_below_guardrail';
        }

        $triggered = !empty($reasonCodes);

        return $this->guardrail(
            'activation_guardrail',
            $triggered,
            $triggered ? 'medium_high' : 'low',
            $reasonCodes,
            $triggered ? ['aggressive_ads_scale', 'increase_paywall_pressure'] : [],
            $triggered ? ['prioritize_activation_fix', 'first_log_flow_optimization'] : ['continue_monitoring'],
            'Activation weakness blocks scaling because acquisition should not be increased before first-log flow is healthy. Reads food_add_success rates from key_metrics, summary, or metrics_7d.'
        );
    }

    private function forecastGuardrail(array $metricsContext, array $evaluationContext): array
    {
        $forecast = $metricsContext['tomorrow_forecast_metrics'] ?? [];
        $calibration = $evaluationContext['forecast_model_calibration'] ?? [];
        $riskFlags = $forecast['risk_flags']
            ?? ($forecast['guardrails'] ?? ($forecast['guardrail_assessment'] ?? []));

        $scalingCaution = $riskFlags['scaling_caution']
            ?? ($forecast['scaling_caution'] ?? ($riskFlags['scaling_guardrail'] ?? ($forecast['scaling_guardrail'] ?? null)));

        $forecastRole = $calibration['decision_instruction']['forecast_role']
            ?? $calibration['forecast_role']
            ?? null;
        $trustScore = $this->number(
            $calibration['trust_score']['updated_score']
            ?? $calibration['trust_score']
            ?? null
        );

        $reasonCodes = [];
        $hasScalingBlock = in_array($scalingCaution, ['block_aggressive_scaling', 'block_scaling'], true);
        $calibrationSupportsHardCaution = in_array($forecastRole, ['primary_forecast_caution', 'supporting_guardrail', 'can_strengthen_guardrail'], true);
        $forecastDirectionalOnly = $forecastRole === 'directional_signal_only';
        $forecastLowTrust = $forecastDirectionalOnly || ($trustScore !== null && $trustScore < 60);

        if ($hasScalingBlock) {
            $reasonCodes[] = 'forecast_cautions_against_aggressive_scaling';
        }

        if ($calibrationSupportsHardCaution) {
            $reasonCodes[] = 'forecast_calibration_supports_caution';
        }

        if ($forecastDirectionalOnly) {
            $reasonCodes[] = 'forecast_directional_only_not_hard_veto';
        }

        if ($trustScore !== null && $trustScore < 60) {
            $reasonCodes[] = 'forecast_trust_score_below_hard_guardrail_threshold';
        }

        $triggered = $hasScalingBlock && !$forecastLowTrust && $calibrationSupportsHardCaution;

        return $this->guardrail(
            'forecast_guardrail',
            $triggered,
            $triggered ? 'medium_high' : 'low',
            $reasonCodes,
            $triggered ? ['aggressive_ads_scale'] : [],
            $triggered ? ['small_controlled_test_only', 'wait_for_actuals'] : ['continue_monitoring'],
            'Forecast caution is supporting evidence and cannot become a hard veto when calibration says directional_signal_only or trust_score is below 60. A hard forecast guardrail requires scaling_caution block plus calibration support and sufficient forecast trust.'
        );
    }

    private function adsAcquisitionGuardrail(array $metricsContext): array
    {
        $ads = $metricsContext['ads_metrics'] ?? [];
        $adsDecision = $ads['ads_verdict']['decision'] ?? null;
        $campaigns = $ads['campaigns'] ?? [];

        $hasLegacyDegraded = $this->hasCampaignHealth($campaigns, 'legacy_degraded');
        $hasLegacyContext = $this->hasCampaignHealth($campaigns, 'legacy_context');
        $hasResetCandidate = $this->hasCampaignHealth($campaigns, 'reset_candidate');
        $hasLegacyDegradedWithRecentActivity = $this->hasCampaignHealthWithRecentActivity($campaigns, 'legacy_degraded');
        $hasDeteriorating = $this->hasCampaignHealth($campaigns, 'deteriorating');

        // Once a reset successor/candidate exists, the degraded legacy campaign should remain
        // historical context and a blocked action target, not an active acquisition guardrail.
        $legacyShouldTriggerActiveGuardrail = $hasLegacyDegradedWithRecentActivity && !$hasResetCandidate;

        $reasonCodes = [];
        $blockedActions = [];
        $allowedActions = ['continue_monitoring'];
        $severity = 'low';

        if ($legacyShouldTriggerActiveGuardrail) {
            $reasonCodes[] = 'legacy_campaign_degraded_with_recent_activity';
            $blockedActions[] = 'scale_legacy_campaign';
            $severity = 'medium';
        } elseif ($hasLegacyDegraded || $hasLegacyContext) {
            $reasonCodes[] = 'legacy_campaign_degraded_historical_context';
            $blockedActions[] = 'scale_legacy_campaign';
        }

        if ($hasResetCandidate) {
            $reasonCodes[] = 'reset_campaign_candidate';
            $allowedActions[] = 'small_reset_campaign_test';
        }

        if ($adsDecision === 'shift_attention_to_reset_campaign') {
            $reasonCodes[] = 'ads_recommends_shift_to_reset_campaign';
            $allowedActions[] = 'shift_attention_to_reset_campaign';

            if ($legacyShouldTriggerActiveGuardrail) {
                $blockedActions[] = 'treat_legacy_pause_as_acquisition_shutdown';
                $severity = 'medium';
            }
        }

        if ($hasDeteriorating || $adsDecision === 'hold_or_reduce_ads') {
            $reasonCodes[] = 'ads_performance_deteriorating';
            $blockedActions[] = 'increase_ads_budget';
            $allowedActions[] = 'hold_or_reduce_ads';
            $severity = 'medium_high';
        }

        $triggered = $legacyShouldTriggerActiveGuardrail
            || $hasDeteriorating
            || $adsDecision === 'hold_or_reduce_ads';

        return $this->guardrail(
            'ads_acquisition_guardrail',
            $triggered,
            $severity,
            $reasonCodes,
            $blockedActions,
            $allowedActions,
            'Ads acquisition guardrail separates acquisition supply health from downstream product quality and respects campaign lifecycle context.'
        );
    }

    private function monetizationGuardrail(array $metricsContext): array
    {
        $monetization = $metricsContext['monetization_metrics'] ?? [];
        $status = $monetization['status'] ?? null;
        $summary = $monetization['summary'] ?? [];
        $keyMetrics = $monetization['key_metrics'] ?? [];
        $metrics7d = $monetization['metrics_7d'] ?? [];
        $purchaseSuccessUsers = $this->number(
            $keyMetrics['purchase_success_users']
            ?? $summary['purchase_success_users']
            ?? $metrics7d['purchase_success_users']
            ?? null
        );
        $purchaseSuccessRateFromPaywall = $this->number(
            $keyMetrics['purchase_success_rate_from_paywall']
            ?? $summary['purchase_success_rate_from_paywall']
            ?? $metrics7d['purchase_success_rate_from_paywall']
            ?? null
        );
        $reasonCodes = [];

        if ($status === 'risk' || $status === 'warning') {
            $reasonCodes[] = 'monetization_status_' . $status;
        }

        if ($purchaseSuccessUsers !== null && $purchaseSuccessUsers < 3) {
            $reasonCodes[] = 'purchase_success_users_low_sample';
        }

        if ($purchaseSuccessRateFromPaywall !== null && $purchaseSuccessRateFromPaywall < 1) {
            $reasonCodes[] = 'purchase_success_rate_from_paywall_below_guardrail';
        }

        $triggered = !empty($reasonCodes);

        return $this->guardrail(
            'monetization_guardrail',
            $triggered,
            $triggered ? 'medium' : 'low',
            $reasonCodes,
            $triggered ? ['increase_paywall_pressure'] : [],
            $triggered ? ['segment_monetization', 'hold_paywall_pressure'] : ['continue_monitoring'],
            'Monetization guardrail prevents revenue pressure from damaging activation and retention. Reads purchase metrics from key_metrics, summary, or metrics_7d.'
        );
    }

    private function releaseGuardrail(array $metricsContext): array
    {
        $version = $metricsContext['version_metrics'] ?? [];
        $status = $version['status'] ?? null;
        $versions = $version['top_versions'] ?? ($version['versions'] ?? []);
        $totalSessionUsers = $this->totalSessionUsersFromVersions($versions);
        $reasonCodes = [];
        $contextReasonCodes = [];

        if ($status === 'risk' || $status === 'warning') {
            $reasonCodes[] = 'version_status_' . $status;
        }

        foreach ($versions as $versionRow) {
            $appVersion = (string)($versionRow['app_version'] ?? 'unknown');
            $sessionUsers = $this->number($versionRow['session_users'] ?? null);
            $sessionShare = $this->sessionShare($sessionUsers, $totalSessionUsers);

            if (!$this->isReleaseRelevantVersion($appVersion, $sessionUsers, $sessionShare)) {
                if ($sessionUsers !== null && $sessionUsers >= self::MIN_RELEASE_GUARDRAIL_SESSION_USERS) {
                    $contextReasonCodes[] = 'legacy_or_incompatible_version_' . $appVersion . '_ignored_for_release_veto';
                }
                continue;
            }

            $foodAddFromSession = $this->number($versionRow['food_add_success_rate_from_session'] ?? null);
            $foodAddFromWorkspace = $this->number($versionRow['food_add_success_rate_from_workspace'] ?? null);

            if ($sessionUsers !== null && $sessionUsers >= self::MIN_RELEASE_GUARDRAIL_SESSION_USERS) {
                if ($foodAddFromSession !== null && $foodAddFromSession < 30) {
                    $reasonCodes[] = 'version_' . $appVersion . '_food_success_from_session_below_guardrail';
                }

                if ($foodAddFromWorkspace !== null && $foodAddFromWorkspace < 70) {
                    $reasonCodes[] = 'version_' . $appVersion . '_food_success_from_workspace_below_guardrail';
                }
            }
        }

        $triggered = !empty($reasonCodes);
        $reasonCodes = array_merge($reasonCodes, $contextReasonCodes);

        return $this->guardrail(
            'release_guardrail',
            $triggered,
            $triggered ? 'medium' : 'low',
            $reasonCodes,
            $triggered ? ['continue_rollout_expansion'] : [],
            $triggered ? ['pause_rollout_or_monitor_regression'] : ['continue_monitoring'],
            'Release guardrail blocks rollout expansion only when relevant current/recent release versions show regression. Relevance uses compatible release family, active base, and session share; legacy or instrumentation-incompatible versions may be logged as context but cannot veto current rollout decisions.'
        );
    }

    private function isReleaseRelevantVersion(string $appVersion, ?float $sessionUsers = null, ?float $sessionShare = null): bool
    {
        $normalized = strtolower(trim($appVersion));

        if ($normalized === '' || $normalized === 'unknown') {
            return false;
        }

        $majorMinor = $this->majorMinorVersion($normalized);

        if ($majorMinor === null) {
            return false;
        }

        $isConfiguredCurrentLine = in_array(number_format($majorMinor, 1, '.', ''), self::RELEASE_RELEVANT_MAJOR_MINOR, true);
        $hasMeaningfulActiveBase = $sessionUsers !== null
            && $sessionUsers >= self::MIN_RELEASE_GUARDRAIL_SESSION_USERS
            && $sessionShare !== null
            && $sessionShare >= self::MIN_RELEASE_GUARDRAIL_SESSION_SHARE;
        $hasComparableInstrumentation = $majorMinor >= self::RELEASE_COMPATIBLE_MIN_MAJOR_MINOR;

        return $hasComparableInstrumentation && ($isConfiguredCurrentLine || $hasMeaningfulActiveBase);
    }

    private function majorMinorVersion(string $appVersion): ?float
    {
        if (!preg_match('/^(\d+)\.(\d+)/', $appVersion, $matches)) {
            return null;
        }

        return (float)($matches[1] . '.' . $matches[2]);
    }

    private function totalSessionUsersFromVersions(array $versions): float
    {
        $total = 0.0;

        foreach ($versions as $versionRow) {
            $sessionUsers = $this->number($versionRow['session_users'] ?? null);

            if ($sessionUsers !== null && $sessionUsers > 0) {
                $total += $sessionUsers;
            }
        }

        return $total;
    }

    private function sessionShare(?float $sessionUsers, float $totalSessionUsers): ?float
    {
        if ($sessionUsers === null || $totalSessionUsers <= 0) {
            return null;
        }

        return round(($sessionUsers / $totalSessionUsers) * 100, 2);
    }

    private function guardrail(
        string $name,
        bool $triggered,
        string $severity,
        array $reasonCodes,
        array $blockedActions,
        array $allowedActions,
        string $explanation
    ): array {
        return [
            'name' => $name,
            'triggered' => $triggered,
            'severity' => $severity,
            'priority' => self::PRIORITIES[$name] ?? 0,
            'blocked_actions' => array_values(array_unique($blockedActions)),
            'allowed_actions' => array_values(array_unique($allowedActions)),
            'reason_codes' => array_values(array_unique($reasonCodes)),
            'explanation' => $explanation,
        ];
    }

    private function winningGuardrail(array $triggeredGuardrails): ?string
    {
        if (empty($triggeredGuardrails)) {
            return null;
        }

        uasort($triggeredGuardrails, function ($a, $b) {
            return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
        });

        $first = reset($triggeredGuardrails);

        return $first['name'] ?? null;
    }

    private function deterministicDecision(array $triggeredGuardrails, ?string $winningGuardrail): array
    {
        $reasonCodes = [];
        $blockedActions = [];
        $allowedActions = ['continue_monitoring'];

        foreach ($triggeredGuardrails as $guardrail) {
            $reasonCodes = array_merge($reasonCodes, $guardrail['reason_codes'] ?? []);
            $blockedActions = array_merge($blockedActions, $guardrail['blocked_actions'] ?? []);
            $allowedActions = array_merge($allowedActions, $guardrail['allowed_actions'] ?? []);
        }

        $reasonCodes = array_values(array_unique($reasonCodes));
        $blockedActions = array_values(array_unique($blockedActions));
        $allowedActions = array_values(array_unique($allowedActions));

        $businessVerdict = empty($triggeredGuardrails) ? 'CONTINUE_MONITORING' : 'HOLD_AND_OPTIMIZE';
        $adsDecision = in_array('small_reset_campaign_test', $allowedActions, true)
            ? 'hold_aggressive_scale_allow_small_reset_test'
            : (in_array('hold_or_reduce_ads', $allowedActions, true) ? 'hold_or_reduce_ads' : 'monitor_ads');

        $blockedDecision = in_array('aggressive_ads_scale', $blockedActions, true) || in_array('increase_ads_budget', $blockedActions, true)
            ? 'increase_budget_aggressively'
            : (in_array('scale_legacy_campaign', $blockedActions, true) ? 'scale_legacy_campaign' : 'none');

        $allowedDecision = in_array('small_reset_campaign_test', $allowedActions, true)
            ? 'small_controlled_reset_campaign_test'
            : (in_array('prioritize_retention', $allowedActions, true) ? 'prioritize_retention' : 'continue_monitoring');

        return [
            'business_verdict' => $businessVerdict,
            'ads_decision' => $adsDecision,
            'blocked_decision' => $blockedDecision,
            'allowed_decision' => $allowedDecision,
            'winning_guardrail' => $winningGuardrail,
            'confidence_score' => $this->confidenceScore($triggeredGuardrails),
            'blocked_actions' => $blockedActions,
            'allowed_actions' => $allowedActions,
            'reason_codes' => $reasonCodes,
        ];
    }

    private function confidenceScore(array $triggeredGuardrails): int
    {
        if (empty($triggeredGuardrails)) {
            return 60;
        }

        $maxPriority = max(array_map(function ($guardrail) {
            return (int)($guardrail['priority'] ?? 0);
        }, $triggeredGuardrails));

        $score = 55 + (int)round($maxPriority * 0.25) + min(15, count($triggeredGuardrails) * 3);

        return max(0, min(95, $score));
    }

    private function hasCampaignHealth(array $campaigns, string $healthStatus): bool
    {
        foreach ($campaigns as $campaign) {
            if (($campaign['health']['status'] ?? null) === $healthStatus) {
                return true;
            }
        }

        return false;
    }

    private function hasCampaignHealthWithRecentActivity(array $campaigns, string $healthStatus): bool
    {
        foreach ($campaigns as $campaign) {
            if (($campaign['health']['status'] ?? null) !== $healthStatus) {
                continue;
            }

            $recent = $campaign['recent_vs_previous']['recent_3d'] ?? [];
            $cost = $this->number($recent['cost'] ?? null) ?? 0.0;
            $clicks = $this->number($recent['clicks'] ?? null) ?? 0.0;
            $conversions = $this->number($recent['conversions'] ?? null) ?? 0.0;

            if ($cost > 0 || $clicks > 0 || $conversions > 0) {
                return true;
            }
        }

        return false;
    }

    private function number($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float)$value : null;
    }
}