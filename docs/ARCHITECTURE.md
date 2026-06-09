# Architecture

This document describes the current AI Growth Doctor architecture and how it supports the Agent Society workflow.

AI Growth Doctor combines deterministic metric computation, specialist AI agents, forecast evaluation, calibration memory, guardrail policy, structured negotiation, final decision synthesis, scenario simulation, and a graph visualizer for auditability.

## System Goals

- Convert daily growth data into an evidence-backed operating decision.
- Separate deterministic facts from AI interpretation.
- Let specialist agents analyze different domains independently.
- Detect cross-domain conflicts before the final recommendation.
- Apply deterministic guardrails before aggressive actions can be recommended.
- Keep humans as the final decision owners.
- Make every run inspectable through JSON, dashboard cards, interaction logs, and the Agent Society graph.

## High-Level Flow

```text
Checkpoint Data
-> Metrics Extraction
-> Forecast Evaluation
-> Forecast Calibration Memory
-> Guardrail / Safe Context
-> Specialist Agents
-> Single-Round Structured Negotiation
-> Orchestrator Evidence Assembly
-> Final Decision Agent
-> Decision Scenario Simulator
-> Dashboard / Graph / Human Operator
```

The graph visualizer renders the same run as a horizontal Agent Society pipeline:

```text
Checkpoint Load
-> Metrics Extraction
-> Guardrail & Safe Context
-> Specialist Agents fan-out
-> Single-Round Structured Negotiation
-> Orchestrator Evidence Assembly
-> Final Decision Agent
-> Decision Scenario Simulator
```

## Runtime Components

### Laravel Backend

Laravel owns routes, controllers, services, run persistence, and Blade page shells.

Key responsibilities:

- Load checkpoint data.
- Run deterministic services.
- Call specialist agents.
- Store async run progress.
- Persist completed run JSON.
- Serve dashboard and graph pages.
- Serve graph JSON derived from existing run files.

### Deterministic Services

Deterministic services compute facts and constraints before AI synthesis.

Examples:

- `MetricsExtractor`
- `ForecastEvaluationService`
- `ForecastCalibrationService`
- `GuardrailPolicyEngine`
- `StructuredNegotiationService`
- `RunProgressStore`

These services should be reproducible for the same input data.

### AI Agent Layer

Specialist agents are invoked through the agent orchestration layer. They read a compact context and return structured outputs.

Specialists:

- Activation Agent
- Retention Agent
- Monetization Agent
- Version Agent
- Ads Agent
- Tomorrow Forecast Agent

Final synthesis:

- Final Decision Agent
- Decision Scenario Simulator

### React Graph Island

The graph visualizer is a React island mounted into a Blade page. It does not turn the Laravel app into a single-page application.

Blade root:

```html
<div id="agd-graph-root" data-run-id="..." data-graph-url="..."></div>
```

Frontend stack:

- Vite
- React
- React Flow / `@xyflow/react`
- `html-to-image` for PNG export

Backend graph service:

```text
app/Services/AiGrowthDoctor/AiGrowthDoctorGraphBuilder.php
```

Graph routes:

```text
GET /ai-growth-doctor/runs/{runId}/graph
GET /ai-growth-doctor/runs/{runId}/graph-view
```

## Data Layer

Input data starts as checkpoint or daily growth data. It can include:

- activation daily data
- retention daily data
- monetization events
- app version performance
- ads campaign reports
- forecast history
- evaluation history
- calibration memory

Sensitive data should not be committed to a public repository.

Completed runs are stored as JSON:

```text
storage/app/ai-growth-doctor/runs/{runId}.json
```

Run JSON is treated as immutable historical evidence. The graph visualizer only reads it.

## Metrics Extraction

`MetricsExtractor` computes the factual metrics used by agents and guardrails.

Examples:

- session users
- workspace users
- food add success users
- food add success rate
- D0 logged rate
- D1 logged rate
- 7-day habit rate
- average log days in 7 days
- paywall view users
- purchase success users
- purchase success rate
- campaign cost per install
- campaign conversion rate

These numbers are calculated from input data. Agents should not invent them.

## Forecast Evaluation and Calibration

`ForecastEvaluationService` compares previous forecasts against actual mature data.

It distinguishes:

- `hit`
- `miss_low`
- `miss_high`
- `pending_maturity`
- `pending_actual_data`
- `missing_actual_metric`
- `invalid_forecast_metric`

`ForecastCalibrationService` uses forecast evaluation results to update trust.

Calibration can decide that forecast output should be:

- directional only
- supporting evidence
- strong supporting evidence
- eligible to strengthen a guardrail

Low-trust forecasts must not override mature actual metrics.

## Guardrail Policy Engine

