# AI Growth Doctor - Project Summary for Qwen 3.7 Max

## 🎯 TL;DR (Elevator Pitch)

**AI Growth Doctor** adalah multi-agent AI copilot yang menganalisis metrik pertumbuhan aplikasi harian dan menghasilkan rekomendasi operasional konkret yang aman untuk direview manusia. Bukan single AI answer, tapi **Agent Society** dengan 6 specialist agents yang berdebat terstruktur sebelum memberikan final decision.

**Live Demo:** https://agd.hitungkalori.com (auth: judge / AGD-qwen)

---

## 🏗️ Arsitektur Sistem (8-Layer Pipeline)

```
1. Checkpoint JSON (input data harian)
   ↓
2. Metrics Extraction (deterministic calculation)
   ↓
3. App Data Mapping (source → generic contract)
   ↓
4. Guardrail & Safe Context (safety policy engine)
   ↓
5. Specialist Agents (6 parallel agents)
   ↓
6. Adaptive Structured Negotiation (max 3 round debate)
   ↓
7. Orchestrator Evidence Assembly
   ↓
8. Final Decision Agent + Scenario Simulator
```

---

## 🤖 6 Specialist Agents

| Agent | Fokus Domain | Key Questions |
|-------|-------------|---------------|
| **Activation Agent** | User onboarding & core action success | Apakah user baru berhasil mengalami "aha moment"? |
| **Retention Agent** | D1/D7/D30 retention, habit formation | Apakah user kembali menggunakan app? |
| **Monetization Agent** | Paywall conversion, ARPU, subscription | Apakah user mau bayar? pricing optimal? |
| **Version Agent** | Release quality, version performance | Apakah update terbaru merusak experience? |
| **Ads Agent** | Campaign lifecycle, CPI, ROAS | Apakah ads scaling aman atau berbahaya? |
| **Tomorrow Forecast Agent** | Predictive metrics, risk assessment | Apa yang mungkin terjadi 24-72 jam ke depan? |

### Output Structure per Agent:
- `domain_only_position`: Kesimpulan dari domain sempit saja
- `bounded_system_position`: Rekomendasi setelah apply guardrails + cross-domain constraints

---

## 🔒 Design Principles (Critical for AI Behavior)

### 1. **Metrics First, AI Second**
- Core metrics dihitung deterministik dari data aktual
- Agents **TIDAK BOLEH** invent numbers
- AI hanya interpretasi, bukan kalkulasi

### 2. **Specialist Isolation With Bounded Context**
- Setiap agent dapat focused domain + bounded safe context
- Mencegah satu perspektif (misal: ads) mendominasi terlalu dini
- Cross-domain awareness tetap ada tapi controlled

### 3. **Guardrails Before Action**
- Guardrail Policy Engine cek safety SEBELUM action jadi recommendation
- Contoh: Weak retention → block aggressive ads scaling
- Deterministic rules, bukan AI opinion

### 4. **Structured Negotiation (Adaptive)**
- Max 3 round debate antar agents
- **Early exit** jika tidak ada unresolved material conflict
- Round 2 & 3 TIDAK dipaksa jika hanya soft tensions remain
- Track: hard conflicts, soft tensions, partial concessions, safety-bounded revisions

### 5. **Ads Lifecycle vs Metrics Separation**
```
deterministic_lifecycle_context → campaign identity (degraded_legacy vs reset_successor)
ads_metric_independent_assessment → budget posture (hold/monitor/test/maintain/scale)
downstream activation/retention/guardrails → safety limits
```
- Reset successor label ≠ campaign performing well
- Budget posture harus dari CPI, conversion rate, volume, spend movement

### 6. **Final Synthesis, Not Voting**
- Final Decision Agent **BUKAN** simple vote counting
- Synthesize: specialist evidence + guardrails + conflicts + forecast calibration + business risk
- Single operating decision dengan evidence-backed reasoning

### 7. **Human-in-the-Loop**
- AI Growth Doctor = copilot, bukan autopilot
- Recommend, explain, simulate → **Human makes final decision**
- Tidak execute business actions automatically

---

## 📊 Generic Growth Metric Contract

Sistem reusable untuk berbagai aplikasi dengan mapping:

```
Source Metric (app-specific)          Generic Contract (reusable)
─────────────────────────────────  →  ─────────────────────────────
food_add_success                   →  activation.core_action_success_users
recipe_created                     →  activation.core_action_success_users
workout_completed                  →  activation.core_action_success_users
day_1_return                       →  retention.d1_returning_users
day_7_streak                       →  retention.d7_habit_users
subscription_started               →  monetization.paying_users
```

**Config file:** `config/ai_growth_doctor.php`

**Validation:** Mapping harus valid sebelum agents bisa analyze

---

## 🛡️ Guardrail Policy Engine

Deterministic safety layer yang memblok action berbahaya:

