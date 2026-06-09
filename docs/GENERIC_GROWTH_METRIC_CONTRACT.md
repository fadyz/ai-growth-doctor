# Generic Growth Metric Contract

The Generic Growth Metric Contract lets AI Growth Doctor analyze different mobile apps through a reusable metric layer.

Hitung Kalori remains the real aggregated case study, but its app-specific source metrics are mapped into generic growth metrics before the Agent Society reads them.

## Why This Contract Exists

Without a generic contract, the system can look like a one-off script for one app. The contract separates:

- source app metrics
- app profile
- source-to-generic metric mapping
- generic growth metric context
- mapping validation
- source metric references

This makes the same Agent Society architecture reusable for another mobile app without changing the core agent workflow.

## Conceptual Flow

```text
Source App Metrics
-> App Profile
-> Metric Mapping
-> Generic Growth Metric Contract
-> Existing AI Growth Doctor Flow
```

## App Profile

`app_profile` describes the product being analyzed.

Default profile location:

```text
config/ai_growth_doctor.php
```

Example:

```json
{
  "tenant_id": "tenant_demo_001",
  "app_id": "hitung_kalori_case_study",
  "app_name": "Hitung Kalori",
  "app_category": "health_fitness",
  "core_action_name": "food logging",
  "core_action_success_label": "food_add_success",
  "workspace_name": "diary workspace",
  "monetization_model": "subscription",
  "timezone": "Asia/Jakarta",
  "data_mode": "real_aggregated_case_study"
}
```

## Metric Mapping

`metric_mapping` maps generic metric keys to source metric paths.

Default mapping location:

```text
config/ai_growth_doctor.php
```

Example:

```json
{
  "activation.entry_users": "activation_metrics.metrics_7d.session_users",
  "activation.core_action_success_users": "activation_metrics.metrics_7d.food_add_success_users",
  "activation.core_action_success_rate_from_entry": "activation_metrics.metrics_7d.food_add_success_rate_from_session",
  "retention.d1_rate": "retention_metrics.metrics_7d_avg.d1_logged_rate",
  "monetization.purchase_success_users": "monetization_metrics.metrics_7d.purchase_success_users",
  "ads.conversion_rate": "ads_metrics.overall.conversion_rate"
}
```

Paths use dot notation. Missing paths do not crash the run; the generic value becomes `null` and validation records the missing metric.

## Generic Metrics Context

`generic_metrics_context` is the normalized view.

Example:

```json
{
  "activation": {
    "entry_users": 2906,
    "workspace_users": 1419,
    "core_action_success_users": 1119,
    "core_action_success_rate_from_entry": 38.51,
    "core_action_success_rate_from_workspace": 78.86
  },
  "retention": {
    "d0_rate": 29.38,
    "d1_rate": 14.99,
    "habit_7d_rate": 13.25,
    "avg_active_days_7d": 0.61
  },
  "monetization": {
    "exposure_users": 193,
    "purchase_start_users": 15,
    "purchase_success_users": 7,
    "purchase_success_rate_from_exposure": 3.63
  },
  "ads": {
    "cost": 3053475.53,
    "clicks": 7512,
    "impressions": 285252,
    "conversions": 2623,
    "cost_per_conversion": 1164.12,
    "conversion_rate": 34.92
  }
}
```

## Mapping Validation

`mapping_validation` summarizes whether the contract is usable.

Example:

```json
{
  "status": "valid_with_warnings",
  "mapped_metric_count": 19,
  "required_metric_count": 8,
  "missing_required_metrics": [],
  "missing_optional_metrics": [],
  "data_quality_warnings": [],
  "low_sample_warnings": [
    "monetization.purchase_success_users below 20"
  ],
  "app_profile_complete": true
}
```

Status rules:

- `invalid`: one or more required metrics are missing.
- `valid_with_warnings`: required metrics exist, but optional metrics or quality warnings exist.
- `valid`: required metrics exist and no warnings are present.

## Source Metric References

`source_metric_refs` preserves app-specific meaning.

Example:

```json
{
  "activation.core_action_success_users": {
    "source_path": "activation_metrics.metrics_7d.food_add_success_users",
    "source_label": "food_add_success_users",
    "app_specific_meaning": "food logging success (food_add_success)",
    "found": true
  }
}
```

Agents should use generic metrics as the primary layer and source refs for audit and app-specific translation.

## Example: Another App

For a workout app:

```json
{
  "app_profile": {
    "app_id": "workout_app_demo",
    "app_name": "Workout Coach",
    "app_category": "health_fitness",
    "core_action_name": "workout completion",
    "core_action_success_label": "workout_completed"
  },
  "metric_mapping": {
    "activation.entry_users": "activation_metrics.metrics_7d.session_users",
    "activation.core_action_success_users": "activation_metrics.metrics_7d.workout_completed_users",
    "activation.core_action_success_rate_from_entry": "activation_metrics.metrics_7d.workout_completed_rate_from_session",
    "retention.d1_rate": "retention_metrics.metrics_7d_avg.d1_active_rate"
  }
}
```

The generic diagnosis can remain:

```text
Core action success from entry users is weak.
```

The app-specific translation becomes:

```text
For Workout Coach, workout completion from session users is weak.
```

## Data Safety

Hitung Kalori is used as a real aggregated case study. Public/demo data should remain aggregated and privacy-safe.

Do not commit:

- raw user-level data
- email
- phone number
- FCM token
- order id
- credential
- access token

The platform operates on metric contracts, not personal data.
