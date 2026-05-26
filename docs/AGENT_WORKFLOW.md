# Agent Workflow

Dokumen ini menjelaskan workflow agent di AI Growth Doctor: peran masing-masing agent, data yang dibaca, output yang dihasilkan, dan bagaimana semua agent disatukan menjadi keputusan operasional harian.

## Ringkasan Workflow

AI Growth Doctor menggunakan pola:

```text
Metrics Extractor
→ Parallel Specialist Agents
→ Guardrail Policy Engine
→ Final Decision Agent
→ Decision Scenario Simulator
```

Sistem ini tidak meminta satu AI untuk menjawab semua hal sekaligus. Masalah growth dipecah menjadi beberapa domain agar setiap agent bisa fokus pada area yang berbeda.

## Prinsip Utama

1. **Metrics first, AI second**  
   Angka utama dihitung secara deterministik dari data aktual. Agent tidak boleh mengarang angka.

2. **Specialist agents are isolated by design**  
   Setiap agent membaca domain berbeda secara terpisah. Tujuannya agar satu sudut pandang, misalnya monetization atau ads, tidak langsung mendominasi diagnosis awal.

3. **Guardrail before action**  
   Rekomendasi agent tidak langsung menjadi keputusan. Guardrail policy mengecek apakah tindakan tertentu aman dilakukan.

4. **Final synthesis, not blind voting**  
   Final Decision Agent tidak sekadar menghitung suara agent. Ia menyintesis evidence, konflik, guardrail, forecast, dan calibration.

5. **Human-in-the-loop**  
   Sistem memberi rekomendasi dan alasan, tetapi keputusan akhir tetap berada di tangan manusia.

---

## 1. Metrics Extractor

### Tujuan

Metrics Extractor membangun `metrics_context` sebagai sumber kebenaran angka.

### Data yang Dibaca

Contoh data yang bisa masuk:

- activation daily
- retention daily
- monetization events
- app version performance
- ads campaign report
- forecast history
- evaluation history

### Output Utama

Contoh output:

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

### Catatan

Metrics Extractor adalah lapisan deterministik. Jika angka salah di sini, agent juga bisa ikut salah. Karena itu, data quality dan maturity check penting.

---

## 2. Activation Agent

### Tujuan

Activation Agent membaca apakah pengguna berhasil mencapai core value aplikasi.

Untuk aplikasi Hitung Kalori, core action utamanya adalah berhasil mencatat makanan atau `food_add_success`.

### Input

Activation Agent membaca:

- `session_users`
- `workspace_users`
- `food_add_success_users`
- `food_add_success_rate_from_session`
- `food_add_success_rate_from_workspace`
- version context jika relevan

### Pertanyaan yang Dijawab

- Apakah user berhasil masuk ke workspace?
- Apakah user yang sudah masuk workspace berhasil add food?
- Apakah bottleneck ada sebelum workspace atau di dalam add-food flow?
- Apakah activation cukup sehat untuk menerima lebih banyak acquisition?

### Output

Contoh output:

```json
{
  "status": "warning",
  "summary": "Workspace quality sehat, tetapi session ke food_add_success masih sedang. Bottleneck kemungkinan ada sebelum atau saat entry ke workspace."
}
```

### Batasan

Activation Agent sebaiknya tidak menjadikan monetization sebagai diagnosis utama. Paywall boleh disebut sebagai downstream context, tetapi fokus agent ini tetap activation funnel.

---

## 3. Retention Agent

### Tujuan

Retention Agent membaca apakah pengguna kembali dan mulai membentuk kebiasaan.

### Input

Retention Agent membaca:

- `d0_logged_rate`
- `d1_logged_rate`
- `habit_7d_rate`
- `avg_log_days_7d`
- maturity info
- cohort windows

### Pertanyaan yang Dijawab

- Apakah pengguna yang aktif di D0 kembali di D1?
- Apakah habit 7 hari mulai terbentuk?
- Apakah retention cukup sehat untuk mendukung scaling?
- Apakah retention data sudah mature?

### Output

Contoh output:

```json
{
  "status": "warning",
  "summary": "D0 cukup aktif, tetapi D1 dan habit 7D masih lemah. Scaling acquisition agresif berisiko menghasilkan user yang tidak kembali."
}
```

### Catatan Maturity

Retention metric tidak selalu bisa dibaca pada hari yang sama.

Contoh:

```text
D1 logged rate membutuhkan H+1
Habit 7D membutuhkan H+6 atau H+7
```

Karena itu, sistem harus membedakan:

- mature metric
- pending maturity
- pending actual data
- missing actual metric

---

## 4. Monetization Agent

### Tujuan

Monetization Agent membaca apakah paywall dan purchase signal sehat.

### Input

Monetization Agent membaca:

- `paywall_view_users`
- `purchase_start_users`
- `purchase_success_users`
- `paywall_rate_from_food_add_success`
- `purchase_success_rate_from_paywall`
- activation context

### Pertanyaan yang Dijawab

- Apakah paywall muncul terlalu dini?
- Apakah user sudah cukup merasakan value sebelum paywall?
- Apakah purchase signal cukup kuat atau masih low sample?
- Apakah monetization boleh dioptimasi atau harus ditahan?

### Output

Contoh output:

```json
{
  "status": "active_signal",
  "summary": "Monetisasi sudah menghasilkan purchase, tetapi sample masih kecil. Optimasi paywall boleh dilakukan hati-hati, jangan sampai merusak activation."
}
```

### Catatan

Monetization Agent harus membaca sample size. Purchase kecil tidak boleh menjadi dasar overconfidence.

---

## 5. Version Agent

### Tujuan

Version Agent membaca performa antar versi aplikasi.

### Input

Version Agent membaca:

- `version_metrics`
- top versions
- release candidate versions
- activation by version
- monetization by version

### Pertanyaan yang Dijawab

- Apakah versi terbaru menunjukkan regression?
- Apakah versi lama masih relevan untuk keputusan rollout?
- Apakah sample versi cukup besar?
- Apakah rollout aman dilanjutkan?

### Output

Contoh output:

```json
{
  "status": "caution",
  "summary": "Versi terbaru memiliki sample lebih kecil dan belum cukup kuat untuk dijadikan dasar scaling. Versi lama hanya menjadi konteks, bukan veto rollout."
}
```

### Aturan Penting

Versi lama yang tidak relevan tidak boleh mem-veto rollout modern.

Contoh:

```text
Legacy version with incompatible instrumentation = context only
Current release line = relevant for release decision
```

---

## 6. Ads Agent

### Tujuan

Ads Agent membaca acquisition dan campaign lifecycle.

### Input

Ads Agent membaca:

- campaign cost
- clicks
- impressions
- conversions
- cost per install
- conversion rate
- recent vs previous performance
- campaign lifecycle context
- activation and retention context

### Pertanyaan yang Dijawab

- Campaign mana yang sehat?
- Apakah campaign lama sudah degraded?
- Apakah reset campaign layak diuji?
- Apakah aman scale budget?
- Apakah ads signal didukung downstream quality?

### Output

Contoh output:

```json
{
  "status": "active",
  "summary": "Campaign reset lebih sehat daripada legacy campaign, tetapi tetap harus small controlled test karena retention belum cukup kuat."
}
```

### Catatan

Ads Agent tidak boleh hanya membaca CPI atau conversion rate. Ia harus membaca downstream context seperti activation dan retention.

---

## 7. Tomorrow Forecast Agent

### Tujuan

Tomorrow Forecast Agent membaca forecast deterministic dan menerjemahkannya menjadi risk signal.

### Input

Tomorrow Forecast Agent membaca:

- `tomorrow_forecast_metrics`
- predicted activation
- predicted retention
- predicted monetization
- risk flags
- forecast calibration context

### Pertanyaan yang Dijawab

- Apa risiko besok?
- Apakah activation diprediksi aman?
- Apakah retention/habit berisiko?
- Apakah forecast cukup dipercaya?
- Apakah forecast boleh memperkuat guardrail?

### Output

Contoh output:

```json
{
  "status": "watch",
  "summary": "Forecast menunjukkan activation relatif aman, tetapi retention dan habit perlu diawasi. Forecast hanya menjadi directional signal jika trust score masih rendah."
}
```

### Catatan

Forecast tidak boleh langsung menjadi hard veto jika:

```text
forecast_role = directional_signal_only
trust_score < threshold
```

---

## 8. Guardrail Policy Engine

### Tujuan

Guardrail Policy Engine adalah lapisan deterministic policy yang membatasi tindakan AI.

### Contoh Guardrail

- Data Quality Guardrail
- Activation Guardrail
- Retention Guardrail
- Monetization Guardrail
- Forecast Guardrail
- Ads Acquisition Guardrail
- Release Guardrail

### Contoh Keputusan

Jika retention lemah:

```json
{
  "blocked_actions": ["increase_budget_aggressively"],
  "allowed_actions": ["continue_monitoring", "small_controlled_test"]
}
```

Jika forecast low trust:

```json
{
  "forecast_guardrail": {
    "triggered": false,
    "reason_codes": [
      "forecast_directional_only_not_hard_veto"
    ]
  }
}
```

### Prinsip

Guardrail tidak menggantikan seluruh keputusan bisnis. Ia menjadi batas aman agar AI tidak memberi rekomendasi yang melanggar prinsip founder.

---

## 9. Final Decision Agent

### Tujuan

Final Decision Agent menyatukan semua evidence menjadi satu rekomendasi operasional.

### Input

Final Decision Agent menerima:

