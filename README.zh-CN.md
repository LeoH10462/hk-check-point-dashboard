# BorderFlow · 香港口岸客流看板

[EN · English](README.md) | **CN · 简体中文**

BorderFlow 是香港城市大学的数据库应用课程项目。旅客端展示官方等候等级、交通接驳与历史客流。需要登录的运营端提供演示旅客记录、车辆配额预约、排队模拟和 MySQL 执行计划。

## 界面预览

### 旅客端

![旅客端](img/Traveller%20dashboard.png)

### 运营端总览

![运营端总览](img/Operations%20dashboard%201.png)

### 历史客流

![历史客流](img/Operations%20dashboard%202.png)

### 模拟分钟客流与官方每日统计

![模拟分钟客流与官方每日统计](img/Operations%20dashboard%203.png)

### 演示旅客查询、过关记录与排队模拟

![演示旅客查询、过关记录与排队模拟](img/Operations%20dashboard%204.png)

### 车辆配额预约与数据库实验室

![车辆配额预约与数据库实验室](img/Operations%20dashboard%205.png)

## 在 Windows 本机运行

启动 Docker Desktop，在包含 `compose.yaml` 的项目目录打开 PowerShell，运行：

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

在 `.env` 中设置至少 12 个字符的 `OPERATOR_PASSWORD`，保存并关闭记事本。默认账号为 `operator`，可通过 `OPERATOR_USERNAME` 修改。

```powershell
docker compose up -d --build
powershell -ExecutionPolicy Bypass -File .\scripts\import-traffic.ps1
```

打开[旅客端](http://127.0.0.1:8000/)或[运营端](http://127.0.0.1:8000/operations)。首次构建会下载 Docker 镜像和 Composer 依赖。同一台电脑准备好镜像和数据后，可用 `docker compose up -d --no-build --pull never` 启动现有部署。运行 `docker compose down` 可停止服务并保留 MySQL 数据。

## 在 macOS 本机运行

启动 Docker Desktop for Mac。在终端中进入包含 `compose.yaml` 的项目目录。首次配置时运行：

```sh
if [ ! -f .env ]; then cp .env.example .env; fi
if grep -q '^APP_KEY=$' .env; then
  key=$(openssl rand -base64 32) &&
    sed "s|^APP_KEY=$|APP_KEY=base64:$key|" .env > .env.tmp &&
    mv .env.tmp .env
fi
nano .env
```

将 `OPERATOR_PASSWORD` 设置为至少 12 个字符。默认账号是 `operator`。按 **Ctrl+O**，再按 **Enter** 保存，最后按 **Ctrl+X** 退出。

```sh
docker compose up -d --build
docker compose ps
```

容器启动后，在同一目录运行以下命令，下载并导入官方数据：

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

打开[旅客端](http://127.0.0.1:8000/)或[运营端](http://127.0.0.1:8000/operations)。以后在同一台 Mac 上启动现有部署时，可运行 `docker compose up -d --no-build --pull never`。运行 `docker compose down` 可停止服务并保留数据库。

### 升级现有数据库

更新应用后，在 Compose 项目运行时执行 `powershell -ExecutionPolicy Bypass -File .\scripts\upgrade-utf8.ps1`，修复早期版本的中文初始数据并应用口岸名称更新。已导入的客流、预约和模拟情景会保留。

## 功能

| 模块 | 功能 |
| --- | --- |
| 旅客端 | 比较口岸等候等级，切换出入境方向及旅客类型，查看交通接驳和历史客流 |
| 运营端 | 查看官方日流量、口岸详情和七天趋势，筛选演示旅客和过关记录，查看模拟分钟客流 |
| 排队模拟 | 设置人流增长、开放通道和初始队列，对比 60 分钟情景与基准，回放已保存的结果 |
| 配额预约 | 创建演示车辆预约，管理日配额、时段限额、截止时间、取消和审计记录 |
| 数据库实验室 | 使用 `EXPLAIN ANALYZE` 比较同一查询使用和忽略日期索引时的执行计划 |

网站默认显示英文，网站里的 **CN** 切换为香港繁体中文。本 README 提供独立的简体中文版。

## 数据来源

- 每日客流和 2021 年前的节日客流来自香港入境事务处。可重新运行导入脚本更新记录。 [每日客流](https://data.gov.hk/en-data/dataset/hk-immd-set5-statistics-daily-passenger-traffic) · [节日客流](https://data.gov.hk/en-data/dataset/hk-immd-set5-statistics-passenger-traffic-festive-period)
- 官方口岸等候等级来自入境事务处的等候状态服务。 [陆路口岸等候时间](https://data.gov.hk/en-data/dataset/hk-immd-set28-land-boundary-control-points-waiting-time)
- 分钟客流、旅客身份、复核备注、过关记录、车辆和预约均为演示数据。

## 验证项目

```powershell
docker compose exec app php artisan traffic:reconcile LWS 2025-09-01
$env:OPERATOR_PASSWORD = Read-Host '输入 .env 中的运营密码'
node tests/smoke.mjs
```

测试需要 Node.js 22+ 和有可用配额的演示数据库。如果修改过运营账号，请在 PowerShell 中设置对应的 `OPERATOR_USERNAME`。测试向同一天的两个时段发起 200 个预约请求，预期成功 8 个，随后取消测试预约并保留审计记录。

- [技术报告](docs/report.md)
- [演示录制流程](docs/demo-script.md)
- [技术报告 PDF](docs/BorderFlow-report.pdf)
