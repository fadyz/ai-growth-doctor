<?php

namespace Tests\Unit;

use App\Services\GrowthDoctor\FinalDecisionEnrichmentService;
use PHPUnit\Framework\TestCase;

class FinalDecisionEnrichmentServiceTest extends TestCase
{
    public function test_it_computes_directional_uplift_growth_scores_and_operating_decision_defaults(): void
    {
        $service = new FinalDecisionEnrichmentService();

        $result = $service->enrich([
            'business_verdict' => 'HOLD_AND_OPTIMIZE',
            'top_priority' => 'Fix retention before scaling',
            'main_diagnosis' => 'Session-to-workspace friction is limiting early value.',
            'confidence_score' => 77,
            'operating_decision' => [
                'ads_decision' => ['decision' => 'hold_budget'],
                'release_decision' => ['decision' => 'continue_with_monitoring'],
                'product_decision' => ['decision' => 'prioritize_retention'],
                'monetization_decision' => ['decision' => 'keep_current'],
            ],
        ], [
            'core_metrics' => [
                'activation' => [
                    'session_users' => 1000,
                    'workspace_users' => 300,
                    'food_add_success_users' => 150,
                    'food_add_success_rate_from_session' => 15,
                    'food_add_success_rate_from_workspace' => 50,
                    'paywall_rate_from_food_add_success' => 20,
                ],
                'retention' => [
                    'd1_logged_rate' => 18,
                    'habit_7d_rate' => 12,
                    'avg_log_days_7d' => 1.1,
                ],
                'monetization' => [
                    'purchase_success_users' => 6,
                    'purchase_success_rate_from_paywall' => 5,
                ],
                'version' => [
                    'top_versions' => [
                        [
                            'app_version' => '2.4.1',
                            'session_users' => 700,
                            'food_add_success_rate_from_session' => 35,
                        ],
                        [
                            'app_version' => '2.4.2',
                            'session_users' => 250,
                            'food_add_success_rate_from_session' => 33,
                        ],
                    ],
                    'release_candidate_summary' => 'Better monetization on release candidate, but low sample caveat applies.',
                    'legacy_version_risk_summary' => null,
                ],
            ],
        ]);

        $uplift = $result['business_impact_estimate']['estimated_uplift_if_fixed'];
        $growth = $result['growth_health_score'];
        $productDecision = $result['operating_decision']['product_decision'];

        $this->assertSame(50, $uplift['extra_workspace_users_7d']);
        $this->assertSame(25, $uplift['extra_food_add_success_users_7d']);
        $this->assertSame(5, $uplift['extra_paywall_eligible_users_7d']);
        $this->assertArrayNotHasKey('calculation_status', $result['business_impact_estimate']);
        $this->assertSame('d1_logged_rate', $result['business_impact_estimate']['main_metric_at_risk']);
        $this->assertLessThan(70, $growth['activation_score']);
        $this->assertSame('retention', $growth['main_constraint']);
        $this->assertSame('Prioritize Retention', $productDecision['label']);
        $this->assertSame(85, $productDecision['confidence_score']);
        $this->assertSame('d1_logged_rate', $productDecision['success_metric']);
        $this->assertSame('Hold Budget', $result['operating_decision']['ads_decision']['label']);
        $this->assertSame(75, $result['operating_decision']['ads_decision']['confidence_score']);
    }

    public function test_it_marks_uplift_as_missing_when_required_inputs_are_unavailable(): void
    {
        $service = new FinalDecisionEnrichmentService();

        $result = $service->enrich([
            'business_verdict' => 'HOLD_AND_OPTIMIZE',
        ], [
            'core_metrics' => [
                'activation' => [
                    'session_users' => 500,
                    'workspace_users' => 200,
                    'food_add_success_rate_from_workspace' => null,
                ],
                'retention' => [],
                'monetization' => [
                    'purchase_success_rate_from_paywall' => 4,
                ],
                'version' => [],
            ],
        ]);

        $impact = $result['business_impact_estimate'];

        $this->assertSame('missing_input', $impact['calculation_status']);
        $this->assertNull($impact['estimated_uplift_if_fixed']['extra_workspace_users_7d']);
        $this->assertNull($impact['estimated_uplift_if_fixed']['extra_food_add_success_users_7d']);
        $this->assertNull($impact['estimated_uplift_if_fixed']['extra_paywall_eligible_users_7d']);
    }
}