`GuardrailPolicyEngine` is a deterministic safety layer.

Examples of guardrails:

- data quality guardrail
- retention guardrail
- activation guardrail
- forecast guardrail
- ads acquisition guardrail
- monetization guardrail
- release guardrail

Guardrails can produce:

- triggered guardrails
- winning guardrail
- blocked actions
- allowed actions
- reason codes
- deterministic business verdict
- confidence score

Example:

```json
{
  "winning_guardrail": "retention_guardrail",
  "deterministic_decision": {
    "business_verdict": "HOLD_AND_OPTIMIZE",
    "blocked_actions": [
      "aggressive_ads_scale",
      "increase_paywall_pressure"
    ],
    "allowed_actions": [
      "prioritize_retention",
      "small_controlled_test_only"
    ]
  }
}
```

## Specialist Agents

Specialist agents analyze separate domains:

- Activation Agent checks whether users reach core value.
- Retention Agent checks D0, D1, habit, and maturity.
- Monetization Agent checks paywall and purchase quality.
- Version Agent checks app version risk and release comparability.
- Ads Agent checks acquisition and campaign lifecycle.
- Tomorrow Forecast Agent checks forecast risk and trust weighting.

Specialist outputs are evidence, not final decisions.

## Structured Negotiation

`StructuredNegotiationService` creates a single-round cross-examination step between specialist outputs.

It records:

- negotiation rules
- specialist summaries
- agent responses
- negotiation timeline
- conflicts
- baseline comparison
- decision package
- summary counts

Rules include:

```text
max_rounds = 1
raw_chain_of_thought_allowed = false
evidence_required_for_objection = true
final_decision_owner = FinalDecisionAgent
```

The purpose is not endless debate. The purpose is to expose material conflicts and revised recommendations before final synthesis.

## Orchestrator Evidence Assembly

The orchestrator assembles the final evidence package for the Final Decision Agent.

It includes:

- metrics context
- guardrail policy
- specialist outputs
- structured negotiation result
- conflict matrix
- forecast evaluation
- calibration memory
- interaction log references

## Final Decision Agent

The Final Decision Agent synthesizes the evidence package.

Output can include:

- business verdict
- confidence score
- operating decision
- today operator summary
- accepted recommendations
- rejected recommendations
- resolved conflicts
- action plan for 24 to 72 hours
- risk notes
- monitoring plan
- rationale

Typical verdicts:

- `CONTINUE_MONITORING`
- `HOLD_AND_OPTIMIZE`
- `SMALL_CONTROLLED_TEST`
- `PAUSE_OR_ROLLBACK`
- `SCALE_CAUTIOUSLY`

The Final Decision Agent may be conservative, but it should not violate deterministic blocked actions.

## Decision Scenario Simulator

The Decision Scenario Simulator compares:

- baseline without intervention
- recommended intervention
- scenario with intervention
- baseline vs intervention comparison

It is used for human review. It should not claim exact uplift without experiment or holdout evidence.

## Graph Builder

`AiGrowthDoctorGraphBuilder` converts existing run JSON into React Flow nodes and edges.

It builds:

- graph metadata
- nodes
- edges
- detail payloads
- summary cards

It does not:

- modify run JSON
- re-run agents
- change negotiation logic
- expose raw chain-of-thought

## Auditability

Each run can be inspected through:

- dashboard cards
- raw run JSON
- interaction log
- structured negotiation timeline
- conflict matrix
- graph visualizer
- exported PNG

This makes it possible to answer:

- Which agent produced which signal?
- Which guardrail was triggered?
- Which actions were blocked?
- Which conflicts were detected?
- Why did the final verdict choose a conservative or aggressive action?

## Docker Architecture

Docker services:

- `web`: Laravel + Apache
- `worker`: async AI Growth Doctor worker
- `mysql`: database
- `node`: optional asset watcher profile

The `web` service copies application source into the image. It does not live-mount the full project source. Rebuild the image after changing PHP, Blade, JS, CSS, or built assets:

```bash
docker compose up -d --build web
```

Clear Laravel caches inside Docker when needed:

```bash
docker compose exec web php artisan view:clear
docker compose exec web php artisan route:clear
docker compose exec web php artisan cache:clear
docker compose exec web php artisan config:clear
```

## Architecture Principles

- Deterministic metrics first.
- AI reasoning second.
- Specialist outputs are isolated before synthesis.
- Guardrails define safe operating boundaries.
- Forecast must be evaluated and calibrated.
- Low-trust forecast cannot become a hard veto.
- Missing data must not produce confident diagnosis.
- Raw chain-of-thought is not displayed.
- Historical run JSON remains immutable.
- Human operator remains the final decision maker.
