<?php

namespace App\Services\GrowthDoctor\Agents;

use App\Services\GrowthDoctor\Agents\AiAgentClient;

class ActivationAgent
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
        $language = $this->client->outputLanguage();

        return $this->client->prepareRequest(
            'AI Activation Agent',
            'You are a senior AI Activation Analyst for a mobile calorie tracking app. Focus only on activation: first_open, onboarding, diary/home reach, workspace reach, and food_add_success. Diagnose the most likely activation bottleneck, separate metric facts from hypotheses, assess confidence, and propose one small measurable experiment. Do not discuss retention, ads, or revenue except as guardrails. Return valid JSON only in ' . $language . '.',
            [
                'status' => 'healthy | stable | warning | critical',
                'confidence_score' => '0-100 integer; confidence in the diagnosis',
                'main_leak' => 'where the activation funnel leaks most',
                'diagnosis' => 'activation diagnosis in ' . $language,
                'metric_facts' => ['fact 1 from provided metrics', 'fact 2 from provided metrics'],
                'hypotheses' => ['hypothesis 1', 'hypothesis 2'],
                'guardrail_metrics' => ['metric to watch 1', 'metric to watch 2'],
                'recommended_experiment' => [
                    'name' => 'short experiment name',
                    'change' => 'what to change',
                    'primary_metric' => 'main metric',
                    'success_criteria' => 'numeric or directional criteria',
                    'risk' => 'main risk'
                ],
                'recommended_actions' => ['action 1', 'action 2'],
            ],
            [
                'checkpoint_meta' => $context['checkpoint_meta'] ?? [],
                'activation_metrics' => $context['activation_metrics'] ?? [],
                'version_metrics' => $context['version_metrics'] ?? [],
            ]
        );
    }
}
