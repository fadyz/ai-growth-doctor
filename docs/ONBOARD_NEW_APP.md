# Onboard a New App

This guide describes how to connect another mobile app to AI Growth Doctor through the Generic Growth Metric Contract.

Do not start by changing prompts or agent logic. Start by defining the app profile and mapping source metrics into the generic contract.

## What to Edit

For the current implementation, define the default app profile and default metric mapping here:

```text
config/ai_growth_doctor.php
```

Do not edit the service classes for normal onboarding. The services read from config:

```text
app/Services/AiGrowthDoctor/AppProfileService.php
app/Services/AiGrowthDoctor/MetricMappingService.php
```

Historical run JSON can still include its own `app_profile` or `metric_mapping`; those values win when reading an existing run.

## 1. Define App Profile

Edit `default_app_profile` in `config/ai_growth_doctor.php`.

Required fields:

- `tenant_id`
- `app_id`
- `app_name`
- `app_category`
- `core_action_name`
- `core_action_success_label`
- `workspace_name`
- `monetization_model`
- `timezone`
- `data_mode`

Example config value:

```php
'default_app_profile' => [
    'tenant_id' => 'tenant_demo_002',
    'app_id' => 'workout_app_demo',
    'app_name' => 'Workout Coach',
    'app_category' => 'health_fitness',
    'core_action_name' => 'workout completion',
    'core_action_success_label' => 'workout_completed',
    'workspace_name' => 'training plan',
    'monetization_model' => 'subscription',
    'timezone' => 'UTC',
    'data_mode' => 'aggregated_metrics',
],
```

You can also override the default profile with environment variables:

```text
AGD_APP_ID
AGD_APP_NAME
AGD_APP_CATEGORY
AGD_CORE_ACTION_NAME
AGD_CORE_ACTION_SUCCESS_LABEL
AGD_WORKSPACE_NAME
AGD_MONETIZATION_MODEL
AGD_TIMEZONE
AGD_DATA_MODE
```

## 2. Identify the Core Action

The core action is the behavior that proves the user reached value.

Examples:

- calorie tracker: food logging
- workout app: workout completion
- language app: lesson completion
- finance app: first budget created
- habit app: habit checked in

Map this to:

```text
activation.core_action_success_users
activation.core_action_success_rate_from_entry
activation.core_action_success_rate_from_workspace
```

## 3. Identify Source Metric Paths

List source metric paths in the app's checkpoint or metrics context.

Example:

```text
activation_metrics.metrics_7d.session_users
activation_metrics.metrics_7d.workout_completed_users
retention_metrics.metrics_7d_avg.d1_active_rate
monetization_metrics.metrics_7d.purchase_success_users
```

## 4. Map Source Metrics to Generic Contract

Edit `default_metric_mapping` in `config/ai_growth_doctor.php`.

Example config value:

```php
'default_metric_mapping' => [
    'activation.entry_users' => 'activation_metrics.metrics_7d.session_users',
    'activation.core_action_success_users' => 'activation_metrics.metrics_7d.workout_completed_users',
    'activation.core_action_success_rate_from_entry' => 'activation_metrics.metrics_7d.workout_completed_rate_from_session',
    'retention.d0_rate' => 'retention_metrics.metrics_7d_avg.d0_active_rate',
    'retention.d1_rate' => 'retention_metrics.metrics_7d_avg.d1_active_rate',
    'retention.habit_7d_rate' => 'retention_metrics.metrics_7d_avg.habit_7d_rate',
    'monetization.exposure_users' => 'monetization_metrics.metrics_7d.paywall_view_users',
    'monetization.purchase_success_users' => 'monetization_metrics.metrics_7d.purchase_success_users',
],
```

## 5. Validate Mapping

Mapping validation checks:

- required metrics
- optional metrics
- app profile completeness
- low sample warnings
- data quality warnings

Required metrics:

- `activation.entry_users`
- `activation.core_action_success_users`
- `activation.core_action_success_rate_from_entry`
- `retention.d0_rate`
- `retention.d1_rate`
- `retention.habit_7d_rate`
- `monetization.exposure_users`
- `monetization.purchase_success_users`

## 6. Run AI Growth Doctor

After mapping is valid or valid with warnings, run the existing AI Growth Doctor flow.

The run result should contain:

- `result.app_profile`
- `result.metric_mapping`
- `result.generic_metrics_context`
- `result.mapping_validation`
- `result.source_metric_refs`
- `result.metrics`

`result.metrics` remains the source/app-specific metrics for audit.

## 7. Review Agent Society Output

Review:

- Guardrail & Safe Context
- Specialist Agent cards
- Structured Negotiation
- Conflict Matrix
- Final Decision Agent
- Decision Scenario Simulator
- Agent Society Graph

The graph page is:

```text
/ai-growth-doctor/runs/{runId}/graph-view
```

Click **App Data Mapping** in the graph to inspect:

- app profile
- metric mapping
- mapping validation
- generic metrics preview
- source metric references

## Data Safety

Only use aggregated metrics.

Do not commit:

- raw user-level data
- email
- phone number
- FCM token
- order id
- credential
- access token

The platform operates on metric contracts and aggregate business signals, not personal data.
