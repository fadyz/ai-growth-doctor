# Agent Workflow

This document describes the AI Growth Doctor Agent Society workflow: what each component reads, what it produces, and how the system turns specialist evidence into a daily operating decision.

AI Growth Doctor does not ask one AI agent to answer every growth question at once. It decomposes the problem into deterministic preparation, specialist analysis, structured negotiation, final synthesis, and scenario simulation.

## Workflow Summary

```text
Checkpoint Data
-> Metrics Extractor
-> App Data Mapping
-> Forecast Evaluation
-> Forecast Calibration
-> Guardrail / Safe Context
-> Specialist Agents
   - Activation Agent
   - Retention Agent
   - Monetization Agent
   - Version Agent
   - Ads Agent
   - Tomorrow Forecast Agent
-> Adaptive Structured Negotiation
-> Orchestrator Evidence Assembly
-> Final Decision Agent
-> Decision Scenario Simulator
-> Human Operator
```

## Core Principles

### 1. Metrics First, AI Second

Primary metrics are computed deterministically from actual input data. Agents may interpret evidence, but they must not invent numbers.

### 2. Specialist Agents Are Isolated But Bounded

Each specialist reads a focused domain plus bounded safe context from the metrics, mapping, guardrail, forecast, and calibration layers. Ads, monetization, retention, activation, version, and forecast each get their own analysis before final synthesis, but they are not blind silos.

Each specialist summary should expose:

- `domain_only_position`
- `bounded_system_position`
- `constraint_acknowledgement`
- `negotiation_need`
- `residual_conflict`
- `why_no_further_negotiation_needed`

### 3. Guardrail Before Action

Recommendations are checked against deterministic policy. A specialist can suggest an action, but guardrails can block it.

### 4. Adaptive Structured Negotiation Before Final Decision

Specialist outputs pass through adaptive structured negotiation. The system supports up to three structured rounds, but exits early when no unresolved material or critical conflict remains.

The negotiation layer records hard conflicts separately from bounded soft operating tensions. A soft tension can be visible and valuable without forcing Round 2.

### 5. Final Synthesis Is Not Voting

The Final Decision Agent does not count votes. It synthesizes metrics, specialist outputs, guardrails, conflicts, forecast evaluation, and calibration.

### 6. Human-in-the-Loop

The system recommends and explains. The human operator remains the final decision maker.

## 1. Metrics Extractor

### Purpose

The Metrics Extractor builds `metrics_context`, the factual source of truth for the run.

### Input

Possible input includes:

- activation daily data
- retention daily data
- monetization events
- app version performance
- ads campaign reports
- forecast history
- forecast evaluation history
- calibration memory

### Output

Example:

```json
{
  "activation_metrics": {
    "session_users": 1200,
    "workspace_users": 640,
    "food_add_success_users": 510
  },
  "retention_metrics": {
    "d0_logged_rate": 34.2,
    "d1_logged_rate": 16.8,
    "habit_7d_rate": 18.5
  },
  "monetization_metrics": {
    "paywall_view_users": 90,
    "purchase_success_users": 4
  }
}
```

### Notes

This is a deterministic layer. If a metric is missing, immature, or low sample, that condition should be preserved instead of hidden.

## 2. App Data Mapping

### Purpose

App Data Mapping converts app-specific source metrics into the Generic Growth Metric Contract before guardrails and agents read the run.

### Produces

- `app_profile`
- `metric_mapping`
- `generic_metrics_context`
- `mapping_validation`
- `source_metric_refs`

### Example

```text
activation_metrics.metrics_7d.food_add_success_users
-> activation.core_action_success_users
```

### Principle

Agents should use `generic_metrics_context` as the primary growth metric layer and `source_metric_refs` for app-specific translation.

## 3. Forecast Evaluation

### Purpose

Forecast Evaluation compares previous forecasts against actual mature data.

### Output States

Possible metric states:

- `hit`
- `miss_low`
- `miss_high`
- `pending_maturity`
- `pending_actual_data`
- `missing_actual_metric`
- `invalid_forecast_metric`

### Why It Matters

Forecasts should not influence the final decision as if they are facts unless they have enough maturity and calibration support.

## 4. Forecast Calibration

### Purpose

Forecast Calibration updates the trust level of the forecasting system.

### Output

Examples:

