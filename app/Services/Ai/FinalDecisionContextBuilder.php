<?php

namespace App\Services\Ai;

class FinalDecisionContextBuilder
{
    public function buildCompact(
        array $metricsContext,
        array $specialistAgents,
        array $structuredNegotiation,
        array $evaluations = [],
        array $baselineComparison = [],
        bool $strict = false
    ): array {
        $forecastEvaluations = $evaluations['forecast_evaluations']
            ?? ($metricsContext['forecast_evaluations'] ?? ($metricsContext['evaluations']['forecast_evaluations'] ?? []));
        $forecastCalibration = $evaluations['forecast_model_calibration']
            ?? ($metricsContext['forecast_model_calibration'] ?? ($metricsContext['evaluations']['forecast_model_calibration'] ?? []));
        $tomorrowForecast = $specialistAgents['ai_tomorrow_forecast_agent']['result'] ?? [];

        return [
            'checkpoint_meta' => $this->checkpointMeta($metricsContext),
            'core_metrics' => [
                'activation' => $this->activationMetrics($metricsContext),
                'retention' => $this->retentionMetrics($metricsContext),
                'monetization' => $this->monetizationMetrics($metricsContext),
                'ads' => $this->adsMetrics($metricsContext, $strict ? 1 : 2),
                'version' => $this->versionMetrics($metricsContext, $strict ? 3 : 5),
            ],
            'guardrail_policy' => $this->guardrailPolicy($metricsContext),
            'specialist_summaries' => $this->specialistSummaries($specialistAgents, $strict ? 2 : 3),
            'structured_negotiation' => $this->structuredNegotiation($structuredNegotiation, $strict ? 3 : 5),
            'forecast' => [
                'forecast_for_date' => $tomorrowForecast['forecast_for_date'] ?? ($metricsContext['tomorrow_forecast_metrics']['forecast_for_date'] ?? null),
                'data_as_of_date' => $tomorrowForecast['data_as_of_date'] ?? ($metricsContext['tomorrow_forecast_metrics']['data_as_of_date'] ?? null),
                'risk_flags' => $this->takeAssoc($tomorrowForecast['risk_flags_used'] ?? ($tomorrowForecast['risk_flags'] ?? []), $strict ? 4 : 8),
                'evaluation' => $this->forecastEvaluation($forecastEvaluations),
                'calibration' => $this->forecastCalibration($forecastCalibration),
            ],
            'baseline_comparison' => $this->baselineComparison($baselineComparison ?: ($structuredNegotiation['quantitative_baseline_comparison'] ?? ($structuredNegotiation['baseline_comparison'] ?? [])), $strict),
            'evidence_refs' => $this->evidenceRefs($metricsContext, $strict ? 12 : 20),
            'decision_authority_split' => [
                'guardrail_policy_role' => 'sets non-negotiable safety boundary',
                'agent_society_role' => 'selects safe operating plan within boundary',
                'not_claimed' => 'Agent Society did not override hard guardrails.',
            ],
        ];
    }

