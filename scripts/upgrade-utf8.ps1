$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)
docker compose cp database/04-utf8.sql db:/tmp/borderflow-utf8.sql
if ($LASTEXITCODE -ne 0) { throw 'Could not copy the UTF-8 upgrade.' }
docker compose exec -T db sh -c 'mysql --default-character-set=utf8mb4 --user="$MYSQL_USER" --password="$MYSQL_PASSWORD" "$MYSQL_DATABASE" < /tmp/borderflow-utf8.sql'
if ($LASTEXITCODE -ne 0) { throw 'Could not apply the UTF-8 upgrade.' }
docker compose cp database/05-checkpoint-names.sql db:/tmp/borderflow-checkpoint-names.sql
if ($LASTEXITCODE -ne 0) { throw 'Could not copy the checkpoint name upgrade.' }
docker compose exec -T db sh -c 'mysql --default-character-set=utf8mb4 --user="$MYSQL_USER" --password="$MYSQL_PASSWORD" "$MYSQL_DATABASE" < /tmp/borderflow-checkpoint-names.sql'
if ($LASTEXITCODE -ne 0) { throw 'Could not apply the checkpoint name upgrade.' }