```php
// Example guardrail rules
if (retention.d7 < threshold && ads.scaling == 'aggressive') {
    return BLOCKED('Weak retention blocks ads scaling');
}

if (version.crash_rate > 5% && paywall.change == 'global_increase') {
    return BLOCKED('Unstable version blocks paywall pressure');
}

if (forecast.trust_score < 0.6 && decision == 'major_change') {
    return DOWNGRADE_TO('monitor_only');
}
```

**Key property:** Guardrails adalah deterministic code, BUKAN AI prose

---

## ⚖️ Structured Negotiation Flow

### Round 1: Initial Position
- Setiap agent submit `domain_only_position` + `bounded_system_position`
- System detect hard conflicts (mutually exclusive recommendations)
- System detect soft tensions (different priorities, not mutually exclusive)

### Round 2: Targeted Debate (ONLY IF needed)
- Agents dengan conflicts diberi kesempatan respond
- Focus pada unresolved material conflicts
- Early exit jika semua conflicts resolved atau hanya soft tensions remain

### Round 3: Final Concession (RARE)
- Last chance untuk safety-bounded revisions
- Partial concessions recorded
- Baseline comparison vs single-agent approach

### Negotiation Output:
```json
{
  "unresolved_hard_conflicts": [...],
  "bounded_soft_tensions": [...],
  "partial_concessions": [...],
  "safety_bounded_revisions": [...],
  "evidence_references": [...],
  "early_exit_reason": "No unresolved material conflicts after Round 1",
  "total_rounds_run": 1
}
```

---

## 🧠 Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | Laravel 10+ |
| **Database** | MySQL 8 (port 3307 in Docker) |
| **AI Provider** | OpenAI API / Qwen API (configurable) |
| **Worker** | Laravel Queue (`growth-doctor:work`) |
| **Frontend** | Blade + Tailwind + React Flow (Vite) |
| **Container** | Docker Compose (web, mysql, worker) |
| **Visualization** | React Flow graph visualizer |

---

## 🚀 Cara Menjalankan

### Local Development (Docker)
```bash
make dev              # Start full stack (web + mysql + worker)
make up               # Start detached
docker compose exec web php artisan growth-doctor:work --sleep=1  # Manual worker
```

### AI Provider Config
```bash
# OpenAI
export OPENAI_API_KEY="sk-..."

# Qwen
export QWEN_API_KEY="..."

# Output language
export AI_OUTPUT_LANGUAGE="Indonesian"  # Default: English
```

### Build Frontend Assets
```bash
npm install
npm run build
docker compose up -d --build web  # Rebuild container after asset changes
```

### Access Points
- App: http://localhost:8080
- Dashboard: http://localhost:8080/ai-growth-doctor
- Graph Visualizer: `/ai-growth-doctor/runs/{runId}/graph-view`
- Graph JSON: `/ai-growth-doctor/runs/{runId}/graph`

---

## 📁 File Structure Penting

```
/workspace
├── config/ai_growth_doctor.php          # App profile & metric mapping
├── app/Services/AIGrowthDoctor/         # Core services
│   ├── MetricsExtractionService.php
│   ├── GuardrailPolicyEngine.php
│   ├── Agents/                          # 6 specialist agents
│   ├── Negotiation/                     # Structured negotiation logic
│   ├── Orchestrator.php                 # Evidence assembly
│   └── FinalDecisionAgent.php
├── resources/prompts/ai_growth_doctor/  # Prompt templates
│   └── structured_negotiation.md
├── storage/app/ai-growth-doctor/runs/   # Immutable run JSON files
├── docs/
│   ├── ARCHITECTURE.md
│   ├── AGENT_WORKFLOW.md
│   ├── GENERIC_GROWTH_METRIC_CONTRACT.md
│   ├── ONBOARD_NEW_APP.md
│   └── QWEN_PROJECT_SUMMARY.md          # This file
└── resources/js/agd-graph/              # React Flow visualizer
```

---

## 🎭 Use Cases & Questions yang Dijawab

AI Growth Doctor membantu jawab:

1. **Health Check**: Apakah app sehat hari ini?
2. **Problem Diagnosis**: Masalah utama di activation, retention, monetization, version, ads, atau forecast risk?
3. **Scaling Safety**: Apakah aman scale acquisition?
4. **Action Recommendation**: Hold, optimize, atau small controlled test?
5. **Monitoring Priority**: Metrics mana yang perlu di-watch 24-72 jam ke depan?
6. **Guardrail Transparency**: Recommendations mana yang diblok deterministic guardrails?
7. **Conflict Detection**: Konflik apa yang dideteksi Agent Society yang single-agent answer mungkin miss?

---

## 🔐 Safety Features

| Feature | Implementation |
|---------|---------------|
| **Immutable Runs** | Run JSON files read-only untuk graph visualizer |
| **No Raw CoT** | Chain-of-thought tidak ditampilkan ke user |
| **Deterministic Guardrails** | Safety rules separate dari agent prose |
| **Forecast Trust-Weighting** | Low trust forecast tidak override mature actual metrics |
| **Human Decision** | System recommend, human decide |
| **Audit Trail** | Interaction log untuk setiap run |