- trust score
- trust interpretation
- forecast role
- weak metrics to treat carefully
- group accuracy snapshot

### Decision Rule

If forecast trust is low, the forecast should be treated as a directional signal, not as a deterministic decision owner.

## 5. Guardrail / Safe Context

### Purpose

Guardrail / Safe Context defines the deterministic operating boundaries for the Agent Society.

### Guardrails

Examples:

- data quality guardrail
- retention guardrail
- activation guardrail
- forecast guardrail
- ads acquisition guardrail
- monetization guardrail
- release guardrail

### Output

Example:

```json
{
  "winning_guardrail": "retention_guardrail",
  "triggered_guardrails": {
    "retention_guardrail": {
      "triggered": true,
      "severity": "high",
      "blocked_actions": [
        "aggressive_ads_scale",
        "increase_paywall_pressure"
      ],
      "allowed_actions": [
        "prioritize_retention",
        "small_controlled_test_only"
      ]
    }
  },
  "deterministic_decision": {
    "business_verdict": "HOLD_AND_OPTIMIZE"
  }
}
```

### Principle

Guardrails do not replace the final decision. They define what the final decision must respect.

## 6. Activation Agent

### Purpose

Activation Agent checks whether users reach the core value action.

For the calorie tracking app, the key core action is successful food logging, represented by `food_add_success`.

### Reads

- `session_users`
- `workspace_users`
- `food_add_success_users`
- `food_add_success_rate_from_session`
- `food_add_success_rate_from_workspace`
- version context if relevant
- guardrail context

### Questions Answered

- Are users reaching the workspace?
- Are workspace users successfully adding food?
- Is the bottleneck before workspace or inside the food-add flow?
- Is activation healthy enough to support more acquisition?

### Output

Example:

```json
{
  "status": "warning",
  "confidence_score": 72,
  "diagnosis": "Activation is stable, but the largest drop is between workspace entry and successful food add.",
  "recommended_actions": [
    "Audit the food-add funnel.",
    "Verify success-event instrumentation."
  ]
}
```

### Boundary

Activation Agent may mention paywall or retention as context, but its primary diagnosis should stay focused on activation.

## 7. Retention Agent

### Purpose

Retention Agent checks whether users return and begin forming a habit.

### Reads

- `d0_logged_rate`
- `d1_logged_rate`
- `habit_7d_rate`
- `avg_log_days_7d`
- metric maturity
- cohort windows
- guardrail context

### Questions Answered

- Do D0 active users return on D1?
- Is 7-day habit formation improving?
- Are retention metrics mature enough to read?
- Is retention strong enough to support acquisition scaling?

### Output

Example:

```json
{
  "status": "warning",
  "habit_risk": "high",
  "diagnosis": "D0 activity is not converting into a stable D1 habit.",
  "recommended_actions": [
    "Launch a D1 quick-log nudge.",
    "Track D0-to-D1 cohort movement."
  ]
}
```

### Maturity Notes

Retention metrics may not be readable immediately.

```text
D1 logged rate requires next-day actual data.
Habit 7D requires a mature 7-day cohort window.
```

The system should distinguish mature data from pending maturity.

## 8. Monetization Agent

### Purpose

Monetization Agent checks whether paywall and purchase signals are healthy.

### Reads

- `paywall_view_users`
- `purchase_start_users`
- `purchase_success_users`
- `paywall_rate_from_food_add_success`
- `purchase_success_rate_from_paywall`
- activation context
- retention context
- guardrail context

### Questions Answered

- Is the paywall shown too early?
- Have users experienced enough core value before monetization pressure?
- Is purchase volume meaningful or low sample?
- Should monetization be optimized, held, or segmented?

### Output

Example:

```json
{
  "status": "active_signal",
  "confidence_score": 72,
  "diagnosis": "Purchases exist, but purchase volume is small and paywall conversion remains noisy.",
  "blocked_action_awareness": [
    "increase_paywall_pressure"
  ]
}
```

### Boundary

Small purchase counts must not create overconfidence. Monetization pressure should not override activation or retention guardrails.

## 9. Version Agent

### Purpose

Version Agent checks app version quality and release comparability.

### Reads

- version metrics
- top versions
- release candidate versions
- activation by version
- monetization by version
- sample size
- instrumentation compatibility

### Questions Answered

