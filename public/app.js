'use strict';
const english = document.documentElement.lang === 'en';
const t = value => english ? (window.borderflowTranslations[value] ?? value) : value;
const checkpointChinese = {'Airport':'機場','China Ferry Terminal':'中國客運碼頭',
    'Express Rail Link West Kowloon':'高鐵西九龍站','Harbour Control':'港口管制','Hung Hom':'紅磡',
    'Kai Tak Cruise Terminal':'啟德郵輪碼頭','Macao Ferry Terminal':'港澳客輪碼頭',
    'Tuen Mun Ferry Terminal':'屯門客運碼頭'};
const pointName = point => english ? point.name : (point.chinese || checkpointChinese[point.name] || point.name);
const detailCode = document.body.dataset.detail;
const el = id => document.getElementById(id);
const operator = document.body.dataset.session === '1' && document.body.dataset.operator === '1';
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const number = value => value == null ? '—' : Number(value).toLocaleString(english ? 'en-HK' : 'zh-HK');
let selected = 'LWS';
let points = [];
let feedRequest = 0;
let historyRequest = 0;

function node(tag, value, className) {
    const element = document.createElement(tag);
    element.textContent = value ?? '';
    if (className) element.className = className;
    return element;
}
async function api(path, data) {
    const options = {headers:{Accept:'application/json'}};
    if (data !== undefined) {
        options.method = 'POST';
        options.headers['Content-Type'] = 'application/json';
        options.headers['X-CSRF-TOKEN'] = csrf;
        options.body = JSON.stringify(data);
    }
    const response = await fetch('/api/' + path, options);
    if (!response.ok) {
        const error = await response.json().catch(() => ({}));
        throw new Error(response.status >= 500 ? t('服務暫時無法回應') : t(error.message || '操作失敗'));
    }
    return response.json();
}
function act(task) {
    return async (...args) => {
        el('error').hidden = true;
        try { await task(...args); } catch (error) {
            el('error').textContent = error.message;
            el('error').hidden = false;
        }
    };
}
function checkpointLink(point) {
    const link = node('a',pointName(point),'detail-link');
    link.href = '/operations/checkpoints/' + encodeURIComponent(point.code);
    return link;
}
function bind(id, task) {
    el(id)?.addEventListener('submit', act(async event => {
        event.preventDefault();
        const button = event.target.querySelector('button');
        button.disabled = true;
        try { await task(Object.fromEntries(new FormData(event.target))); }
        finally { button.disabled = false; }
    }));
}
function action(label, task) {
    const button = node('button',label,'muted');
    button.type = 'button';
    button.addEventListener('click', act(task));
    return button;
}
function table(target, columns, rows) {
    const wrapper = node('div','','table');
    const result = node('table');
    const header = result.createTHead().insertRow();
    columns.forEach(label => header.append(node('th',label)));
    const body = result.createTBody();
    for (const values of rows) {
        const row = body.insertRow();
        for (const value of values) {
            const cell = row.insertCell();
            if (value instanceof Node) cell.append(value);
            else cell.textContent = value ?? '—';
        }
    }
    wrapper.append(result);
    el(target).replaceChildren(rows.length ? wrapper : node('p',t('暫無資料。')));
}
function chart(series, colors) {
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns,'svg');
    svg.setAttribute('viewBox','0 0 600 150');
    svg.setAttribute('role','img');
    svg.setAttribute('aria-label',t('數值趨勢'));
    const max = Math.max(1,...series.flat().filter(value => value != null));
    series.forEach((values,index) => {
        let segment = [];
        function drawSegment() {
            if (!segment.length) return;
            const line = document.createElementNS(ns,'polyline');
            line.setAttribute('points',segment.map(point => point.join(',')).join(' '));
            line.setAttribute('fill','none');
            line.setAttribute('stroke',colors[index]);
            line.setAttribute('stroke-width','3');
            svg.append(line);
            for (const [x,y] of segment) {
                const dot = document.createElementNS(ns,'circle');
                dot.setAttribute('cx',x);
                dot.setAttribute('cy',y);
                dot.setAttribute('r','2.5');
                dot.setAttribute('fill',colors[index]);
                svg.append(dot);
            }
            segment = [];
        }
        values.forEach((value,i) => {
            if (value == null) {
                drawSegment();
                return;
            }
            segment.push([10 + i * 580 / Math.max(values.length - 1, 1),140 - value * 130 / max]);
        });
        drawSegment();
    });
    return svg;
}
function filters() { return new URLSearchParams(new FormData(el('filters'))); }
async function history() {
    const request = ++historyRequest;
    const rows = await api('history?' + new URLSearchParams({code:selected,direction:filters().get('direction')}));
    if (request !== historyRequest) return;
    el('history-title').textContent = (pointName(points.find(point => point.code === selected) || {}) || '') + t(' · 歷史客流');
    table('history',[t('日期'),t('人次'),t('來源')],rows.slice(-7).reverse().map(row => [row.day,number(row.total),row.source === 'daily' ? t('每日客流') : t('節日客流')]));
    if (rows.length > 1) el('history').prepend(chart([rows.map(row => Number(row.total))],['#00796e']));
}
function renderPoints(audience) {
    const status = {0:audience === 'R' ? t('正常 · 通常少於 15 分鐘') : t('正常 · 通常少於 30 分鐘'),
        1:audience === 'R' ? t('繁忙 · 通常少於 30 分鐘') : t('繁忙 · 通常少於 45 分鐘'),
        2:audience === 'R' ? t('非常繁忙 · 通常 30 分鐘或以上') : t('非常繁忙 · 通常 45 分鐘或以上'),
        4:t('系統維護'),99:t('非服務時間')};
    const cards = points.map(point => {
        const button = action('', async () => {
            selected = point.code;
            renderPoints(audience);
            await history();
        });
        button.className = 'card';
        button.setAttribute('aria-pressed', String(point.code === selected));
        const detail = node('span',status[point.status] || t('等候資料未提供'),'status');
        detail.dataset.code = point.status;
        button.append(node('strong',pointName(point)),detail,node('small',t(point.transport)));
        return button;
    });
    el('points').replaceChildren(...cards);
    const ranked = points.filter(point => [0,1,2].includes(point.status)).sort((a,b) => a.status - b.status);
    const best = ranked.filter(point => point.status === ranked[0].status);
    el('comparison').textContent = best.length ? t('較低等候級別 · ') + best.map(point => pointName(point)).join('、') : '';
}
async function refresh() {
    const request = ++feedRequest;
    const params = filters();
    const result = await api('checkpoints?' + params);
    if (request !== feedRequest) return;
    points = result.points;
    el('feed').textContent = result.retrieved ?
        t('入境處等候狀態 · 擷取時間 ') + new Date(result.retrieved).toLocaleTimeString(english ? 'en-HK' : 'zh-HK',{timeZone:'Asia/Hong_Kong'}) + t(' 香港時間') :
        t('官方等候資料暫時無法取得。');
    renderPoints(params.get('audience'));
    const selector = el('simulate')?.elements.code;
    if (selector && !selector.options.length) {
        selector.replaceChildren(...points.map(point => new Option(pointName(point),point.code)));
        selector.value = selected;
    }
    await history();
}
async function travellers(q = '') {
    const rows = await api('ops/travellers?' + new URLSearchParams({q}));
    table('travellers',[t('姓名'),t('演示證件'),t('詳情')],rows.map(row => [
        t(row.display_name),row.demo_document,action(t('查看'),async () => {
            const detail = await api('ops/travellers/' + row.id);
            table('profile',[t('口岸'),t('日期時間'),t('方向')],detail.crossings.map(c => [pointName(c),c.occurred_at,c.direction === 'Arrival' ? t('入境') : t('離境')]));
            el('profile').prepend(node('h3',t(detail.profile.display_name) + t(' · 累計 ') + detail.count + t(' 次過關')),
                node('p',t(detail.profile.fixture_note || '示範檔案無覆核備註')));
        })
    ]));
}
function showSimulation(result) {
    const last = result.scenario.at(-1);
    const slider = node('input');
    slider.type = 'range';
    slider.min = '1';
    slider.max = '60';
    slider.value = '60';
    const cursor = node('output');
    const label = node('label',t('回放分鐘'));
    label.append(slider,cursor);
    const showMinute = () => {
        const row = result.scenario[Number(slider.value)-1];
        cursor.textContent = t('第 ') + row.minute + t(' 分鐘 · 隊列 ') + row.queue + t(' 人 · 預計等候 ') + row.wait + t(' 分鐘');
    };
    slider.addEventListener('input',showMinute);
    showMinute();
    table('simulation',[t('分鐘'),t('到達'),t('完成'),t('排隊'),t('情景等候'),t('基準等候')],result.scenario.filter(row => row.minute % 10 === 0).map(row => [
        row.minute,row.arrivals,row.served,number(row.queue),row.wait + t(' 分'),result.baseline[row.minute - 1].wait + t(' 分')
    ]));
    el('simulation').prepend(node('h3',t('第 60 分鐘 · 情景預計等候 ') + last.wait + t(' 分鐘')),
        label,node('p',t('橙色：增加人流情景。綠色：基準情景。')),chart([
            result.scenario.map(row => row.wait),result.baseline.map(row => row.wait)
        ],['#c75d26','#00796e']));
}
async function runs() {
    const rows = await api('ops/simulations');
    el('runs').replaceChildren(...rows.map(row => action('#' + row.id + ' ' + row.checkpoint_code,() => showSimulation(JSON.parse(row.results)))));
}
async function bookings() {
    const [slots, rows] = await Promise.all([api('ops/slots'),api('ops/bookings')]);
    const selector = el('book').elements.slot;
    const current = selector.value;
    selector.replaceChildren(...slots.map(slot => {
        const option = new Option(slot.day + ' ' + slot.opens.slice(0,5) + t(' · 餘 ') + slot.remaining,slot.id);
        option.disabled = Number(slot.remaining) === 0;
        return option;
    }));
    if (Array.from(selector.options).some(option => option.value === current && !option.disabled)) selector.value = current;
    table('bookings',[t('車輛'),t('日期'),t('狀態'),t('操作')],rows.map(row => {
        const tools = node('div');
        tools.append(action(t('稽核'),async () => {
            const entries = await api('ops/bookings/' + row.id + '/audit');
            table('audit',[t('狀態'),t('時間')],entries.map(entry => [entry.status,entry.changed_at]));
        }));
        if (row.status === 'booked') {
            for (const [status,label] of [['cancelled',t('取消')],['used',t('已使用')],['no_show',t('未到')]]) {
                tools.append(action(label,async () => {
                    await api('ops/bookings/' + row.id + '/status',{status});
                    await bookings();
                }));
            }
        }
        return [row.vehicle,row.day + ' ' + row.opens.slice(0,5),row.status,tools];
    }));
}
bind('login',async data => { await api('login',data); location.reload(); });
el('logout')?.addEventListener('click',act(async () => { await api('logout',{}); location.reload(); }));
if (el('filters')) {
    el('filters').addEventListener('change',act(refresh));
    act(refresh)();
    setInterval(act(refresh),60000);
}
if (operator && !detailCode) {
    bind('search',data => travellers(data.q));
    bind('simulate',async data => { showSimulation(await api('ops/simulations',data)); await runs(); });
    bind('book',async data => { await api('ops/bookings',data); await bookings(); });
    [travellers,runs,bookings].forEach(task => act(task)());
    act(async () => {
        const result = await api('ops/statistics');
        table('statistics',[result.day || t('口岸'),t('入境人次'),t('離境人次')],result.rows.map(row => [checkpointLink(row),number(row.arrivals),number(row.departures)]));
    })();
}

