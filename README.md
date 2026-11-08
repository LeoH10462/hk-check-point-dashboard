# BorderFlow 香港口岸出行與營運控制台

BorderFlow 是 CityU 的資料庫應用課程專案。旅客端查看入境事務處等候級別、交通接駁方式和歷史客流。營運端提供受密碼保護的虛構旅客與過關流水、車輛配額、排隊壓力測試與 SQL 執行計畫。

## 本機啟動

需要 Docker Desktop 與網絡連線。在 Windows PowerShell 進入專案資料夾後執行：

```powershell
Copy-Item .env.example .env
$key = [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
(Get-Content .env) -replace '^APP_KEY=$', "APP_KEY=base64:$key" | Set-Content .env
notepad .env
docker compose up -d --build
./scripts/import-traffic.ps1
```

在 `.env` 設定至少 12 個字元的 `OPERATOR_PASSWORD` 後再執行 Compose。旅客端為 [localhost:8000](http://localhost:8000)，營運端為 [localhost:8000/operations](http://localhost:8000/operations)。Compose 預設只綁定本機。第一次建置需要下載 Docker 映像與 Composer 套件。若 Windows 指令碼政策阻止執行匯入指令，可從 PowerShell 以 `powershell -ExecutionPolicy Bypass -File scripts/import-traffic.ps1` 執行該單一指令碼。

`docker compose down` 停止服務並保留 MySQL 資料。初始化 SQL 會在建立空資料庫時依檔名順序執行。

## 功能分區

| 區域 | 功能 |
|---|---|
| 旅客出行 | 入境／離境、居民／訪客切換，官方等待級別與較低等待級別的口岸比較，接駁交通方式與 28 筆每日客流走勢 |
| 營運控制台 | 上述口岸資訊、最新官方日報、虛構旅客檔案及過關記錄、過關流水篩選 |
| 壓力測試 | 60 分鐘到達量爬升、通道容量和初始隊列設定、基準曲線、分鐘回放與情景留存 |
| 配額預約 | 演示車輛預約、日／時段配額、狀態與截止時間檢查、取消後釋放配額、稽核記錄 |
| 資料庫實驗室 | 以 `EXPLAIN ANALYZE` 比較同一查詢忽略與使用日期索引的計畫 |

## 資料來源

- [香港入境事務處每日客流](https://data.gov.hk/en-data/dataset/hk-immd-set5-statistics-daily-passenger-traffic) 與 [節日客流](https://data.gov.hk/en-data/dataset/hk-immd-set5-statistics-passenger-traffic-festive-period)。匯入指令可重跑，節日資料僅取 2021 年前的記錄。歷史圖使用每日總人次。
- [陸路口岸等候狀態](https://data.gov.hk/en-data/dataset/hk-immd-set28-land-boundary-control-points-waiting-time)。資料服務每 15 分鐘更新，頁面每分鐘刷新，後端快取五分鐘。畫面顯示本系統擷取時間和官方級別。服務中斷時顯示暫不可用。
- 所有個人身分、覆核備註、過關流水、車輛及預約均為課程示範。模擬參數為情景假設，接駁資料展示交通方式。

## 驗證

```powershell
docker compose exec app php artisan traffic:reconcile LWS 2025-09-01
$env:OPERATOR_PASSWORD = Read-Host '輸入 .env 裡的營運密碼'
node tests/smoke.mjs
```

測試需要 Node.js 22+ 和新建的演示資料庫。測試對同一天的兩個時段同時發出 200 個預約請求，預期接納 8 個，隨後取消已建立的測試預約。各筆記錄仍留在稽核表。

- [技術報告](docs/report.md)
- [錄影流程](docs/demo-script.md)
- [PDF 版本](docs/BorderFlow-report.pdf)
