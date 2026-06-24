<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default App Profile
    |--------------------------------------------------------------------------
    |
    | Edit this profile when connecting a different app. Historical run JSON
    | can still carry its own app_profile; that value wins at read time.
    |
    */
    'default_app_profile' => [
        'tenant_id' => env('AGD_TENANT_ID', 'tenant_demo_001'),
        'app_id' => env('AGD_APP_ID', 'hitung_kalori_case_study'),
        'app_name' => env('AGD_APP_NAME', 'Hitung Kalori'),
        'app_category' => env('AGD_APP_CATEGORY', 'health_fitness'),
        'core_action_name' => env('AGD_CORE_ACTION_NAME', 'food logging'),
        'core_action_success_label' => env('AGD_CORE_ACTION_SUCCESS_LABEL', 'food_add_success'),
        'workspace_name' => env('AGD_WORKSPACE_NAME', 'diary workspace'),
        'monetization_model' => env('AGD_MONETIZATION_MODEL', 'subscription'),
        'timezone' => env('AGD_TIMEZONE', 'Asia/Jakarta'),
        'data_mode' => env('AGD_DATA_MODE', 'real_aggregated_case_study'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Metric Mapping
    |--------------------------------------------------------------------------
    |
    | Map generic growth metric keys to paths inside the source metrics context.
    | Source metrics remain available as result.metrics for audit.
    |
    */
    'default_metric_mapping' => [
        'activation.entry_users' => 'activation_metrics.metrics_7d.session_users',
        'activation.workspace_users' => 'activation_metrics.metrics_7d.workspace_users',
        'activation.core_action_success_users' => 'activation_metrics.metrics_7d.food_add_success_users',
        'activation.core_action_success_rate_from_entry' => 'activation_metrics.metrics_7d.food_add_success_rate_from_session',
        'activation.core_action_success_rate_from_workspace' => 'activation_metrics.metrics_7d.food_add_success_rate_from_workspace',

        'retention.d0_rate' => 'retention_metrics.metrics_7d_avg.d0_logged_rate',
        'retention.d1_rate' => 'retention_metrics.metrics_7d_avg.d1_logged_rate',
        'retention.habit_7d_rate' => 'retention_metrics.metrics_7d_avg.habit_7d_rate',
        'retention.avg_active_days_7d' => 'retention_metrics.metrics_7d_avg.avg_log_days_7d',

        'monetization.exposure_users' => 'monetization_metrics.metrics_7d.paywall_view_users',
        'monetization.purchase_start_users' => 'monetization_metrics.metrics_7d.purchase_start_users',
        'monetization.purchase_success_users' => 'monetization_metrics.metrics_7d.purchase_success_users',
        'monetization.purchase_success_rate_from_exposure' => 'monetization_metrics.metrics_7d.purchase_success_rate_from_paywall',

        'ads.cost' => 'ads_metrics.overall.cost',
        'ads.clicks' => 'ads_metrics.overall.clicks',
        'ads.impressions' => 'ads_metrics.overall.impressions',
        'ads.conversions' => 'ads_metrics.overall.conversions',
        'ads.cost_per_conversion' => 'ads_metrics.overall.cost_per_install',
        'ads.conversion_rate' => 'ads_metrics.overall.conversion_rate',
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'openai'),
        'output_language' => env('AI_OUTPUT_LANGUAGE', 'English'),
        'agent_cache_ttl_seconds' => (int) env('AI_AGENT_CACHE_TTL_SECONDS', 1800),
        'agent_timeout_seconds' => (int) env('AI_AGENT_TIMEOUT_SECONDS', 90),
        'tomorrow_forecast_timeout_seconds' => (int) env('AI_TOMORROW_FORECAST_TIMEOUT_SECONDS', 90),
        'tomorrow_forecast_fallback_enabled' => env('AI_TOMORROW_FORECAST_FALLBACK_ENABLED', true),
        'final_decision_context_mode' => env('AI_FINAL_DECISION_CONTEXT_MODE', 'compact'),
        'final_decision_max_payload_kb' => (int) env('AI_FINAL_DECISION_MAX_PAYLOAD_KB', 35),
        'final_decision_timeout_seconds' => (int) env('AI_FINAL_DECISION_TIMEOUT_SECONDS', 60),
        'final_decision_repair_timeout_seconds' => (int) env('AI_FINAL_DECISION_REPAIR_TIMEOUT_SECONDS', 30),
        'final_decision_retry_enabled' => env('AI_FINAL_DECISION_RETRY_ENABLED', true),
        'log_agent_payload_size' => env('AI_LOG_AGENT_PAYLOAD_SIZE', true),
        'log_agent_payload_body' => env('AI_LOG_AGENT_PAYLOAD_BODY', false),
        'show_request_metrics' => env('AI_SHOW_REQUEST_METRICS', false),
    ],
];
