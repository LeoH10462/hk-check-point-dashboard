# BorderFlow · Hong Kong Checkpoint Dashboard

**EN** | [CN · 简体中文](README.zh-CN.md)

BorderFlow is a CityU database application project. The traveller dashboard shows official checkpoint wait categories, transport connections and historical passenger traffic. The password-protected operations dashboard adds demo traveller records, vehicle quota bookings, queue simulations and MySQL execution plans.

## Run locally on Windows

Start Docker Desktop. Open PowerShell in the project folder that contains `compose.yaml` and run:

```powershell
if (-not (Test-Path .env)) {
    Copy-Item .env.example .env
}
if (Select-String -LiteralPath .env -Pattern '^APP_KEY=$' -Quiet) {
    $bytes = New-Object byte[] 32
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    $key = [Convert]::ToBase64String($bytes)
    (Get-Content .env) -replace '^APP_KEY=$', "APP_KEY=base64:$key" |
        Set-Content -LiteralPath .env -Encoding ASCII
}
notepad .env
```

Set `OPERATOR_PASSWORD` to at least 12 characters in `.env`, then save and close Notepad. The default account is `operator`. To use a different account, set `OPERATOR_USERNAME`.

```powershell
docker compose up -d --build
powershell -ExecutionPolicy Bypass -File .\scripts\import-traffic.ps1
```

Open the [traveller dashboard](http://127.0.0.1:8000/) or the [operations dashboard](http://127.0.0.1:8000/operations). The first build downloads Docker images and Composer dependencies. On the same computer, once the images and data are ready, use `docker compose up -d --no-build --pull never` to start the existing deployment. Use `docker compose down` to stop it and retain the MySQL data.

### Upgrade an existing database

After updating the application, run `powershell -ExecutionPolicy Bypass -File .\scripts\upgrade-utf8.ps1` with the Compose project running. This repairs Chinese seed text and applies checkpoint name updates while preserving imported traffic, bookings and simulation scenarios.

## Features

| Area | What you can do |
| --- | --- |
| Traveller dashboard | Compare checkpoint wait categories, switch direction and traveller type, and view transport connections and historical traffic |
| Operations dashboard | Review daily official figures, checkpoint details and seven-day trends. Filter demo travellers and crossings, and view simulated minute-by-minute flows |
| Queue simulation | Set arrival growth, open lanes and the initial queue. Compare a 60-minute scenario with its baseline and replay saved results |
| Quota bookings | Create demo vehicle bookings with daily and time-slot limits, deadlines, cancellation and an audit trail |
| Database lab | Use `EXPLAIN ANALYZE` to compare the same query with and without a date index |

The website opens in English. Its **CN** switch displays Hong Kong Traditional Chinese. This README has a separate Simplified Chinese edition.

## Data sources

- Daily passenger traffic and pre-2021 festive-period figures come from the Hong Kong Immigration Department. The import can be run again to refresh the records. [Daily passenger traffic](https://data.gov.hk/en-data/dataset/hk-immd-set5-statistics-daily-passenger-traffic) · [Festive-period traffic](https://data.gov.hk/en-data/dataset/hk-immd-set5-statistics-passenger-traffic-festive-period)
- Official checkpoint wait categories come from the Immigration Department's waiting-time feed. [Land boundary checkpoint waiting times](https://data.gov.hk/en-data/dataset/hk-immd-set28-land-boundary-control-points-waiting-time)
- Minute-level flow, traveller identities, review notes, crossings, vehicles and bookings are demo data.

## Verify the project

```powershell
docker compose exec app php artisan traffic:reconcile LWS 2025-09-01
$env:OPERATOR_PASSWORD = Read-Host 'Enter the operator password from .env'
node tests/smoke.mjs
```

The smoke test needs Node.js 22+ and a demo database with available quotas. If you changed the operator account, set the matching `OPERATOR_USERNAME` in PowerShell. The test submits 200 requests across two slots on one day, expects eight successful bookings and cancels the test bookings afterward. Audit records are retained.

- [Technical report](docs/report.md)
- [Demo recording outline](docs/demo-script.md)
- [Technical report PDF](docs/BorderFlow-report.pdf)
