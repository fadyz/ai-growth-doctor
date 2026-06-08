<?php

namespace App\Services\GrowthDoctor\Agents;

use App\Services\GrowthDoctor\Agents\AiAgentClient;

class RetentionAgent
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
            'AI Retention Agent',
            'You are a senior AI Retention Analyst for a mobile calorie tracking app. Focus only on D0, D1, 7-day habit, avg log days, and early habit formation. Distinguish weak same-day activation from weak next-day habit. Assess whether D0 improvement is converting into D1 habit. Separate metric facts from hypotheses, assess confidence, and propose one small measurable retention experiment. Do not discuss monetization except as a possible risk. Return valid JSON only in ' . $language . '.',
            [
                'status' => 'healthy | stable | warning | critical',
                'confidence_score' => '0-100 integer; confidence in the diagnosis',
                'habit_risk' => 'low | medium | high',
                'diagnosis' => 'retention diagnosis in ' . $language,
                'metric_facts' => ['fact 1 from provided metrics', 'fact 2 from provided metrics'],
                'd0_to_d1_interpretation' => 'whether D0 action is translating into D1 habit',
                'hypotheses' => ['hypothesis 1', 'hypothesis 2'],
                'guardrail_metrics' => ['metric to watch 1', 'metric to watch 2'],
                'recommended_experiment' => [
                    'name' => 'short experiment name',
                    'target_segment' => 'segment to target',
                    'change' => 'what to change',
                    'primary_metric' => 'main metric',
                    'success_criteria' => 'numeric or directional criteria',
                    'risk' => 'main risk'
                ],
                'recommended_actions' => ['action 1', 'action 2'],
            ],
            [
                'checkpoint_meta' => $context['checkpoint_meta'] ?? [],
                'retention_metrics' => $context['retention_metrics'] ?? [],
            ]
        );
    }
}
