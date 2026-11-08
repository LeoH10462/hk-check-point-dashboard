$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)
New-Item -ItemType Directory -Force data | Out-Null
$base = 'https://www.immd.gov.hk/opendata/eng/transport/immigration_clearance'
$sources = @{
    daily = 'statistics_on_daily_passenger_traffic.csv'
    festival = 'statistics_passenger_traffic_festival_periods.csv'
}
foreach ($source in 'daily','festival') {
    $local = "data/$source.csv"
    $target = "/tmp/$source.csv"
    Invoke-WebRequest "$base/$($sources[$source])" -OutFile $local
    docker compose cp $local "app:$target"
    if ($LASTEXITCODE -ne 0) { throw "Could not copy $source into app container." }
    docker compose exec -T app php artisan traffic:import $target $source
    if ($LASTEXITCODE -ne 0) { throw "Could not import $source." }
}