async function crossingRows(input = {}) {
    const params = new URLSearchParams(Object.entries(input).filter(([,value]) => value));
    const rows = await api('ops/crossings?' + params);
    table('crossing-table',[t('旅客'),t('口岸'),t('時間'),t('方向')],rows.map(row => [
        t(row.display_name),pointName(row),row.occurred_at,row.direction === 'Arrival' ? t('入境') : t('離境')
    ]));
}
if (operator && !detailCode) {
    bind('crossing-filter',crossingRows);
    act(crossingRows)();
    act(loadLiveCheckpoints)();
    el('explain').addEventListener('click',act(async () => {
        const result = await api('ops/database-lab');
        el('plans').replaceChildren(...(result.day ? [
            node('p',t('查詢日期 ') + result.day),
            ...result.plans.flatMap(plan => [node('h3',t(plan.label)),node('pre',plan.sql+'\n'+plan.plan)])
        ] : [node('p',t('請先匯入官方客流。'))]));
    }));
}

async function loadLiveCheckpoints() {
    const selector = el('live-checkpoint');
    const checkpoints = await api('ops/checkpoints');
    selector.replaceChildren(...checkpoints.map(point => new Option(pointName(point),point.code)));
    if (checkpoints.some(point => point.code === 'LWS')) selector.value = 'LWS';
    let request = 0;
    const update = act(async () => {
        const pending = ++request;
        const data = await api('ops/checkpoints/' + encodeURIComponent(selector.value) + '/minute');
        if (pending !== request) return;
        table('live-flow',[t('時間'),t('入境人次'),t('離境人次')],[
            [data.minute + t(' 香港時間'),number(data.arrivals),number(data.departures)]
        ]);
    });
    selector.addEventListener('change',update);
    await update();
    setInterval(update,10000);
}

