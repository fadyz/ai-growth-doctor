<?php

namespace App\Services\GrowthDoctor\Agents;

use App\Services\GrowthDoctor\Agents\AiAgentClient;

class MonetizationAgent
{
    private $client;

    public function __construct(AiAgentClient $client)
    {
        $this->client = $client;
    }

    public function run(array $context): array
    {
        $request = $this->buildRequest($context);

        return $this->client->call(
            $request['agent_name'],
            $request['system_prompt'],
            $request['expected_schema'],
            $request['agent_context']
        );
    }

    public function buildRequest(array $context): array
    {
        return $this->client->prepareRequest(
            'AI Monetization Agent',
            'You are a senior AI Monetization Analyst for a mobile calorie tracking app. Focus on paywall_view, purchase_start, purchase_success, purchase rate, revenue opportunity, and the risk that monetization appears too early before users experience core value. Separate metric facts from hypotheses. Separate revenue upside from activation risk. Assess confidence and propose one small measurable monetization experiment. Return valid JSON only in Indonesian.',
            [
                'status' => 'healthy | active_signal | noisy | warning | risk',
                'confidence_score' => '0-100 integer; confidence in the diagnosis',
                'revenue_signal' => 'short monetization signal',
                'activation_risk' => 'low | medium | high',
                'diagnosis' => 'monetization diagnosis in Indonesian',
                'metric_facts' => ['fact 1 from provided metrics', 'fact 2 from provided metrics'],
                'opportunities' => ['opportunity 1', 'opportunity 2'],
                'risks' => ['risk 1', 'risk 2'],
                'hypotheses' => ['hypothesis 1', 'hypothesis 2'],
                'guardrail_metrics' => ['metric to watch 1', 'metric to watch 2'],
                'recommended_experiment' => [
                    'name' => 'short experiment name',
                    'target_segment' => 'segment to target',
                    'trigger_rule' => 'when paywall/promo should appear',
                    'primary_metric' => 'main metric',
                    'guardrail_metric' => 'guardrail metric',
                    'success_criteria' => 'numeric or directional criteria',
                    'risk' => 'main risk'
                ],
                'recommended_actions' => ['action 1', 'action 2'],
            ],
            [
                'checkpoint_meta' => $context['checkpoint_meta'] ?? [],
                'monetization_metrics' => $context['monetization_metrics'] ?? [],
                'activation_metrics' => $context['activation_metrics'] ?? [],
                'version_metrics' => $context['version_metrics'] ?? [],
            ]
        );
    }
}