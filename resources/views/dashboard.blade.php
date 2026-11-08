<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>BorderFlow · {{ $operator ? '營運控制台' : '口岸出行' }}</title>
<link rel="stylesheet" href="/style.css"><script src="/app.js" defer></script>
</head>
<body data-operator="{{ $operator ? '1' : '0' }}" data-session="{{ session('operator') ? '1' : '0' }}">
<header><a class="brand" href="/"><span>↗</span> BorderFlow</a><nav><a href="/">旅客出行</a><a href="/operations">營運控制台</a></nav></header>
<main>
<section class="hero"><div class="eyebrow">HONG KONG · BORDER INTELLIGENCE</div>
<h1>{{ $operator ? '口岸營運控制台' : '掌握口岸情況，安排跨境行程' }}</h1>
<p>{{ $operator ? '監察等候狀態、查閱示範記錄與測試排隊情景。' : '查看陸路口岸的官方等候狀態、交通接駁與每日客流。' }}</p></section>
<p id="error" role="alert" hidden></p>
@if($operator && !session('operator'))
<section class="panel"><h2>營運人員登入</h2><form id="login"><label>演示密碼 <input required type="password" name="password" autocomplete="current-password"></label><button>登入</button></form></section>
@else
@if($operator)<button id="logout" class="muted">登出</button>@endif
<section class="panel"><div class="section-top"><div><h2>口岸等候概況</h2><p id="feed" aria-live="polite">正在取得等候狀態…</p></div>
<form id="filters"><label>方向 <select name="direction"><option value="Arrival">入境香港</option><option value="Departure">離境香港</option></select></label>
<label>旅客類型 <select name="audience"><option value="R">香港居民</option><option value="V">訪港旅客</option></select></label></form></div><div id="points" class="cards"></div><p id="comparison" aria-live="polite"></p></section>
<section class="panel"><h2 id="history-title">歷史客流</h2><p>點選口岸查看最近 28 筆官方每日客流。</p><div id="history" aria-live="polite"></div></section>
@if($operator)
<section class="panel"><h2>官方每日統計</h2><div id="statistics"></div></section>
<section class="panel"><h2>示範旅客查詢</h2><p>虛構身份與人工覆核案例。</p><form id="search"><label>姓名或演示證件 <input name="q" maxlength="60"></label><button>搜尋</button></form><div id="travellers"></div><div id="profile" aria-live="polite"></div></section>
<section class="panel"><h2>示範過關流水</h2>
<form id="crossing-filter"><label>口岸代碼 <input name="code" placeholder="LWS"></label>
<label>日期 <input name="day" type="date"></label><label>方向 <select name="direction"><option value="">全部</option><option value="Arrival">入境</option><option value="Departure">離境</option></select></label><button>篩選</button></form>
<div id="crossing-table"></div></section>
<section class="panel"><h2>60 分鐘人流壓力測試</h2><p>增加量會在設定時間內逐步加入。每條通道假設每分鐘處理 4 人。</p>
<form id="simulate"><label>口岸 <select name="code" required></select></label>
<label>基準到達／分鐘 <input name="base" type="number" min="0" max="1500" value="30" required></label>
<label>額外到達／分鐘 <input name="increase" type="number" min="0" max="1500" value="35" required></label>
<label>爬升分鐘 <input name="ramp" type="number" min="1" max="60" value="20" required></label>
<label>開放通道 <input name="lanes" type="number" min="1" max="100" value="12" required></label>
<label>初始隊列 <input name="initial" type="number" min="0" max="10000" value="0" required></label><button>運行模擬</button></form>
<div id="simulation" aria-live="polite"></div><h3>最近情景</h3><div id="runs"></div></section>
<section class="panel"><h2>車輛配額預約</h2><p>港珠澳大橋課程沙盒。以香港時間計算，預約於出行前一天中午截止。</p>
<form id="book"><label>日期與時段 <select name="slot" required></select></label><label>演示車輛 <input name="vehicle" placeholder="DEMO-CAR1" pattern="DEMO-[A-Z0-9]{1,16}" required></label><button>建立預約</button></form>
<div id="bookings"></div><div id="audit" aria-live="polite"></div></section>
@endif
@endif
@if($operator && session('operator'))
<section class="panel"><h2>資料庫實驗室</h2><p>針對同一天的官方入境資料，比較 MySQL 實際執行計畫。</p><button type="button" id="explain">執行查詢比較</button><div id="plans"></div></section>
@endif
</main><footer>BorderFlow · 課程示範 · 官方資料來源：香港入境事務處</footer>
</body></html>