- Does the current version show regression?
- Is a new version healthy enough to continue rollout?
- Are old versions still comparable?
- Is the sample size large enough to trust?

### Output

Example:

```json
{
  "status": "caution",
  "rollout_decision": "need_more_data",
  "diagnosis": "The newest version has promising monetization but a smaller sample, so it should not be expanded aggressively yet."
}
```

### Boundary

Legacy versions with incompatible instrumentation should be treated as context only. They should not automatically veto a modern rollout.

## 10. Ads Agent

### Purpose

Ads Agent checks acquisition quality and campaign lifecycle.

### Reads

- campaign cost
- clicks
- impressions
- conversions
- cost per install
- conversion rate
- recent vs previous movement
- campaign lifecycle context
- activation context
- retention context
- guardrail context
- `deterministic_lifecycle_context`
- `ads_metric_independent_assessment`
- `field_resolution_rule`

### Questions Answered

- Which campaign is healthy?
- Is a legacy campaign degraded?
- Is a reset successor campaign worth a controlled test?
- Is it safe to scale budget?
- Does downstream product quality support acquisition growth?

### Output

Example:

```json
{
  "ads_verdict": "evaluate_reset_successor",
  "campaign_health": "mixed",
  "confidence_score": 62,
  "deterministic_lifecycle_context": {
    "interpretation_winner": "lifecycle_context_wins_campaign_identity",
    "legacy_campaign": {
      "lifecycle_status": "degraded_legacy"
    },
    "reset_successor_campaign": {
      "lifecycle_status": "reset_successor"
    }
  },
  "ads_metric_independent_assessment": {
    "budget_intensity_supported_by_metrics": "cautious_test",
    "metric_winner_for_budget_intensity": "ads_metrics_win_budget_intensity"
  },
  "field_resolution_rule": {
    "lifecycle_wins_for": "campaign identity and interpretation",
    "metrics_win_for": "budget intensity",
    "downstream_guardrails_win_for": "safety limits"
  },
  "impact_on_final_decision": "Keep acquisition constrained to a small controlled test."
}
```

### Boundary

Ads Agent must not judge acquisition only from CPI or conversion rate. Downstream activation and retention quality must constrain its recommendation.

Ads lifecycle and ads performance are intentionally separated:

- `deterministic_lifecycle_context` decides campaign identity and interpretation. For example, `Volume Stabil` can be treated as degraded legacy and `Volume Install Reset` can be treated as reset successor.
- `ads_metric_independent_assessment` decides budget intensity from actual ads metrics such as CPI, conversion rate, conversion volume, spend movement, and sample quality.
- downstream activation, retention, and guardrails cap safety. Positive ads metrics can still be limited to a small controlled test.

The reset successor label does not prove the reset campaign is working. It only makes that campaign the valid candidate to evaluate.

## 11. Tomorrow Forecast Agent

### Purpose

Tomorrow Forecast Agent interprets deterministic forecast output as a risk signal.

### Reads

- `tomorrow_forecast_metrics`
- predicted activation
- predicted retention
- predicted monetization
- risk flags
- forecast calibration context
- guardrail context

### Questions Answered

- What risks are expected tomorrow?
- Is activation forecast stable?
- Is retention or habit at risk?
- Is the forecast trusted enough to influence action?
- Should forecast strengthen a guardrail or remain directional?

### Output

Example:

```json
{
  "prediction_status": "watch",
  "confidence_score": 62,
  "summary": "Forecast is directionally mixed. Retention and habit should be watched, but low calibration means forecast should not override mature actual metrics."
}
```

### Boundary

Forecast cannot become a hard veto when:

```text
forecast_role = directional_signal_only
trust_score is low
```

## 12. Adaptive Structured Negotiation

### Purpose

Structured Negotiation creates a deterministic bounded cross-examination step after specialist outputs.

It is designed to answer:

- Did any specialist recommendation conflict with another domain?
- Did any recommendation violate deterministic guardrails?
- Did any agent revise its recommendation after seeing cross-domain evidence?
- Did any agent make a safety-bounded partial concession?
- Are there bounded soft operating tensions that should be visible even though no hard conflict remains?
- What would a single-agent baseline have missed?

### Rules

```text
max_rounds = 3
early_exit_enabled = true
raw_chain_of_thought_allowed = false
evidence_required_for_objection = true
evidence_bound_objections = true
no_free_form_debate = true
final_decision_owner = FinalDecisionAgent
```

