<?php

namespace App\Services\GrowthDoctor;

class AgentSocietyBaselineComparisonService
{
    private const DOMAINS = ['activation', 'retention', 'monetization', 'version', 'ads', 'forecast', 'guardrail'];

    private const SPECIALIST_MAP = [
        'ads_agent_only' => ['key' => 'ai_ads_agent', 'agent' => 'AI Ads Agent', 'domain' => 'ads'],
        'monetization_agent_only' => ['key' => 'ai_monetization_agent', 'agent' => 'AI Monetization Agent', 'domain' => 'monetization'],
        'activation_agent_only' => ['key' => 'ai_activation_agent', 'agent' => 'AI Activation Agent', 'domain' => 'activation'],
        'retention_agent_only' => ['key' => 'ai_retention_agent', 'agent' => 'AI Retention Agent', 'domain' => 'retention'],
        'version_agent_only' => ['key' => 'ai_version_agent', 'agent' => 'AI Version Agent', 'domain' => 'version'],
        'forecast_agent_only' => ['key' => 'ai_tomorrow_forecast_agent', 'agent' => 'Tomorrow Forecast Agent', 'domain' => 'forecast'],
    ];

    private const SCORE_METHODOLOGY = [
        'score_type' => 'heuristic_audit_score',
        'not_causal_proof' => true,
        'evidence_coverage_formula' => 'round((evidence_domains_used / max(1, available_domains_in_run)) * 100)',
        'caveat_coverage_formula' => 'min(100, resolved_material_tensions_detected*25 + minor_bounded_cautions_detected*15 + safety_bounded_revisions*20 + guardrail_blocks_used*20)',
        'decision_completeness_formula' => 'min(100, action_items_count*15 + action_domains_count*15 + cross_domain_constraints_count*10 + guardrail_blocks_used*15 + safety_bounded_revisions*15)',
    ];

    public function compare(array $context): array
    {
        $metricsContext = $context['metrics_context'] ?? [];
        $specialistAgents = $context['specialist_agents'] ?? [];
        $structuredNegotiation = $context['structured_negotiation'] ?? [];
        $conflictMatrix = $context['conflict_matrix'] ?? ($structuredNegotiation['conflict_matrix'] ?? ($structuredNegotiation['conflicts'] ?? []));
        $finalDecisionEnvelope = $context['final_decision'] ?? [];
        $finalDecision = $finalDecisionEnvelope['result'] ?? $finalDecisionEnvelope;
        $guardrailPolicy = $context['guardrail_policy'] ?? ($metricsContext['guardrail_policy'] ?? []);
        $sourceMetricRefs = $context['source_metric_refs'] ?? ($metricsContext['source_metric_refs'] ?? []);
        $normalizedActionPlan = $context['normalized_action_plan'] ?? $this->normalizedActionPlan($finalDecision);

        $availableDomains = $this->availableDomains($metricsContext, $sourceMetricRefs, $guardrailPolicy);
        $baselineSelection = $this->selectBaseline($metricsContext, $specialistAgents, $guardrailPolicy);
        $baselineAgent = $specialistAgents[$baselineSelection['key']] ?? [];

        $baseline = $this->baselineMetrics($baselineSelection, $baselineAgent, $metricsContext, $sourceMetricRefs, $guardrailPolicy, $availableDomains);
        $society = $this->agentSocietyMetrics(
            $specialistAgents,
            $structuredNegotiation,
            $conflictMatrix,
            $finalDecision,
            $guardrailPolicy,
            $sourceMetricRefs,
            $normalizedActionPlan,
            $availableDomains
        );

        $delta = $this->delta($baseline, $society);

        return [
            'baseline_mode' => $baselineSelection['mode'],
            'baseline_source_agent' => $baselineSelection['agent'],
            'comparison_method' => 'derived_from_existing_run_outputs',
            'is_deterministic' => false,
            'is_data_derived' => true,
            'available_domains_in_run' => count($availableDomains),
            'score_methodology' => self::SCORE_METHODOLOGY,
            'single_agent_baseline' => $baseline,
            'agent_society' => $society,
            'delta' => $delta,
            'headline' => $this->headline($baselineSelection['mode'], $delta),
            'explanation' => 'Baseline is derived from the selected strongest single specialist output for this run. Agent Society uses guardrail-mediated specialist agents plus structured negotiation and final synthesis.',
            'limitations' => [
                'Baseline is derived from existing single-agent output, not from a separate LLM rerun.',
                'Scores are heuristic and intended for comparative audit, not scientific causal proof.',
            ],
        ];
    }

