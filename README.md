# AI Growth Doctor

AI Growth Doctor adalah sistem multi-agent AI yang bertindak sebagai co-pilot bagi founder atau owner aplikasi untuk membaca kondisi growth harian dan menghasilkan rekomendasi operasional berbasis data.

Project ini tidak hanya menampilkan dashboard angka. Sistem bekerja seperti tim growth kecil yang terdiri dari beberapa agent spesialis: Activation Agent, Retention Agent, Monetization Agent, Version Agent, Ads Agent, dan Tomorrow Forecast Agent. Masing-masing agent membaca domain berbeda, lalu Final Decision Agent menyintesis semua evidence menjadi satu keputusan harian.

## Tujuan

AI Growth Doctor dibuat untuk membantu menjawab pertanyaan seperti:

- Apakah hari ini aplikasi sedang sehat?
- Apakah masalah utama ada di activation, retention, monetization, version, ads, atau forecast?
- Apakah aman untuk scaling iklan?
- Apakah harus hold, optimize, atau menjalankan small controlled test?
- Metric apa yang harus dipantau dalam 24 sampai 72 jam ke depan?

## Fitur Utama

- Deterministic Metrics Extractor
- Parallel specialist agent fan-out
- Activation Agent
- Retention Agent
- Monetization Agent
- Version Agent
- Ads Agent
- Tomorrow Forecast Agent
- Guardrail Policy Engine
- Forecast Evaluation
- Forecast Calibration Memory
- Final Decision Agent
- Decision Scenario Simulator
- Live Agent Progress
- Interaction Log / audit trail

## Prinsip Desain

AI Growth Doctor menggunakan kombinasi antara AI reasoning dan aturan deterministik.

Angka utama seperti activation rate, D1 retention, 7-day habit, paywall conversion, dan campaign performance dihitung dari data aktual, bukan dikarang oleh AI.

AI Agent bertugas membaca, membandingkan, dan menjelaskan evidence. Guardrail Policy Engine menjadi batas aman agar sistem tidak memberi rekomendasi terlalu agresif saat metric kunci belum sehat.

## Alur Singkat

```text
Checkpoint Data
→ Metrics Extractor
→ Parallel Specialist Agents
→ Forecast Evaluation & Calibration
→ Guardrail Policy Engine
→ Final Decision Agent
→ Decision Scenario Simulator
```

## Human-in-the-loop

AI Growth Doctor bukan pengambil keputusan otomatis. Sistem ini dirancang sebagai co-pilot. AI membantu membuat data lebih jelas, tetapi keputusan akhir tetap berada di tangan manusia.

## Arsitektur

Lihat penjelasan arsitektur sistem di:

```text
docs/ARCHITECTURE.md
```

## Menjalankan Lokal Dengan Docker

Jalankan stack lokal:

```bash
make dev
```

Jika ingin AI agents aktif, pass API key dari shell sebelum menjalankan Docker:

```bash
export OPENAI_API_KEY="isi_api_key"
make dev
```

AI output language default untuk Docker adalah English. Untuk menggantinya:

```bash
export AI_OUTPUT_LANGUAGE="Indonesian"
make dev
```

Atau gunakan provider Sumopod:

```bash
export SUMOPOD_API_KEY="isi_api_key"
make dev
```

Command ini menjalankan Laravel web server di Docker, MySQL, dan worker:

```text
php artisan growth-doctor:work --sleep=1
```

Aplikasi dapat dibuka di:

```text
http://localhost:8080
```

Database Docker tersedia dari host di port `3307` dengan credential:

```text
database: ai_growth_doctor
username: laravel
password: secret
root password: root
```

Untuk menjalankan asset watcher Laravel Mix:

```bash
make assets
```