async function checkpointDetails() {
    const details = await api('ops/checkpoints/' + encodeURIComponent(detailCode) + '/details');
    el('checkpoint-title').textContent = pointName(details.point);
    const columns = [t('日期'),t('入境人次'),t('離境人次')];
    const cells = row => [row.day,number(row.arrivals),number(row.departures)];
    if (details.today) {
        table('checkpoint-today',columns,[cells(details.today)]);
    } else {
        el('checkpoint-today').textContent = details.date + ' · ' + t('今日官方數據尚未發佈。');
    }
    table('checkpoint-week',columns,details.lastSeven.map(cells));
    el('checkpoint-week').prepend(chart([
        details.lastSeven.map(row => row.arrivals),details.lastSeven.map(row => row.departures)
    ],['#00796e','#c75d26']));
    table('checkpoint-history',columns,details.history.map(cells));
    const rows = await api('ops/crossings?' + new URLSearchParams({code:detailCode,day:details.date}));
    table('checkpoint-crossings',[t('旅客'),t('演示證件'),t('時間'),t('方向')],rows.map(row => [
        t(row.display_name),row.demo_document,row.occurred_at,row.direction === 'Arrival' ? t('入境') : t('離境')
    ]));
}

if (operator && detailCode) {
    const button = el('toggle-crossings');
    const panel = el('checkpoint-crossing-panel');
    button.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        button.setAttribute('aria-expanded',String(!panel.hidden));
    });
    act(checkpointDetails)();
}