    public function compact(array $comparison): array
    {
        if (empty($comparison)) {
            return [];
        }

        return [
            'baseline_mode' => $comparison['baseline_mode'] ?? null,
            'single_agent_scores' => $this->scoreSlice($comparison['single_agent_baseline'] ?? []),
            'agent_society_scores' => $this->scoreSlice($comparison['agent_society'] ?? []),
            'delta' => $comparison['delta'] ?? [],
            'headline' => $comparison['headline'] ?? null,
        ];
    }

    private function selectBaseline(array $metricsContext, array $specialistAgents, array $guardrailPolicy): array
    {
        $guardrailText = strtolower($this->text([$guardrailPolicy]));
        $hasAdsMetrics = !empty($metricsContext['ads_metrics'] ?? []);

        if ($hasAdsMetrics && $this->containsAny($guardrailText, ['ads', 'budget', 'scaling', 'scale'])) {
            return $this->selection('ads_agent_only');
        }

        if (!empty($metricsContext['monetization_metrics'] ?? []) && $this->containsAny($this->text($metricsContext['monetization_metrics']), ['paywall', 'purchase', 'revenue', 'monetization'])) {
            return $this->selection('monetization_agent_only');
        }

        if (!empty($metricsContext['activation_metrics'] ?? []) && $this->containsAny(strtolower($this->text($metricsContext['activation_metrics']) . ' ' . $this->text($specialistAgents['ai_activation_agent'] ?? [])), ['warning', 'first-core-action', 'core_action', 'core action', 'activation'])) {
            return $this->selection('activation_agent_only');
        }

        return $this->highestRiskSpecialist($specialistAgents);
    }

    private function selection(string $mode): array
    {
        $map = self::SPECIALIST_MAP[$mode];

        return [
            'mode' => $mode,
            'key' => $map['key'],
            'agent' => $map['agent'],
            'domain' => $map['domain'],
        ];
    }

    private function highestRiskSpecialist(array $specialistAgents): array
    {
        $bestMode = 'ads_agent_only';
        $bestScore = -1;

        foreach (self::SPECIALIST_MAP as $mode => $map) {
            $agent = $specialistAgents[$map['key']] ?? [];
            $text = strtolower($this->text($agent));
            $score = 0;
            foreach (['risk', 'weak', 'warning', 'blocked', 'caution', 'unsafe', 'low sample', 'not proven'] as $needle) {
                if (strpos($text, $needle) !== false) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMode = $mode;
            }
        }

        return $this->selection($bestMode);
    }

    private function baselineMetrics(array $selection, array $agent, array $metricsContext, array $sourceMetricRefs, array $guardrailPolicy, array $availableDomains): array
    {
        $text = strtolower($this->text($agent));
        $domains = $this->domainsFromValue($agent);
        $domains[] = $selection['domain'];

        foreach ($availableDomains as $domain) {
            if ($domain !== $selection['domain'] && strpos($text, $domain) !== false) {
                $domains[] = $domain;
            }
        }

        $evidenceRefs = $this->collectSourceRefs([$agent]);
        $inferredRefs = false;
        if (empty($evidenceRefs)) {
            $evidenceRefs = $this->refsForDomain($sourceMetricRefs, $selection['domain']);
            $inferredRefs = !empty($evidenceRefs);
        }

        $minorCautions = min(3, $this->keywordCount($text, ['small sample', 'monitor', 'cautious', 'not proven', 'noisy', 'watch', 'weak', 'uncertain']));
        $guardrailBlocks = $this->agentUsesGuardrail($text, $guardrailPolicy) ? 1 : 0;
        $safetyRevisions = $this->baselineSafetyBoundedRevision($text);
        $actionItems = $this->baselineActionItems($selection['domain'], $text);
        $actionDomains = count(array_unique($this->baselineActionDomains($selection['domain'], $text)));
        $crossDomainConstraints = $this->baselineCrossDomainConstraints($selection['domain'], $text);
        $risk = $this->riskLevel($text, $guardrailPolicy, $guardrailBlocks, $crossDomainConstraints, true);

        $metrics = [
            'summary' => $this->summaryText($agent),
            'decision_posture' => $this->decisionPosture($agent, $text),
            'evidence_domains_used' => count(array_unique(array_intersect($domains, self::DOMAINS))),
            'source_metric_ref_count' => count(array_unique($evidenceRefs)),
            'source_metric_ref_count_inferred' => $inferredRefs,
            'resolved_material_tensions_detected' => 0,
            'minor_bounded_cautions_detected' => $minorCautions,
            'minor_bounded_cautions_heuristic' => true,
            'safety_bounded_revisions' => $safetyRevisions,
            'safety_bounded_revisions_inferred' => $safetyRevisions > 0,
            'guardrail_blocks_used' => $guardrailBlocks,
            'guardrail_blocked_decision' => $this->blockedDecision($guardrailPolicy),
            'guardrail_allowed_decision' => $this->allowedDecision($guardrailPolicy),
            'action_items_count' => $actionItems,
            'action_items_count_inferred' => true,
            'action_domains_count' => $actionDomains,
            'cross_domain_constraints_count' => $crossDomainConstraints,
            'unsafe_or_overbroad_action_risk' => $risk,
        ];

        return $this->withScores($metrics, count($availableDomains));
    }