---

## 📊 Decision Scenario Simulator

Setelah Final Decision Agent produce recommendation, system jalankan simulator untuk show:

- Best case scenario
- Base case scenario  
- Worst case scenario
- Key assumptions per scenario
- Metrics to watch untuk validate assumption

Ini membantu human operator understand risk profile sebelum commit ke decision.

---

## 🎨 Graph Visualizer Features

React Flow visualization untuk Agent Society runs:

**Nodes shown:**
- Checkpoint Load
- Metrics Extraction
- App Data Mapping
- Guardrail & Safe Context
- 6 Specialist Agents (parallel)
- Adaptive Structured Negotiation
- Orchestrator Evidence Assembly
- Final Decision Agent
- Decision Scenario Simulator

**Toolbar features:**
- Fit view / Reset zoom
- Minimap toggle
- Detail panel toggle
- Edge label toggle
- Presentation mode
- Export PNG
- Copy graph JSON link

**Detail panels show:**
- Hard conflicts vs bounded soft tensions
- Partial concessions
- Safety-bounded revisions
- Early exit justification (jika applicable)

---

## 🧪 Testing & Debugging

```bash
# Run tests
make test
php artisan test
docker compose exec web ./vendor/bin/phpunit

# View logs
make logs
docker compose logs -f worker

# Shell access
make shell
docker compose exec web bash

# Clear caches
docker compose exec web php artisan view:clear
docker compose exec web php artisan route:clear
docker compose exec web php artisan cache:clear
docker compose exec web php artisan config:clear
```

---

## 🌐 Hosted Demo

**URL:** https://agd.hitungkalori.com

**HTTP Basic Auth:**
- Username: `judge`
- Password: `AGD-qwen`

**Env config untuk production auth:**
```env
DEMO_AUTH_ENABLED=true
DEMO_AUTH_USER=judge
DEMO_AUTH_PASSWORD=AGD-qwen
```

After changing `.env`:
```bash
php artisan config:clear
php artisan config:cache
```

---

## 💡 Key Insights untuk Qwen 3.7 Max

### 1. **Ini Bukan Chatbot**
AI Growth Doctor adalah operating system untuk growth decision, bukan conversational AI. Output harus actionable, evidence-backed, dan safe untuk human review.

### 2. **Specialist > Generalist**
6 specialist agents dengan bounded context lebih baik daripada 1 general AI yang coba jawab semua. Ini mencegah "jack of all trades, master of none".

### 3. **Debate > Consensus**
Structured negotiation bukan tentang mencapai consensus, tapi tentang expose conflicts, tensions, dan trade-offs secara explicit. Human operator perlu tau ada disagreement, bukan just final answer.

### 4. **Safety > Speed**
Guardrail Policy Engine adalah deterministic code yang tidak bisa di-negotiate oleh agents. Jika guardrail bilang NO, maka NO – tidak peduli seberapa persuasive agent argument.

### 5. **Transparency > Black Box**
Semua decisions harus traceable: metrics → guardrails → agent positions → negotiations → final decision. User harus bisa audit kenapa recommendation X dipilih, bukan Y.

### 6. **Forecast Humility**
Tomorrow Forecast Agent punya trust score. Low trust forecast tidak boleh override high-confidence actual metrics. Forecast adalah input, bukan oracle.

### 7. **Human Agency**
System design assumption: human operator punya business context yang AI tidak punya (budget constraints, strategic pivots, market intelligence, dll). AI = copilot, human = pilot.

---

## 📚 Documentation References

- **Architecture Deep Dive:** `docs/ARCHITECTURE.md`
- **Agent Workflow Details:** `docs/AGENT_WORKFLOW.md`
- **Generic Metric Contract:** `docs/GENERIC_GROWTH_METRIC_CONTRACT.md`
- **New App Onboarding Guide:** `docs/ONBOARD_NEW_APP.md`
- **Structured Negotiation Prompts:** `resources/prompts/ai_growth_doctor/structured_negotiation.md`

---

## 🎯 Success Metrics untuk Project Ini

Project ini sukses jika:

1. ✅ Human operator merasa decision lebih jelas dan confident
2. ✅ False positive recommendations (yang ternyata berbahaya) < 5%
3. ✅ Conflicts yang dideteksi agents > conflicts yang missed
4. ✅ Time-to-decision berkurang tanpa sacrifice quality
5. ✅ Guardrails prevent dangerous actions 100% of the time
6. ✅ Audit trail lengkap untuk compliance/debugging
7. ✅ System bisa onboard new app dengan minimal code changes (hanya config mapping)

---

**Last Updated:** 2025-01-XX
**Author:** AI Growth Doctor Team
**Target Audience:** Qwen 3.7 Max (untuk comprehensive project understanding)