Round 2 and Round 3 are not forced. If Round 1 leaves no unresolved material or critical conflict, negotiation exits early. This is intended behavior, not avoided debate.

### Output Sections

- rules
- specialist output summaries
- agent responses
- negotiation timeline
- conflict matrix
- bounded tensions
- revised recommendations
- baseline comparison
- decision package
- summary

### Turn-Level Schema

Negotiation turns include the specialist bounded-awareness fields:

```json
{
  "response_type": "soft_operating_constraint",
  "severity": "minor",
  "domain_only_position": "Session-to-core-action conversion is the main activation bottleneck.",
  "bounded_system_position": "Activation should constrain acquisition scaling until the first-core-action path improves.",
  "constraint_acknowledgement": [
    "session_to_core_action_gap",
    "ads_scaling_risk"
  ],
  "response_to_challenge": "warn",
  "concession_type": "none",
  "conflict_after_response": "bounded",
  "residual_conflict_severity": "minor"
}
```

### Example Conflict

```json
{
  "conflict_id": "conflict_ads_scale_vs_retention",
  "topic": "Should acquisition budget scale today?",
  "agents_involved": [
    "Ads Agent",
    "Retention Agent",
    "Activation Agent"
  ],
  "severity": "material",
  "resolution_candidate": "Keep budget stable, test higher-intent creative, and avoid aggressive scaling."
}
```

### Example Bounded Tension

```json
{
  "conflict_id": "tension_ads_efficiency_vs_activation_retention_constraints",
  "type": "bounded_tension",
  "title": "Ads efficiency vs activation/retention constraints",
  "severity": "minor",
  "status": "resolved_in_round_1",
  "resolution_mode": "safety_bounded_partial_concession",
  "domain_only_tension": "Ads efficiency supports monitoring the reset successor campaign and possibly running a cautious controlled test.",
  "bounded_system_resolution": "Reject aggressive scaling; preserve only cautious controlled testing with stable budget and downstream quality checks.",
  "is_unresolved_material_conflict": false
}
```

### Early Exit Semantics

`total_conflict_count` means unresolved material or critical conflicts only. Bounded soft tensions are counted separately with fields such as:

- `bounded_tension_count`
- `resolved_bounded_tension_count`
- `partial_concession_count`
- `safety_bounded_revision_count`
- `soft_operating_constraint_count`

Round 1 can complete with `total_conflict_count = 0` and `bounded_tension_count > 0`.

## 13. Orchestrator Evidence Assembly

### Purpose

The orchestrator assembles the evidence package passed to the Final Decision Agent.

### Includes

- deterministic metrics
- guardrail policy
- specialist outputs
- structured negotiation result
- conflict matrix
- forecast evaluation
- calibration memory
- interaction log references

### Principle

The final agent should receive a conflict-aware decision package, not a raw pile of unrelated agent outputs.

## 14. Final Decision Agent

### Purpose

Final Decision Agent turns the evidence package into one operating decision.

### Reads

- metrics context
- specialist summaries
- guardrail policy
- structured negotiation
- conflict matrix
- forecast evaluation
- forecast calibration
- ads lifecycle context
- version risk context

### Output

Example:

```json
{
  "business_verdict": "HOLD_AND_OPTIMIZE",
  "confidence_score": 84,
  "today_operator_summary": "Do not scale acquisition or increase paywall pressure today. Prioritize retention and run only a small controlled reset-campaign evaluation.",
  "action_plan_24_72h": [
    "Launch a D1 quick-log nudge.",
    "Instrument the first-food-add bottleneck.",
    "Keep acquisition budget capped."
  ]
}
```

### Must Include

- business verdict
- confidence score
- rationale
- accepted recommendations
- rejected recommendations
- resolved conflicts
- 24 to 72 hour action plan
- risk notes
- monitoring plan
- weak evidence or uncertainty

### Boundary

Final Decision Agent may be more conservative than a specialist, but it should not violate deterministic blocked actions.

## 15. Decision Scenario Simulator

### Purpose

Decision Scenario Simulator compares doing nothing against the recommended action scenario.

### Reads

- final decision
- tomorrow forecast metrics
- guardrail policy
- structured negotiation
- specialist summaries

### Output Sections

