@php
    $locale = request('lang');
    if (in_array($locale, ['en', 'zh-HK'], true)) {
        session(['dashboard_locale' => $locale]);
    }
    $locale = session('dashboard_locale', 'en');
    $translations = json_decode(file_get_contents(resource_path('translations.json')), true, flags: JSON_THROW_ON_ERROR);
    $t = fn($text) => $locale === 'en' ? ($translations[$text] ?? $text) : $text;
@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>BorderFlow · {{ $operator ? $t('營運控制台') : $t('口岸出行') }}</title>
<script>window.borderflowTranslations = {{ Illuminate\Support\Js::from($translations) }};</script>
<link rel="stylesheet" href="/style.css?v=2"><script src="/app.js" defer></script>
</head>
<body data-detail="{{ $detailCode ?? '' }}" data-operator="{{ $operator ? '1' : '0' }}" data-session="{{ session('operator') ? '1' : '0' }}">
<header><a class="brand" href="/"><span>↗</span> BorderFlow</a><nav><a href="/">{{ $t('旅客出行') }}</a><a href="/operations">{{ $t('營運控制台') }}</a><span class="language" aria-label="Language"><a href="?lang=zh-HK" hreflang="zh-HK" @if($locale === 'zh-HK') aria-current="true" @endif>CN</a> | <a href="?lang=en" hreflang="en" @if($locale === 'en') aria-current="true" @endif>EN</a></span></nav></header>
<main>
<section class="hero"><div class="eyebrow">HONG KONG · BORDER INTELLIGENCE</div>
<h1>{{ $operator ? $t('口岸營運控制台') : $t('掌握口岸情況，安排跨境行程') }}</h1>
<p>{{ $operator ? $t('監察等候狀態、查閱示範記錄與測試排隊情景。') : $t('查看陸路口岸的官方等候狀態、交通接駁與每日客流。') }}</p></section>
<p id="error" role="alert" hidden></p>
@if($operator && !session('operator'))
<section class="panel"><h2>{{ $t('營運人員登入') }}</h2><form id="login"><label>{{ $t('帳戶') }} <input required name="username" maxlength="80" autocomplete="username"></label><label>{{ $t('演示密碼') }} <input required type="password" name="password" autocomplete="current-password"></label><button>{{ $t('登入') }}</button></form></section>
@elseif(isset($detailCode))
<section class="panel">
<a class="detail-link" href="/operations">{{ $t('返回營運控制台') }}</a>
<h2 id="checkpoint-title">{{ $t('口岸') }}</h2>
<h3>{{ $t('今日官方流量') }}</h3><div id="checkpoint-today"></div>
<h3>{{ $t('過去 7 天官方流量') }}</h3><p>{{ $t('綠色為入境，橙色為離境。缺少資料的日期會留空。') }}</p><div id="checkpoint-week"></div>
<h3>{{ $t('最近 30 筆官方日報') }}</h3><div id="checkpoint-history"></div>
</section>
<section class="panel">
<button id="toggle-crossings" type="button" aria-expanded="true" aria-controls="checkpoint-crossing-panel">{{ $t('出入境統計') }}</button>
<div id="checkpoint-crossing-panel"><h3>{{ $t('今日示範出入境旅客') }}</h3><div id="checkpoint-crossings"></div></div>
</section>
@else
@if($operator)<button id="logout" class="muted">{{ $t('登出') }}</button>@endif
<section class="panel"><div class="section-top"><div><h2>{{ $t('口岸等候概況') }}</h2><p id="feed" aria-live="polite">{{ $t('正在取得等候狀態…') }}</p></div>
<form id="filters"><label>{{ $t('方向') }} <select name="direction"><option value="Arrival">{{ $t('入境香港') }}</option><option value="Departure">{{ $t('離境香港') }}</option></select></label>
<label>{{ $t('旅客類型') }} <select name="audience"><option value="R">{{ $t('香港居民') }}</option><option value="V">{{ $t('訪港旅客') }}</option></select></label></form></div><div id="points" class="cards"></div><p id="comparison" aria-live="polite"></p></section>
<section class="panel"><h2 id="history-title">{{ $t('歷史客流') }}</h2><p>{{ $t('點選口岸查看最近 28 筆官方每日客流。') }}</p><div id="history" aria-live="polite"></div></section>
@if($operator)
<section class="panel"><h2>{{ $t('即時出入境流量') }}</h2>
<p>{{ $t('模擬分鐘流量 · 香港時間') }}</p>
<label class="live-selector">{{ $t('口岸') }} <select id="live-checkpoint"></select></label>
<div id="live-flow" aria-live="polite"></div></section>
<section class="panel"><h2>{{ $t('官方每日統計') }}</h2><div id="statistics"></div></section>
<section class="panel"><h2>{{ $t('示範旅客查詢') }}</h2><p>{{ $t('虛構身份與人工覆核案例。') }}</p><form id="search"><label>{{ $t('姓名或演示證件') }} <input name="q" maxlength="60"></label><button>{{ $t('搜尋') }}</button></form><div id="travellers"></div><div id="profile" aria-live="polite"></div></section>
<section class="panel"><h2>{{ $t('示範過關流水') }}</h2>
<form id="crossing-filter"><label>{{ $t('口岸代碼') }} <input name="code" placeholder="LWS"></label>
<label>{{ $t('日期') }} <input name="day" type="date"></label><label>{{ $t('方向') }} <select name="direction"><option value="">{{ $t('全部') }}</option><option value="Arrival">{{ $t('入境') }}</option><option value="Departure">{{ $t('離境') }}</option></select></label><button>{{ $t('篩選') }}</button></form>
<div id="crossing-table"></div></section>
<section class="panel"><h2>{{ $t('60 分鐘人流壓力測試') }}</h2><p>{{ $t('增加量會在設定時間內逐步加入。每條通道假設每分鐘處理 4 人。') }}</p>
<form id="simulate"><label>{{ $t('口岸') }} <select name="code" required></select></label>
<label>{{ $t('基準到達／分鐘') }} <input name="base" type="number" min="0" max="1500" value="30" required></label>
<label>{{ $t('額外到達／分鐘') }} <input name="increase" type="number" min="0" max="1500" value="35" required></label>
<label>{{ $t('爬升分鐘') }} <input name="ramp" type="number" min="1" max="60" value="20" required></label>
<label>{{ $t('開放通道') }} <input name="lanes" type="number" min="1" max="100" value="12" required></label>
<label>{{ $t('初始隊列') }} <input name="initial" type="number" min="0" max="10000" value="0" required></label><button>{{ $t('運行模擬') }}</button></form>
<div id="simulation" aria-live="polite"></div><h3>{{ $t('最近情景') }}</h3><div id="runs"></div></section>
<section class="panel"><h2>{{ $t('車輛配額預約') }}</h2><p>{{ $t('港珠澳大橋課程沙盒。以香港時間計算，預約於出行前一天中午截止。') }}</p>
<form id="book"><label>{{ $t('日期與時段') }} <select name="slot" required></select></label><label>{{ $t('演示車輛') }} <input name="vehicle" placeholder="DEMO-CAR1" pattern="DEMO-[A-Z0-9]{1,16}" required></label><button>{{ $t('建立預約') }}</button></form>
<div id="bookings"></div><div id="audit" aria-live="polite"></div></section>
@endif
@endif
@if($operator && session('operator') && !isset($detailCode))
<section class="panel"><h2>{{ $t('資料庫實驗室') }}</h2><p>{{ $t('針對同一天的官方入境資料，比較 MySQL 實際執行計畫。') }}</p><button type="button" id="explain">{{ $t('執行查詢比較') }}</button><div id="plans"></div></section>
@endif
</main><footer>{{ $t('BorderFlow · 課程示範 · 官方資料來源：香港入境事務處') }}</footer>
</body></html>