    public function estimatedPayloadBytes(array $compactContext): int
    {
        return strlen(json_encode($compactContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    private function checkpointMeta(array $metricsContext): array
    {
        $meta = $metricsContext['checkpoint_meta'] ?? ($metricsContext['meta'] ?? []);
        $profile = $metricsContext['app_profile'] ?? [];

        return [
            'app_name' => $meta['app_name'] ?? ($profile['app_name'] ?? null),
            'window_start' => $meta['window_start'] ?? null,
            'window_end' => $meta['window_end'] ?? null,
            'timezone' => $meta['timezone'] ?? ($profile['timezone'] ?? null),
        ];
    }

    private function activationMetrics(array $metricsContext): array
    {
        $metrics = $metricsContext['activation_metrics']['metrics_7d'] ?? ($metricsContext['activation_metrics'] ?? []);

        return [
            'session_users' => $metrics['session_users'] ?? null,
            'workspace_users' => $metrics['workspace_users'] ?? null,
            'food_add_success_users' => $metrics['food_add_success_users'] ?? null,
            'food_add_success_rate_from_session' => $metrics['food_add_success_rate_from_session'] ?? null,
            'food_add_success_rate_from_workspace' => $metrics['food_add_success_rate_from_workspace'] ?? null,
            'paywall_rate_from_food_add_success' => $metrics['paywall_rate_from_food_add_success'] ?? null,
            'purchase_success_rate_from_paywall' => $metrics['purchase_success_rate_from_paywall'] ?? null,
            'diagnosis' => $metricsContext['activation_metrics']['diagnosis'] ?? null,
        ];
    }

    private function retentionMetrics(array $metricsContext): array
    {
        $metrics = $metricsContext['retention_metrics']['metrics_7d_avg'] ?? ($metricsContext['retention_metrics'] ?? []);

        return [
            'd0_logged_rate' => $metrics['d0_logged_rate'] ?? null,
            'd1_logged_rate' => $metrics['d1_logged_rate'] ?? null,
            'habit_7d_rate' => $metrics['habit_7d_rate'] ?? null,
            'avg_log_days_7d' => $metrics['avg_log_days_7d'] ?? null,
            'diagnosis' => $metricsContext['retention_metrics']['diagnosis'] ?? null,
        ];
    }

    private function monetizationMetrics(array $metricsContext): array
    {
        $metrics = $metricsContext['monetization_metrics']['metrics_7d'] ?? ($metricsContext['monetization_metrics'] ?? []);

        return [
            'paywall_view_users' => $metrics['paywall_view_users'] ?? null,
            'purchase_start_users' => $metrics['purchase_start_users'] ?? null,
            'purchase_success_users' => $metrics['purchase_success_users'] ?? null,
            'paywall_rate_from_food_add_success' => $metrics['paywall_rate_from_food_add_success'] ?? null,
            'purchase_success_rate_from_paywall' => $metrics['purchase_success_rate_from_paywall'] ?? null,
            'diagnosis' => $metricsContext['monetization_metrics']['diagnosis'] ?? null,
        ];
    }

    private function adsMetrics(array $metricsContext, int $campaignLimit): array
    {
        $ads = $metricsContext['ads_metrics'] ?? [];
        $overall = $ads['overall'] ?? [];

        return [
            'cost' => $overall['cost'] ?? null,
            'clicks' => $overall['clicks'] ?? null,
            'impressions' => $overall['impressions'] ?? null,
            'conversions' => $overall['conversions'] ?? null,
            'cost_per_install' => $overall['cost_per_install'] ?? null,
            'conversion_rate' => $overall['conversion_rate'] ?? null,
            'ads_verdict' => $ads['ads_verdict']['decision'] ?? null,
            'campaign_summaries' => $this->campaignSummaries($ads['campaigns'] ?? [], $campaignLimit),
        ];
    }

    private function campaignSummaries($campaigns, int $limit): array
    {
        if (!is_array($campaigns)) {
            return [];
        }

        $rows = [];
        foreach ($campaigns as $name => $row) {
            if (!is_array($row)) {
                continue;
            }
            $summary = $row['summary'] ?? [];
            $rows[] = [
                'campaign' => is_string($name) ? $name : ($row['campaign'] ?? null),
                'cost' => $summary['cost'] ?? null,
                'conversions' => $summary['conversions'] ?? null,
                'cost_per_install' => $summary['cost_per_install'] ?? null,
                'conversion_rate' => $summary['conversion_rate'] ?? null,
                'lifecycle_status' => $row['lifecycle_context']['lifecycle_status'] ?? null,
                'health' => $row['health']['status'] ?? null,
            ];
        }

        usort($rows, function ($a, $b) {
            return ((float) ($b['cost'] ?? 0)) <=> ((float) ($a['cost'] ?? 0));
        });

        return array_slice($rows, 0, $limit);
    }

    private function versionMetrics(array $metricsContext, int $limit): array
    {
        $version = $metricsContext['version_metrics'] ?? [];
        $source = $version['compact_version_context']['relevant_versions'] ?? ($version['top_versions'] ?? ($version['versions'] ?? []));

        return [
            'top_versions' => $this->versionRows($source, $limit),
            'release_candidate_summary' => $version['compact_version_context']['release_candidate_versions'] ?? [],
            'legacy_version_risk_summary' => $version['compact_version_context']['legacy_context_summary'] ?? null,
        ];
    }

    private function versionRows($rows, int $limit): array
    {
        if (!is_array($rows)) {
            return [];
        }

        return array_slice(array_map(function ($row) {
            if (!is_array($row)) {
                return $row;
            }

            return [
                'app_version' => $row['app_version'] ?? null,
                'session_users' => $row['session_users'] ?? null,
                'workspace_users' => $row['workspace_users'] ?? null,
                'food_add_success_rate_from_session' => $row['food_add_success_rate_from_session'] ?? null,
                'purchase_success_rate_from_paywall' => $row['purchase_success_rate_from_paywall'] ?? null,
                'release_relevance' => $row['release_relevance'] ?? null,
            ];
        }, array_values($rows)), 0, $limit);
    }

    private function guardrailPolicy(array $metricsContext): array
    {
        $policy = $metricsContext['guardrail_policy'] ?? [];
        $decision = $policy['deterministic_decision'] ?? [];
        $triggered = $policy['triggered_guardrails'] ?? [];

        return [
            'triggered_guardrails_count' => is_array($triggered) ? count($triggered) : 0,
            'winning_guardrail' => $policy['winning_guardrail'] ?? null,
            'deterministic_business_verdict' => $decision['business_verdict'] ?? null,
            'blocked_decision' => $decision['blocked_decision'] ?? null,
            'allowed_decision' => $decision['allowed_decision'] ?? null,
            'blocked_actions' => $decision['blocked_actions'] ?? ($policy['blocked_actions'] ?? []),
            'allowed_actions' => $decision['allowed_actions'] ?? ($policy['allowed_actions'] ?? []),
        ];
    }

    private function specialistSummaries(array $specialistAgents, int $limit): array
    {
        $compact = [];
        foreach ($specialistAgents as $key => $agent) {
            if (!is_array($agent)) {
                continue;
            }
            $result = $agent['result'] ?? [];
            $compact[$key] = [
                'status' => $agent['status'] ?? null,
                'result_status' => is_array($result) ? ($result['status'] ?? null) : null,
                'summary' => $this->summaryFromAgent($agent),
                'top_recommendations' => $this->firstList($result, ['recommended_actions', 'prioritized_actions', 'opportunities', 'recommended_experiment'], $limit),
                'risk_notes' => $this->firstList($result, ['risk_notes', 'release_risks', 'risk_drivers', 'weak_evidence_or_uncertainty'], $limit),
                'decision_usable' => $agent['decision_usable'] ?? (is_array($result) ? ($result['decision_usable'] ?? null) : null),
                'risk_flags_used' => $key === 'ai_tomorrow_forecast_agent' && is_array($result)
                    ? $this->takeAssoc($result['risk_flags_used'] ?? [], $limit + 2)
                    : null,
            ];
        }

        return $compact;
    }

    private function summaryFromAgent(array $agent): ?string
    {
        $result = $agent['result'] ?? [];
        $summary = $agent['summary']
            ?? (is_array($result) ? ($result['summary'] ?? ($result['executive_summary'] ?? ($result['diagnosis'] ?? ($result['main_diagnosis'] ?? ($result['main_predicted_risk'] ?? null))))) : null)
            ?? ($agent['error'] ?? null);

        return $this->stringOrJson($summary, 600);
    }

    private function structuredNegotiation(array $negotiation, int $tensionLimit): array
    {
        $summary = $negotiation['summary'] ?? [];
        $execution = $negotiation['execution'] ?? [];
        $conflicts = $negotiation['conflict_matrix'] ?? ($negotiation['conflicts'] ?? []);

        return [
            'rounds_completed' => $execution['rounds_completed'] ?? ($negotiation['rounds_completed'] ?? ($negotiation['round'] ?? null)),
            'early_exit' => $execution['early_exit'] ?? null,
            'early_exit_reason' => $execution['early_exit_reason'] ?? null,
            'total_conflict_count' => $summary['total_conflict_count'] ?? (is_array($conflicts) ? count($conflicts) : 0),
            'material_conflict_count' => $summary['material_conflict_count'] ?? null,
            'critical_conflict_count' => $summary['critical_conflict_count'] ?? null,
            'resolved_material_tension_count' => $summary['resolved_material_tension_count'] ?? null,
            'minor_bounded_tension_count' => $summary['minor_bounded_tension_count'] ?? null,
            'minor_bounded_caution_count' => $summary['minor_bounded_caution_count'] ?? ($summary['minor_bounded_tension_count'] ?? null),
            'partial_concession_count' => $summary['partial_concession_count'] ?? null,
            'safety_bounded_revision_count' => $summary['safety_bounded_revision_count'] ?? null,
            'conflict_ids' => $this->conflictIds($conflicts),
            'key_tensions' => $this->keyTensions($conflicts, $tensionLimit),
        ];
    }

    private function conflictIds($conflicts): array
    {
        if (!is_array($conflicts)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($conflict) {
            return is_array($conflict) ? ($conflict['conflict_id'] ?? null) : null;
        }, $conflicts)));
    }

    private function keyTensions($conflicts, int $limit): array
    {
        if (!is_array($conflicts)) {
            return [];
        }

        $rows = [];
        foreach ($conflicts as $conflict) {
            if (!is_array($conflict)) {
                continue;
            }
            $rows[] = [
                'conflict_id' => $conflict['conflict_id'] ?? null,
                'title' => $conflict['title'] ?? ($conflict['topic'] ?? null),
                'severity' => $conflict['severity'] ?? null,
                'initial_position' => $this->stringOrJson($conflict['initial_position'] ?? ($conflict['domain_only_tension'] ?? null), 300),
                'resolution' => $this->stringOrJson($conflict['bounded_system_resolution'] ?? ($conflict['resolution_candidate'] ?? null), 300),
            ];
        }

        return array_slice($rows, 0, $limit);
    }

    private function forecastEvaluation(array $evaluations): array
    {
        $evaluated = is_array($evaluations['evaluated'] ?? null) ? $evaluations['evaluated'] : [];
        $latest = $this->latestByDate($evaluated, 'forecast_for_date');
        $latestSummary = is_array($latest['summary'] ?? null) ? $latest['summary'] : [];
        $mainMisses = is_array($latestSummary['main_misses'] ?? null) ? $latestSummary['main_misses'] : [];

        return [
            'evaluated_count' => $evaluations['evaluated_count'] ?? count($evaluated),
            'pending_count' => $evaluations['pending_count'] ?? count($evaluations['pending'] ?? []),
            'actual_data_available_until' => $latest['actual_data_available_until'] ?? ($evaluations['actual_data_available_until'] ?? null),
            'latest_forecast_for_date' => $latest['forecast_for_date'] ?? null,
            'latest_quality' => $latest['summary']['forecast_quality'] ?? ($evaluations['status'] ?? null),
            'latest_hit_rate' => $latestSummary['hit_rate'] ?? null,
            'metrics_pending_maturity' => $latestSummary['metrics_pending_maturity'] ?? null,
            'main_misses' => array_values(array_map(function ($miss) {
                if (!is_array($miss)) {
                    return $miss;
                }

                return trim(implode(' ', array_filter([
                    $miss['metric'] ?? null,
                    $miss['quality'] ?? null,
                ], function ($value) {
                    return is_string($value) && trim($value) !== '';
                })));
            }, $mainMisses)),
        ];
    }

    private function forecastCalibration(array $calibration): array
    {
        return [
            'trust_score' => $calibration['trust_score']['updated_score'] ?? null,
            'trust_interpretation' => $calibration['trust_score']['interpretation'] ?? null,
            'forecast_role' => $calibration['decision_instruction']['forecast_role'] ?? null,
            'systematic_bias_detected' => $calibration['bias_detection']['systematic_bias_detected'] ?? null,
        ];
    }

    private function baselineComparison(array $baselineComparison, bool $strict): array
    {
        $delta = $baselineComparison['delta'] ?? [];

        return [
            'baseline_mode' => $baselineComparison['baseline_mode'] ?? null,
            'headline' => $baselineComparison['headline'] ?? null,
            'delta' => $this->takeAssoc(is_array($delta) ? $delta : [], $strict ? 3 : 6),
            'limitations' => array_slice($baselineComparison['limitations'] ?? [], 0, $strict ? 2 : 3),
        ];
    }

    private function evidenceRefs(array $metricsContext, int $limit): array
    {
        $refs = $metricsContext['source_metric_refs'] ?? [];
        if (!is_array($refs)) {
            return [];
        }

        $compact = [];
        foreach ($refs as $key => $ref) {
            if (!is_array($ref)) {
                continue;
            }
            $compact[] = [
                'metric' => $ref['metric'] ?? (is_string($key) ? $key : null),
                'value' => $ref['value'] ?? null,
                'source_path' => $ref['source_path'] ?? ($ref['path'] ?? null),
                'interpretation' => $this->stringOrJson($ref['interpretation'] ?? ($ref['label'] ?? null), 180),
            ];
        }

        return array_slice($compact, 0, $limit);
    }

    private function firstList(array $source, array $keys, int $limit): array
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }

            $value = $source[$key];
            if (!is_array($value)) {
                return [$this->stringOrJson($value, 300)];
            }

            if ($this->isAssoc($value)) {
                return [array_map(function ($item) {
                    return $this->stringOrJson($item, 300);
                }, array_slice($value, 0, $limit, true))];
            }

            return array_slice(array_map(function ($item) {
                return $this->stringOrJson($item, 300);
            }, $value), 0, $limit);
        }

        return [];
    }

    private function takeAssoc(array $value, int $limit): array
    {
        return array_slice($value, 0, $limit, true);
    }

    private function latestByDate(array $rows, string $dateKey): array
    {
        $valid = array_values(array_filter($rows, function ($row) use ($dateKey) {
            return is_array($row) && !empty($row[$dateKey]);
        }));

        usort($valid, function ($a, $b) use ($dateKey) {
            return strcmp((string) ($b[$dateKey] ?? ''), (string) ($a[$dateKey] ?? ''));
        });

        return $valid[0] ?? [];
    }

    private function stringOrJson($value, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $text = trim((string) $value);

        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength) . '...';
        }

        return $text;
    }

    private function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