- baseline without intervention
- recommended intervention
- scenario with intervention
- baseline vs intervention comparison
- evidence basis
- risk
- success criteria
- human review note

### Boundary

The simulator should support human review. It should not claim exact uplift or revenue impact without experiment evidence.

## 16. Interaction Log

### Purpose

The interaction log makes the run auditable.

### Records

- agent request
- agent response
- source key
- execution mode
- cache hit
- request start and finish time
- duration
- summary
- final decision trace
- guardrail trace

### Questions It Answers

- Which agent produced which signal?
- Did agents run in parallel?
- Was a result cached?
- Which guardrail was active?
- Why did the final decision choose a specific verdict?

## 17. Graph Visualizer

### Purpose

The Agent Society graph visualizer reads completed run JSON and renders the workflow as an interactive React Flow graph.

### Shows

- Metrics Extraction
- App Data Mapping
- Guardrail & Safe Context
- Specialist Agents
- Adaptive Structured Negotiation
- Orchestrator Evidence Assembly
- Final Decision Agent
- Decision Scenario Simulator

### Detail Panel

Clicking nodes shows structured details:

- guardrail details
- agent evidence and recommendations
- negotiation rules, timeline, hard conflicts, bounded soft tensions, and baseline comparison
- final decision and action plan
- scenario simulator summary

### Routes

```text
GET /ai-growth-doctor/runs/{runId}/graph
GET /ai-growth-doctor/runs/{runId}/graph-view
```

### Safety

The graph visualizer does not modify historical run JSON. It does not display raw chain-of-thought.

## 18. Data Readiness Mode

If tracking is incomplete, the system should not force a confident diagnosis.

Examples:

```text
If D1 retention is unavailable, the system must not claim retention is healthy.
If purchase sample is tiny, monetization must be labeled low-sample or noisy.
If forecast has not been evaluated, forecast must be directional only.
```

Correct behavior:

```json
{
  "data_readiness": "insufficient",
  "missing_metrics": [
    "d1_logged_rate",
    "purchase_success_users"
  ],
  "blocked_actions": [
    "scale_budget_aggressively"
  ],
  "recommended_next_step": "Install missing tracking before making aggressive growth decisions."
}
```

## 19. End-to-End Example

Example daily run:

```text
1. Metrics Extractor reads checkpoint data.
2. App Data Mapping maps food_add_success into core_action_success.
3. Forecast Evaluation checks prior forecast accuracy.
4. Forecast Calibration marks forecast as low-trust directional evidence.
5. Guardrail Policy triggers retention_guardrail.
6. Activation Agent finds first-food-add friction.
7. Retention Agent finds weak D0-to-D1 habit continuity.
8. Monetization Agent finds purchase signal but low sample.
9. Version Agent marks newest version as promising but noisy.
10. Ads Agent separates reset-campaign lifecycle context from independent ads metric assessment, then recommends only bounded testing.
11. Tomorrow Forecast Agent warns that retention and habit remain at risk.
12. Structured Negotiation records bounded ads-vs-retention tension and exits early if no unresolved material conflict remains.
13. Orchestrator builds the conflict-aware decision package.
14. Final Decision Agent chooses HOLD_AND_OPTIMIZE.
15. Scenario Simulator compares baseline vs retention-first action plan.
16. Human operator reviews dashboard, graph, and action plan.
```

Example verdict:

```text
HOLD_AND_OPTIMIZE
Do not scale acquisition or increase paywall pressure today. Prioritize retention, fix the activation bottleneck, and run only a small controlled reset-campaign evaluation.
```

## 20. Why Agent Society?

Growth decisions are cross-domain.

Ads can look efficient while retention is weak. Monetization can show a signal while activation quality is fragile. A new version can improve purchase conversion while sample size is too small. A forecast can warn of risk while calibration says it is low-trust.

The Agent Society pattern gives each domain a focused voice, then uses structured negotiation and deterministic guardrails to prevent one incomplete perspective from dominating the final decision.

## 21. Summary

AI Growth Doctor workflow is:

```text
Deterministic metrics
-> App Data Mapping
-> Guardrail / Safe Context
-> Specialist Agent Society
-> Adaptive Structured Negotiation
-> Evidence Assembly
-> Final Decision
-> Scenario Simulation
-> Human Operator
```

The system is built to make daily growth decisions faster, more consistent, safer, and easier to audit without removing human judgment.