    private function agentSocietyMetrics(
        array $specialistAgents,
        array $structuredNegotiation,
        array $conflictMatrix,
        array $finalDecision,
        array $guardrailPolicy,
        array $sourceMetricRefs,
        array $normalizedActionPlan,
        array $availableDomains
    ): array {
        $summary = $structuredNegotiation['summary'] ?? [];
        $text = strtolower($this->text([$specialistAgents, $structuredNegotiation, $finalDecision, $guardrailPolicy]));
        $domains = $this->domainsFromValue([$specialistAgents, $structuredNegotiation, $finalDecision, $guardrailPolicy]);
        $evidenceRefs = $this->collectSourceRefs([$specialistAgents, $structuredNegotiation, $conflictMatrix, $finalDecision]);
        if (empty($evidenceRefs)) {
            $evidenceRefs = $this->collectSourceRefs([$sourceMetricRefs]);
        }

        $resolvedMaterial = (int) ($summary['resolved_material_tension_count'] ?? $this->countResolvedMaterialTensions($conflictMatrix));
        $minorCautions = (int) ($summary['minor_bounded_caution_count'] ?? ($summary['minor_bounded_tension_count'] ?? $this->countMinorBoundedCautions($conflictMatrix)));
        $safetyRevisions = (int) ($summary['safety_bounded_revision_count'] ?? $this->countSafetyRevisions($structuredNegotiation));
        $guardrailBlocks = $this->guardrailBlocksUsed($guardrailPolicy);
        $actionDomains = array_values(array_unique(array_filter(array_map(function ($item) {
            return is_array($item) ? ($item['owner_domain'] ?? null) : null;
        }, $normalizedActionPlan))));
        $crossDomainConstraints = $this->societyCrossDomainConstraints($structuredNegotiation, $conflictMatrix);

        $metrics = [
            'summary' => $this->summaryText(['result' => $finalDecision]) ?: ($summary['negotiation_outcome'] ?? 'Guardrail-mediated specialist negotiation and final synthesis.'),
            'decision_posture' => $this->decisionPosture(['result' => $finalDecision], $text),
            'evidence_domains_used' => count(array_unique(array_intersect($domains, self::DOMAINS))),
            'source_metric_ref_count' => count(array_unique($evidenceRefs)),
            'resolved_material_tensions_detected' => $resolvedMaterial,
            'minor_bounded_cautions_detected' => $minorCautions,
            'safety_bounded_revisions' => $safetyRevisions,
            'guardrail_blocks_used' => $guardrailBlocks,
            'guardrail_blocked_decision' => $this->blockedDecision($guardrailPolicy),
            'guardrail_allowed_decision' => $this->allowedDecision($guardrailPolicy),
            'action_items_count' => count($normalizedActionPlan),
            'action_domains_count' => count($actionDomains),
            'cross_domain_constraints_count' => $crossDomainConstraints,
            'unsafe_or_overbroad_action_risk' => $this->riskLevel($text, $guardrailPolicy, $guardrailBlocks, $crossDomainConstraints, false),
        ];

        return $this->withScores($metrics, count($availableDomains));
    }

    private function withScores(array $metrics, int $availableDomainCount): array
    {
        $metrics['evidence_coverage_score'] = (int) round(($metrics['evidence_domains_used'] / max(1, $availableDomainCount)) * 100);
        $metrics['caveat_coverage_score'] = min(100,
            ($metrics['resolved_material_tensions_detected'] * 25)
            + ($metrics['minor_bounded_cautions_detected'] * 15)
            + ($metrics['safety_bounded_revisions'] * 20)
            + ($metrics['guardrail_blocks_used'] * 20)
        );
        $metrics['decision_completeness_score'] = min(100,
            ($metrics['action_items_count'] * 15)
            + ($metrics['action_domains_count'] * 15)
            + ($metrics['cross_domain_constraints_count'] * 10)
            + ($metrics['guardrail_blocks_used'] * 15)
            + ($metrics['safety_bounded_revisions'] * 15)
        );

        return $metrics;
    }