- compact metrics context
- specialist agent summaries
- guardrail policy
- forecast evaluation
- forecast calibration
- ads lifecycle context
- version risk context

### Output

Contoh output:

```json
{
  "business_verdict": "HOLD_AND_OPTIMIZE",
  "today_operator_summary": "Jangan scaling agresif. Jalankan small controlled test pada reset campaign dan fokus memperbaiki D1 habit.",
  "prioritized_actions": [
    "Perbaiki D1 habit nudge",
    "Evaluasi campaign reset secara kecil",
    "Pantau activation funnel"
  ]
}
```

### Yang Harus Ada di Final Decision

- verdict
- confidence score
- reason
- operating decision
- action plan
- risk notes
- monitoring plan
- exit condition
- weak evidence or uncertainty

### Catatan

Final Decision boleh lebih konservatif dari deterministic baseline, tetapi tidak boleh melanggar blocked actions dari guardrail.

---

## 10. Decision Scenario Simulator

### Tujuan

Decision Scenario Simulator membandingkan baseline scenario dengan recommended action scenario.

### Input

- final decision
- tomorrow forecast metrics
- guardrail policy
- specialist summaries

### Pertanyaan yang Dijawab

- Apa yang mungkin terjadi jika tidak melakukan intervensi besar?
- Apa yang diharapkan jika rekomendasi dijalankan?
- Metric mana yang harus dipantau?
- Kapan keputusan harus dievaluasi ulang?

### Output

Contoh output:

```json
{
  "baseline_scenario": "Continue monitoring without aggressive scale.",
  "recommended_action_scenario": "Small controlled reset campaign test plus D1 habit optimization.",
  "monitoring_metrics": [
    "food_add_success_rate_from_session",
    "d1_logged_rate",
    "habit_7d_rate"
  ]
}
```

---

## 11. Interaction Log

### Tujuan

Interaction log membuat sistem bisa diaudit.

### Yang Dicatat

- agent request
- agent response
- source key
- execution mode
- cache hit
- request started at
- request finished at
- duration
- summary
- final decision trace
- guardrail trace

### Manfaat

Interaction log membantu menjawab:

- Agent mana yang memberi sinyal apa?
- Apakah agent berjalan paralel?
- Apakah hasil dari cache atau real request?
- Guardrail mana yang aktif?
- Kenapa final decision memilih verdict tertentu?

---

## 12. Data Readiness Mode

Jika startup belum punya tracking lengkap, sistem seharusnya tidak memaksakan diagnosis.

Contoh:

```text
Jika D1 retention tidak tersedia, sistem tidak boleh menyimpulkan retention sehat atau buruk.
Jika purchase sample terlalu kecil, monetization harus diberi label low_sample.
Jika forecast belum pernah dievaluasi, forecast harus dianggap directional only.
```

Output yang benar:

```json
{
  "data_readiness": "insufficient",
  "missing_metrics": ["d1_logged_rate", "purchase_success_users"],
  "blocked_actions": ["scale_budget_aggressively"],
  "recommended_next_step": "Install missing tracking before making aggressive growth decisions."
}
```

---

## 13. End-to-End Example

Contoh workflow harian:

```text
1. MetricsExtractor membaca checkpoint data.
2. Activation Agent melihat session → food_add_success masih sedang.
3. Retention Agent melihat D1 dan habit 7D masih lemah.
4. Monetization Agent melihat purchase ada, tetapi sample kecil.
5. Ads Agent melihat reset campaign membaik.
6. Forecast Agent melihat retention besok berisiko.
7. Guardrail memblok scaling agresif.
8. Final Decision memilih HOLD_AND_OPTIMIZE.
9. Scenario Simulator menyarankan small controlled test dan monitoring 24–72 jam.
```

Contoh final verdict:

```text
HOLD_AND_OPTIMIZE
Jangan scale agresif dulu. Jalankan uji kecil reset campaign, perbaiki D1 habit, dan pantau activation serta retention sebelum menaikkan budget.
```

---

## 14. Why Multi-Agent?

AI Growth Doctor menggunakan multi-agent karena masalah growth tidak satu dimensi.

Ads bisa terlihat bagus, tetapi retention buruk. Monetization bisa naik, tetapi activation terganggu. Versi baru bisa menaikkan purchase, tetapi menurunkan food add success.

Dengan agent spesialis, setiap domain mendapat ruang analisis sendiri sebelum disatukan menjadi keputusan final.

---

## 15. Summary

AI Growth Doctor workflow terdiri dari:

```text
Deterministic metrics
→ Specialist agents
→ Guardrail policy
→ Forecast evaluation
→ Calibration memory
→ Final decision
→ Scenario simulation
→ Human operator
```

Sistem ini dirancang untuk membuat keputusan growth lebih cepat, lebih konsisten, dan lebih bisa diaudit tanpa mengambil alih peran manusia.
