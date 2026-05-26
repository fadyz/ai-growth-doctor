# Architecture

Dokumen ini menjelaskan arsitektur AI Growth Doctor.

## Gambaran Umum

AI Growth Doctor adalah workflow multi-agent yang menggabungkan deterministic metrics, specialist AI agents, forecast evaluation, calibration memory, guardrail policy, dan final decision synthesis.

Sistem ini dibuat untuk membantu founder aplikasi mengambil keputusan growth harian dengan lebih konsisten dan auditable.

## High-Level Flow

```text
Raw Data / Checkpoint
→ Metrics Extractor
→ Parallel Specialist Agents
→ Forecast Evaluation
→ Forecast Calibration
→ Guardrail Policy Engine
→ Final Decision Agent
→ Decision Scenario Simulator
→ Dashboard / Operator Decision
```

## 1. Data Layer

Data awal masuk dalam bentuk checkpoint atau data harian yang berisi metric seperti:

- activation daily
- retention daily
- monetization signal
- app version performance
- ads campaign performance
- forecast history
- evaluation history

Data sensitif tidak boleh disimpan dalam repository public.

## 2. Metrics Extractor

`MetricsExtractor` bertugas menghitung metric utama secara deterministik.

Contoh metric:

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
- conversion rate

Angka-angka ini dihitung dari data, bukan dibuat oleh AI.

## 3. Specialist Agents

Specialist agents berjalan secara paralel agar setiap domain dianalisis dari sudut pandang masing-masing.

### Activation Agent

Menganalisis funnel dari session sampai food add success.

Fokus:
- apakah user berhasil masuk ke core action
- apakah ada gap session ke workspace
- apakah workspace quality sehat

### Retention Agent

Menganalisis kebiasaan user setelah install atau join.

Fokus:
- D0 logging
- D1 return
- 7-day habit
- average log days

### Monetization Agent

Menganalisis paywall dan purchase signal.

Fokus:
- paywall view
- purchase start
- purchase success
- purchase rate
- risiko monetisasi terlalu dini

### Version Agent

Menganalisis performa antar versi aplikasi.

Fokus:
- apakah versi terbaru sehat
- apakah ada regression
- apakah versi lama masih relevan untuk keputusan rollout
- apakah sample versi cukup besar

### Ads Agent

Menganalisis campaign dan acquisition lifecycle.

Fokus:
- campaign health
- cost per install
- conversion rate
- campaign legacy vs reset successor
- apakah aman scaling atau hanya small test

### Tomorrow Forecast Agent

Membaca forecast deterministic dan memberi interpretasi risiko.

Fokus:
- prediksi activation
- prediksi retention
- prediksi monetization
- risk flags
- scaling caution

## 4. Forecast Evaluation

`ForecastEvaluationService` membandingkan forecast sebelumnya dengan actual data yang sudah matang.

Tujuannya:
- mengetahui forecast hit atau miss
- membedakan metric mature, pending maturity, pending actual data, dan missing actual
- menghindari keputusan berbasis forecast yang belum valid

Contoh status metric:
- `hit`
- `miss_low`
- `miss_high`
- `pending_maturity`
- `pending_actual_data`
- `missing_actual_metric`
- `invalid_forecast_metric`

## 5. Forecast Calibration Memory

`ForecastCalibrationService` menggunakan hasil evaluasi forecast untuk menghitung tingkat kepercayaan.

Contoh output:
- trust score
- forecast role
- hit rate
- systematic bias
- directional only vs can strengthen guardrail

Jika forecast belum cukup akurat, sistem menurunkan peran forecast menjadi sinyal pendukung saja.

## 6. Guardrail Policy Engine

`GuardrailPolicyEngine` adalah aturan deterministik yang membatasi keputusan AI.

Contoh guardrail:
- data quality guardrail
- retention guardrail
- activation guardrail
- forecast guardrail
- ads acquisition guardrail
- monetization guardrail
- release guardrail

Guardrail mencegah sistem memberi rekomendasi agresif ketika metric kunci belum sehat.

Contoh:
- jangan scale iklan agresif jika retention lemah
- jangan menjadikan forecast sebagai hard veto jika trust score rendah
- jangan membiarkan versi lama memblok rollout versi baru jika tidak relevan

## 7. Final Decision Agent

Final Decision Agent melakukan fan-in dari semua evidence:

- specialist agent outputs
- deterministic metrics
- guardrail policy
- forecast evaluation
- calibration memory
- ads lifecycle context
- version risk context

Output yang dihasilkan:
- business verdict
- confidence score
- operating decision
- diagnosis
- prioritized actions
- 24–72 hour action plan
- risk notes
- monitoring plan
- exit condition

Contoh verdict:
- `CONTINUE_MONITORING`
- `HOLD_AND_OPTIMIZE`
- `SMALL_CONTROLLED_TEST`
- `PAUSE_OR_ROLLBACK`
- `SCALE_CAUTIOUSLY`

## 8. Decision Scenario Simulator

Decision Scenario Simulator membandingkan baseline forecast dengan skenario rekomendasi.

Tujuannya:
- membantu operator memahami konsekuensi keputusan
- membedakan doing nothing vs recommended action
- memberi gambaran metric apa yang harus dipantau

## 9. Audit Trail

Sistem menyimpan interaction log agar keputusan dapat diaudit.

Log dapat mencakup:
- agent request
- agent response
- execution mode
- request duration
- cache hit
- result summary
- guardrail decision
- final decision context trace

## 10. Human-in-the-loop

AI Growth Doctor tidak mengambil keputusan otomatis. Sistem memberi rekomendasi, alasan, dan batas aman, tetapi keputusan akhir tetap pada manusia.

## Prinsip Penting

- Deterministic metrics first
- AI reasoning second
- Guardrail before action
- Forecast must be evaluated
- Low-trust forecast cannot become hard veto
- Missing data should not produce confident diagnosis
- Human remains final decision maker
