# BorderFlow · Hong Kong Checkpoint Dashboard

**EN** | [CN · 简体中文](README.zh-CN.md)

BorderFlow is a CityU database application project. The traveller dashboard shows official checkpoint wait categories, transport connections and historical passenger traffic. The password-protected operations dashboard adds demo traveller records, vehicle quota bookings, queue simulations and MySQL execution plans.

## Dashboard previews

### Traveller dashboard

![Traveller dashboard](img/Traveller%20dashboard.png)

### Operations overview

![Operations overview](img/Operations%20dashboard%201.png)

### Historical passenger traffic

![Historical passenger traffic](img/Operations%20dashboard%202.png)

### Simulated minute-level flow and official daily statistics

![Simulated minute-level flow and official daily statistics](img/Operations%20dashboard%203.png)

### Demo traveller lookup, crossing records and queue simulation

![Demo traveller lookup, crossing records and queue simulation](img/Operations%20dashboard%204.png)

### Vehicle quota bookings and database lab

![Vehicle quota bookings and database lab](img/Operations%20dashboard%205.png)

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

## Run locally on macOS

Start Docker Desktop for Mac. In Terminal, go to the project folder containing `compose.yaml`. On the first setup, run:

```sh
if [ ! -f .env ]; then cp .env.example .env; fi
if grep -q '^APP_KEY=$' .env; then
  key=$(openssl rand -base64 32) &&
    sed "s|^APP_KEY=$|APP_KEY=base64:$key|" .env > .env.tmp &&
    mv .env.tmp .env
fi
nano .env
```

Set `OPERATOR_PASSWORD` to at least 12 characters. The default account is `operator`. Save with **Ctrl+O**, press **Enter**, then exit with **Ctrl+X**.

```sh
docker compose up -d --build
docker compose ps
```

Once the containers are running, run the following from the same directory to download and import the official data:

```sh
(
  set -e
  mkdir -p data
  base='https://www.immd.gov.hk/opendata/eng/transport/immigration_clearance'
  curl -fsSL "$base/statistics_on_daily_passenger_traffic.csv" -o data/daily.csv
  curl -fsSL "$base/statistics_passenger_traffic_festival_periods.csv" -o data/festival.csv

  for source in daily festival; do
    docker compose cp "data/$source.csv" "app:/tmp/$source.csv"
    docker compose exec -T app php artisan traffic:import "/tmp/$source.csv" "$source"
  done
)
```

Open the [traveller dashboard](http://127.0.0.1:8000/) or the [operations dashboard](http://127.0.0.1:8000/operations). To start the existing deployment again on this Mac, run `docker compose up -d --no-build --pull never`. Run `docker compose down` to stop it and retain the database.

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