    private function delta(array $baseline, array $society): array
    {
        $keys = [
            'evidence_domains_used',
            'source_metric_ref_count',
            'resolved_material_tensions_detected',
            'minor_bounded_cautions_detected',
            'safety_bounded_revisions',
            'guardrail_blocks_used',
            'action_items_count',
            'action_domains_count',
            'cross_domain_constraints_count',
            'decision_completeness_score',
            'evidence_coverage_score',
            'caveat_coverage_score',
        ];

        $delta = [];
        foreach ($keys as $key) {
            $numeric = (int) ($society[$key] ?? 0) - (int) ($baseline[$key] ?? 0);
            $suffix = strpos($key, '_score') !== false ? ' pts' : '';
            $delta[$key] = [
                'numeric' => $numeric,
                'display' => ($numeric >= 0 ? '+' : '') . $numeric . $suffix,
            ];
        }

        $delta['unsafe_or_overbroad_action_risk_reduction'] = $this->riskReduction(
            $baseline['unsafe_or_overbroad_action_risk'] ?? 'unknown',
            $society['unsafe_or_overbroad_action_risk'] ?? 'unknown'
        );

        return $delta;
    }

    private function headline(string $baselineMode, array $delta): string
    {
        $label = str_replace('_agent_only', '-only', $baselineMode);
        $domains = (int) ($delta['evidence_domains_used']['numeric'] ?? 0);
        $tensions = (int) ($delta['resolved_material_tensions_detected']['numeric'] ?? 0);
        $actions = (int) ($delta['action_items_count']['numeric'] ?? 0);

        return 'Agent Society used ' . $this->deltaPhrase($domains, 'evidence domains')
            . ', detected ' . $this->deltaPhrase($tensions, 'resolved material tensions')
            . ', and produced ' . $this->deltaPhrase($actions, 'action items')
            . ' than the ' . $label . ' baseline.';
    }

    private function deltaPhrase(int $delta, string $label): string
    {
        if ($delta > 0) {
            return $delta . ' more ' . $label;
        }

        if ($delta < 0) {
            return abs($delta) . ' fewer ' . $label;
        }

        return 'the same number of ' . $label;
    }

    private function scoreSlice(array $metrics): array
    {
        return [
            'decision_completeness_score' => $metrics['decision_completeness_score'] ?? null,
            'evidence_coverage_score' => $metrics['evidence_coverage_score'] ?? null,
            'caveat_coverage_score' => $metrics['caveat_coverage_score'] ?? null,
            'unsafe_or_overbroad_action_risk' => $metrics['unsafe_or_overbroad_action_risk'] ?? null,
        ];
    }

    private function availableDomains(array $metricsContext, array $sourceMetricRefs, array $guardrailPolicy): array
    {
        $domains = [];
        foreach (self::DOMAINS as $domain) {
            $metricKey = $domain === 'forecast' ? 'tomorrow_forecast_metrics' : $domain . '_metrics';
            if (!empty($metricsContext[$metricKey] ?? []) || !empty($sourceMetricRefs[$domain] ?? [])) {
                $domains[] = $domain;
            }
        }
        if (!empty($guardrailPolicy)) {
            $domains[] = 'guardrail';
        }

        return array_values(array_unique($domains));
    }

    private function domainsFromValue($value): array
    {
        $text = strtolower($this->text($value));
        $domains = [];
        foreach (self::DOMAINS as $domain) {
            if (strpos($text, $domain) !== false) {
                $domains[] = $domain;
            }
        }
        if (strpos($text, 'paywall') !== false || strpos($text, 'purchase') !== false) {
            $domains[] = 'monetization';
        }
        if (strpos($text, 'd1') !== false || strpos($text, 'habit') !== false) {
            $domains[] = 'retention';
        }

        return array_values(array_unique($domains));
    }

    private function collectSourceRefs($value): array
    {
        $refs = [];
        $this->walk($value, function ($key, $item) use (&$refs) {
            if (!is_string($item)) {
                return;
            }
            if (strpos($item, 'source_metric_refs') !== false || strpos($item, 'source_metrics_context') !== false || strpos($item, 'metrics_context.') !== false) {
                $refs[] = $item;
            }
        });

        return array_values(array_unique($refs));
    }

