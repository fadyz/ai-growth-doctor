# AI Growth Doctor

AI Growth Doctor is a multi-agent operating copilot for reading daily product growth signals and turning them into a concrete, evidence-backed operating decision.

The system is designed as an **Agent Society**, not a single general-purpose AI answer. Deterministic services first extract the metrics and safe context. Specialist agents then analyze separate growth domains, challenge each other through a single-round structured negotiation, and pass the evidence package to a Final Decision Agent.

Before the Agent Society reads the data, app-specific source metrics are mapped into a **Generic Growth Metric Contract**. Hitung Kalori remains the real aggregated case study, but metrics such as `food_add_success` are normalized into reusable concepts such as `activation.core_action_success_users`.

Default app profile and metric mapping are defined in:

```text
config/ai_growth_doctor.php
```

The goal is not to automate business decisions blindly. The goal is to make the daily growth decision clearer, safer, and easier for a human operator to review.

## What It Answers

AI Growth Doctor helps answer questions such as:

- Is the app healthy today?
- Is the main issue activation, retention, monetization, release/version quality, ads, or forecast risk?
- Is it safe to scale acquisition?
- Should the operator hold, optimize, or run only a small controlled test?
- Which metrics should be watched over the next 24 to 72 hours?
- Which recommendations were blocked by deterministic guardrails?
- Which conflicts were detected by the Agent Society that a single-agent answer may have missed?

## Agent Society Flow

```text
Checkpoint JSON
-> Metrics Extraction
-> App Data Mapping
-> Guardrail / Safe Context
-> Specialist Agents
   - Activation Agent
   - Retention Agent
   - Monetization Agent
   - Version Agent
   - Ads Agent
   - Tomorrow Forecast Agent
-> Single-Round Structured Negotiation
-> Orchestrator Evidence Assembly
-> Final Decision Agent
-> Decision Scenario Simulator
```

## Core Capabilities

- Deterministic metrics extraction
- App Profile and Generic Growth Metric Contract
- Source-to-generic metric mapping
- Mapping validation
- Guardrail Policy Engine
- Forecast evaluation
- Forecast calibration memory
- Parallel specialist agent fan-out
- Single-round structured negotiation
- Conflict matrix
- Orchestrator evidence assembly
- Final Decision Agent
- Decision Scenario Simulator
- Live agent progress
- Interaction log / audit trail
- React Flow graph visualizer for Agent Society runs

## Design Principles

### Metrics First, AI Second

Core metrics such as activation rate, D1 retention, 7-day habit rate, paywall conversion, version performance, and campaign movement are calculated from actual data. Agents are not allowed to invent numbers.

### Specialist Isolation

Each specialist agent reads a focused domain. This prevents one perspective, such as ads or monetization, from dominating the diagnosis too early.

### Guardrails Before Action

The Guardrail Policy Engine checks whether an action is safe before it can become the final recommendation. For example, weak retention can block aggressive ads scaling or global paywall pressure increases.

### Structured Negotiation

Specialist outputs are passed through a deterministic single-round structured negotiation. The system records material conflicts, evidence references, revised recommendations, and baseline comparison against a single-agent approach.

### Final Synthesis, Not Voting

The Final Decision Agent does not simply count agent votes. It synthesizes specialist evidence, guardrails, conflicts, forecast calibration, and business risk into one operating decision.

### Human-in-the-Loop

AI Growth Doctor is a copilot. It recommends, explains, and simulates. The final operating decision remains with the human operator.

## Graph Visualizer

The project includes a React Flow graph visualizer for existing run JSON files.

Graph page:

```text
/ai-growth-doctor/runs/{runId}/graph-view
```

Graph JSON endpoint:

```text
/ai-growth-doctor/runs/{runId}/graph
```

Run JSON files are read from:

```text
storage/app/ai-growth-doctor/runs/{runId}.json
```

The graph visualizer reads existing run JSON only. It does not modify run files, negotiation output, prompts, or AI decision logic.

The graph shows:

- Checkpoint Load
- Metrics Extraction
- App Data Mapping
- Guardrail & Safe Context
- 6 Specialist Agents
- Single-Round Structured Negotiation
- Orchestrator Evidence Assembly
- Final Decision Agent
- Decision Scenario Simulator

The graph toolbar supports:

- Fit view
- Reset zoom
- Minimap toggle
- Detail panel toggle
- Edge label toggle
- Presentation mode
- Export PNG
- Copy graph JSON link

## Local Development With Docker

Start the full local stack:

```bash
make dev
```

Or run it detached:

```bash
make up
```

The Docker stack runs:

- Laravel web service
- MySQL
- AI Growth Doctor worker

Open the app at:

```text
http://localhost:8080
```

The dashboard is available at:

```text
http://localhost:8080/ai-growth-doctor
```

## AI Provider Configuration

To run agents with OpenAI:

```bash
export OPENAI_API_KEY="your_api_key"
make dev
```

The Docker default output language is English. To override it:

```bash
export AI_OUTPUT_LANGUAGE="Indonesian"
make dev
```

To use Qwen:

```bash
export QWEN_API_KEY="your_api_key"
make dev
```

## Worker

The worker processes pending async runs:

```text
php artisan growth-doctor:work --sleep=1
```

In Docker, the `worker` service runs this command automatically.

## Database

Docker MySQL is exposed to the host at port `3307`.

```text
database: ai_growth_doctor
username: laravel
password: secret
root password: root
```

## Frontend Assets

The legacy dashboard still uses the existing Blade/Tailwind/CDN approach.

The Agent Society graph visualizer is a Vite + React island mounted into Blade:

```html
<div id="agd-graph-root" data-run-id="..." data-graph-url="..."></div>
```

Build graph assets from the project root:

```bash
npm install
npm run build
```

Because the Docker `web` service copies source code into the image and does not live-mount the full project source, rebuild the web container after code or asset changes:

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

## Useful Commands

```bash
make up
make logs
make shell
make test
make down
```

Run tests directly:

```bash
php artisan test
```

Run tests in Docker:

```bash
docker compose exec web ./vendor/bin/phpunit
```

## Documentation

Architecture:

```text
docs/ARCHITECTURE.md
```

Agent workflow:

```text
docs/AGENT_WORKFLOW.md
```

Generic growth metric contract:

```text
docs/GENERIC_GROWTH_METRIC_CONTRACT.md
```

New app onboarding:

```text
docs/ONBOARD_NEW_APP.md
```

## Safety Notes

- Existing run JSON files are treated as immutable input for the graph visualizer.
- Raw chain-of-thought is not displayed.
- Guardrails are deterministic and should remain separate from agent prose.
- Forecast output is weighted by calibration and should not override mature actual metrics when trust is low.
- The system supports human decision-making; it does not execute business actions automatically.
