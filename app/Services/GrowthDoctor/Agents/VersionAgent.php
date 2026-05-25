<?php

namespace App\Services\GrowthDoctor\Agents;

use App\Services\GrowthDoctor\Agents\AiAgentClient;

class VersionAgent
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
            'AI Version Agent',
            'You are a senior AI Release Risk Analyst for a mobile calorie tracking app. Compare app versions using session users, workspace users, food_add_success, paywall, and purchase. Be careful with small samples, old versions, and mixed rollout periods. Identify which versions are major enough to trust, which versions should be excluded as noisy, which version needs monitoring, and whether rollout should continue, hold, rollback, or need more data. Separate metric facts from hypotheses. Return valid JSON only in Indonesian.',
            [
                'status' => 'safe | caution | risky | insufficient_sample',
                'confidence_score' => '0-100 integer; confidence in the diagnosis',
                'best_version' => 'version name or null',
                'version_under_watch' => 'version name or null',
                'rollout_decision' => 'continue | hold | rollback | need_more_data',
                'diagnosis' => 'version/release diagnosis in Indonesian',
                'trusted_versions' => ['versions with enough user base'],
                'excluded_or_noisy_versions' => ['versions that are too small, too old, or noisy'],
                'metric_facts' => ['fact 1 from provided metrics', 'fact 2 from provided metrics'],
                'hypotheses' => ['hypothesis 1', 'hypothesis 2'],
                'release_risks' => ['risk 1', 'risk 2'],
                'guardrail_metrics' => ['metric to watch 1', 'metric to watch 2'],
                'recommended_experiment' => [
                    'name' => 'short experiment name',
                    'target_version' => 'version name or null',
                    'rollout_rule' => 'what rollout action should be tested',
                    'primary_metric' => 'main metric',
                    'guardrail_metric' => 'guardrail metric',
                    'success_criteria' => 'numeric or directional criteria',
                    'risk' => 'main risk'
                ],
                'recommended_actions' => ['action 1', 'action 2'],
            ],
            [
                'checkpoint_meta' => $context['checkpoint_meta'] ?? [],
                'version_metrics' => $context['version_metrics'] ?? [],
                'activation_metrics' => $context['activation_metrics'] ?? [],
                'monetization_metrics' => $context['monetization_metrics'] ?? [],
            ]
        );
    }
}