    private function refsForDomain(array $sourceMetricRefs, string $domain): array
    {
        if (empty($sourceMetricRefs[$domain])) {
            return [];
        }

        $refs = [];
        $this->walk($sourceMetricRefs[$domain], function ($key, $item) use (&$refs, $domain) {
            if (is_string($item) && $item !== '') {
                $refs[] = 'metrics_context.source_metric_refs.' . $domain . '.' . $key . ':' . $item;
            }
        });

        return array_values(array_unique($refs));
    }

    private function normalizedActionPlan(array $finalDecision): array
    {
        foreach (['normalized_action_plan', 'action_plan', 'action_plan_24_72h', 'operational_action_plan', 'prioritized_actions', 'recommended_actions', 'accepted_recommendations'] as $field) {
            $value = $finalDecision[$field] ?? null;
            if (is_array($value) && !empty($value)) {
                if ($this->isList($value)) {
                    return array_map(function ($item) {
                        return is_array($item) ? $item : ['action' => (string) $item, 'owner_domain' => null];
                    }, $value);
                }

                return [[
                    'action' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'owner_domain' => $value['owner_domain'] ?? null,
                ]];
            }
        }

        return [];
    }

    private function baselineActionItems(string $domain, string $text): int
    {
        $count = $this->keywordCount($text, ['hold', 'reduce', 'test', 'fix', 'improve', 'keep', 'run', 'monitor', 'optimize']);
        if ($count > 0) {
            return min(3, $count);
        }

        return in_array($domain, ['ads', 'monetization', 'activation'], true) ? 1 : 0;
    }

    private function baselineActionDomains(string $domain, string $text): array
    {
        $domains = [$domain];
        foreach (self::DOMAINS as $candidate) {
            if ($candidate !== 'guardrail' && strpos($text, $candidate) !== false) {
                $domains[] = $candidate;
            }
        }

        return array_unique($domains);
    }

    private function baselineCrossDomainConstraints(string $domain, string $text): int
    {
        $count = 0;
        foreach (self::DOMAINS as $candidate) {
            if ($candidate !== $domain && $candidate !== 'guardrail' && strpos($text, $candidate) !== false) {
                $count++;
            }
        }

        return min(3, $count);
    }

    private function societyCrossDomainConstraints(array $structuredNegotiation, array $conflictMatrix): int
    {
        $count = 0;
        $this->walk($structuredNegotiation, function ($key, $item) use (&$count) {
            if ($key === 'constraint_acknowledgement' && is_array($item)) {
                $count += count($item);
            }
        });

        foreach ($conflictMatrix as $conflict) {
            if (!is_array($conflict)) {
                continue;
            }
            $agents = $conflict['agents_involved'] ?? $conflict['involved_agents'] ?? [];
            if (is_array($agents) && count($agents) > 1) {
                $count++;
            }
        }

        return min(10, $count);
    }

    private function countResolvedMaterialTensions(array $conflictMatrix): int
    {
        return count(array_filter($conflictMatrix, function ($conflict) {
            if (!is_array($conflict)) {
                return false;
            }
            $severity = $conflict['severity'] ?? null;
            $status = $conflict['status'] ?? null;
            return $severity === 'material'
                && (($conflict['is_resolved_material_tension'] ?? false) === true || in_array($status, ['resolved_in_round_1', 'bounded_in_round_1'], true));
        }));
    }

    private function countMinorBoundedCautions(array $conflictMatrix): int
    {
        return count(array_filter($conflictMatrix, function ($conflict) {
            if (!is_array($conflict)) {
                return false;
            }
            return ($conflict['severity'] ?? null) === 'minor'
                || ($conflict['type'] ?? null) === 'bounded_tension'
                || ($conflict['conflict_type'] ?? null) === 'bounded_tension';
        }));
    }

    private function countSafetyRevisions(array $structuredNegotiation): int
    {
        $count = 0;
        $this->walk($structuredNegotiation, function ($key, $item) use (&$count) {
            if (is_string($item) && strpos(strtolower($item), 'safety_bounded_revision') !== false) {
                $count++;
            }
        });

        return $count;
    }

    private function guardrailBlocksUsed(array $guardrailPolicy): int
    {
        $triggered = $guardrailPolicy['triggered_guardrails'] ?? [];
        return !empty($triggered) || $this->blockedDecision($guardrailPolicy) ? 1 : 0;
    }

    private function agentUsesGuardrail(string $text, array $guardrailPolicy): bool
    {
        return strpos($text, 'guardrail') !== false || (strpos($text, 'blocked') !== false && $this->blockedDecision($guardrailPolicy));
    }

    private function blockedDecision(array $guardrailPolicy)
    {
        return $guardrailPolicy['blocked_decision']
            ?? ($guardrailPolicy['deterministic_decision']['blocked_decision'] ?? null);
    }

    private function allowedDecision(array $guardrailPolicy)
    {
        return $guardrailPolicy['allowed_decision']
            ?? ($guardrailPolicy['deterministic_decision']['allowed_decision'] ?? null);
    }

    private function baselineSafetyBoundedRevision(string $text): int
    {
        $rejectsUnsafe = $this->containsAny($text, ['not scale aggressively', 'do not scale', 'hold aggressive', 'reject aggressive', 'avoid broad']);
        $preservesSafe = $this->containsAny($text, ['small controlled test', 'controlled test', 'guarded test', 'bounded test']);

        return $rejectsUnsafe && $preservesSafe ? 1 : 0;
    }

    private function riskLevel(string $text, array $guardrailPolicy, int $guardrailBlocks, int $crossDomainConstraints, bool $baseline): string
    {
        $blocked = strtolower((string) $this->blockedDecision($guardrailPolicy));
        $scaleText = $this->containsAny($text, ['scale', 'increase', 'broaden', 'aggressive']);
        $safeText = $this->containsAny($text, ['hold', 'reduce', 'small controlled', 'guarded', 'do not scale', 'reject aggressive']);

        if ($baseline && $scaleText && $blocked !== '') {
            return 'high';
        }
        if (!$baseline && $guardrailBlocks > 0 && $safeText) {
            return 'low';
        }
        if ($safeText) {
            return 'low';
        }
        if ($scaleText || $crossDomainConstraints === 0) {
            return 'medium';
        }

        return 'unknown';
    }

    private function riskReduction(string $baseline, string $society): string
    {
        $rank = ['unknown' => null, 'low' => 1, 'medium' => 2, 'high' => 3];
        if (!isset($rank[$baseline], $rank[$society]) || $rank[$baseline] === null || $rank[$society] === null) {
            return 'unknown';
        }
        if ($rank[$baseline] === 3 && $rank[$society] === 1) {
            return 'strongly_reduced';
        }
        if ($rank[$baseline] > $rank[$society]) {
            return 'reduced';
        }
        if ($rank[$baseline] === 1 && $rank[$society] === 1) {
            return 'already_low';
        }

        return 'not_reduced';
    }

    private function summaryText(array $agent): string
    {
        $result = $agent['result'] ?? $agent;
        foreach (['summary', 'executive_summary', 'diagnosis', 'main_diagnosis', 'generic_diagnosis', 'today_operator_summary', 'rationale', 'impact_on_final_decision'] as $key) {
            if (!empty($result[$key])) {
                return is_string($result[$key]) ? $result[$key] : json_encode($result[$key], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return 'No summary available from selected output.';
    }

    private function decisionPosture(array $agent, string $text): string
    {
        $result = $agent['result'] ?? $agent;
        foreach (['business_verdict', 'agent_society_operating_verdict', 'ads_verdict', 'recommendation', 'generic_recommendation'] as $key) {
            if (!empty($result[$key])) {
                return is_string($result[$key]) ? $result[$key] : json_encode($result[$key], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        if ($this->containsAny($text, ['hold', 'do not scale', 'reject aggressive'])) {
            return 'hold_or_constrain';
        }
        if ($this->containsAny($text, ['test', 'experiment'])) {
            return 'bounded_test';
        }
        if ($this->containsAny($text, ['scale', 'increase'])) {
            return 'scale_or_expand';
        }

        return 'unknown';
    }

    private function keywordCount(string $text, array $needles): int
    {
        $count = 0;
        foreach ($needles as $needle) {
            if (strpos($text, $needle) !== false) {
                $count++;
            }
        }

        return $count;
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (strpos($text, strtolower($needle)) !== false) {
                return true;
            }
        }

        return false;
    }

    private function text($value): string
    {
        if (is_string($value) || is_numeric($value) || is_bool($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    private function walk($value, callable $callback, $key = null): void
    {
        $callback($key, $value);

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $childKey => $childValue) {
            $this->walk($childValue, $callback, $childKey);
        }
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
