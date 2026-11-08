'use strict';
const el = id => document.getElementById(id);
const operator = document.body.dataset.session === '1' && document.body.dataset.operator === '1';
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const number = value => Number(value).toLocaleString('zh-HK');
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
        throw new Error(response.status >= 500 ? '服務暫時無法回應' : error.message || '操作失敗');
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
    el(target).replaceChildren(rows.length ? wrapper : node('p','暫無資料。'));
}
function chart(series, colors) {
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns,'svg');
    svg.setAttribute('viewBox','0 0 600 150');
    svg.setAttribute('role','img');
    svg.setAttribute('aria-label','數值趨勢');
    const max = Math.max(1,...series.flat());
    series.forEach((values,index) => {
        const line = document.createElementNS(ns,'polyline');
        line.setAttribute('points', values.map((value,i) => [
            10 + i * 580 / Math.max(values.length - 1, 1), 140 - value * 130 / max
        ].join(',')).join(' '));
        line.setAttribute('fill','none');
        line.setAttribute('stroke',colors[index]);
        line.setAttribute('stroke-width','3');
        svg.append(line);
    });
    return svg;
}
function filters() { return new URLSearchParams(new FormData(el('filters'))); }
async function history() {
    const request = ++historyRequest;
    const rows = await api('history?' + new URLSearchParams({code:selected,direction:filters().get('direction')}));
    if (request !== historyRequest) return;
    el('history-title').textContent = (points.find(point => point.code === selected)?.chinese || '') + ' · 歷史客流';
    table('history',['日期','人次','來源'],rows.slice(-7).reverse().map(row => [row.day,number(row.total),row.source === 'daily' ? '每日客流' : '節日客流']));
    if (rows.length > 1) el('history').prepend(chart([rows.map(row => Number(row.total))],['#00796e']));
}
function renderPoints(audience) {
    const status = {0:audience === 'R' ? '正常 · 通常少於 15 分鐘' : '正常 · 通常少於 30 分鐘',
        1:audience === 'R' ? '繁忙 · 通常少於 30 分鐘' : '繁忙 · 通常少於 45 分鐘',
        2:audience === 'R' ? '非常繁忙 · 通常 30 分鐘或以上' : '非常繁忙 · 通常 45 分鐘或以上',
        4:'系統維護',99:'非服務時間'};
    const cards = points.map(point => {
        const button = action('', async () => {
            selected = point.code;
            renderPoints(audience);
            await history();
        });
        button.className = 'card';
        button.setAttribute('aria-pressed', String(point.code === selected));
        const detail = node('span',status[point.status] || '等候資料未提供','status');
        detail.dataset.code = point.status;
        button.append(node('strong',point.chinese),detail,node('small',point.transport));
        return button;
    });
    el('points').replaceChildren(...cards);
    const ranked = points.filter(point => [0,1,2].includes(point.status)).sort((a,b) => a.status - b.status);
    const best = ranked.filter(point => point.status === ranked[0].status);
    el('comparison').textContent = best.length ? '較低等候級別 · ' + best.map(point => point.chinese).join('、') : '';
}
async function refresh() {
    const request = ++feedRequest;
    const params = filters();
    const result = await api('checkpoints?' + params);
    if (request !== feedRequest) return;
    points = result.points;
    el('feed').textContent = result.retrieved ?
        '入境處等候狀態 · 擷取時間 ' + new Date(result.retrieved).toLocaleTimeString('zh-HK',{timeZone:'Asia/Hong_Kong'}) + ' 香港時間' :
        '官方等候資料暫時無法取得。';
    renderPoints(params.get('audience'));
    const selector = el('simulate')?.elements.code;
    if (selector && !selector.options.length) {
        selector.replaceChildren(...points.map(point => new Option(point.chinese,point.code)));
        selector.value = selected;
    }
    await history();
}
async function travellers(q = '') {
    const rows = await api('ops/travellers?' + new URLSearchParams({q}));
    table('travellers',['姓名','演示證件','詳情'],rows.map(row => [
        row.display_name,row.demo_document,action('查看',async () => {
            const detail = await api('ops/travellers/' + row.id);
            table('profile',['口岸','日期時間','方向'],detail.crossings.map(c => [c.chinese,c.occurred_at,c.direction === 'Arrival' ? '入境' : '離境']));
            el('profile').prepend(node('h3',detail.profile.display_name + ' · 累計 ' + detail.count + ' 次過關'),
                node('p',detail.profile.fixture_note || '示範檔案無覆核備註'));
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
    const label = node('label','回放分鐘');
    label.append(slider,cursor);
    const showMinute = () => {
        const row = result.scenario[Number(slider.value)-1];
        cursor.textContent = '第 ' + row.minute + ' 分鐘 · 隊列 ' + row.queue + ' 人 · 預計等候 ' + row.wait + ' 分鐘';
    };
    slider.addEventListener('input',showMinute);
    showMinute();
    table('simulation',['分鐘','到達','完成','排隊','情景等候','基準等候'],result.scenario.filter(row => row.minute % 10 === 0).map(row => [
        row.minute,row.arrivals,row.served,number(row.queue),row.wait + ' 分',result.baseline[row.minute - 1].wait + ' 分'
    ]));
    el('simulation').prepend(node('h3','第 60 分鐘 · 情景預計等候 ' + last.wait + ' 分鐘'),
        label,node('p','橙色：增加人流情景。綠色：基準情景。'),chart([
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
        const option = new Option(slot.day + ' ' + slot.opens.slice(0,5) + ' · 餘 ' + slot.remaining,slot.id);
        option.disabled = Number(slot.remaining) === 0;
        return option;
    }));
    if (Array.from(selector.options).some(option => option.value === current && !option.disabled)) selector.value = current;
    table('bookings',['車輛','日期','狀態','操作'],rows.map(row => {
        const tools = node('div');
        tools.append(action('稽核',async () => {
            const entries = await api('ops/bookings/' + row.id + '/audit');
            table('audit',['狀態','時間'],entries.map(entry => [entry.status,entry.changed_at]));
        }));
        if (row.status === 'booked') {
            for (const [status,label] of [['cancelled','取消'],['used','已使用'],['no_show','未到']]) {
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
if (operator) {
    bind('search',data => travellers(data.q));
    bind('simulate',async data => { showSimulation(await api('ops/simulations',data)); await runs(); });
    bind('book',async data => { await api('ops/bookings',data); await bookings(); });
    [travellers,runs,bookings].forEach(task => act(task)());
    act(async () => {
        const result = await api('ops/statistics');
        table('statistics',[result.day || '口岸','入境人次','離境人次'],result.rows.map(row => [row.name,number(row.arrivals),number(row.departures)]));
    })();
}

async function crossingRows(input = {}) {
    const params = new URLSearchParams(Object.entries(input).filter(([,value]) => value));
    const rows = await api('ops/crossings?' + params);
    table('crossing-table',['旅客','口岸','時間','方向'],rows.map(row => [
        row.display_name,row.chinese,row.occurred_at,row.direction === 'Arrival' ? '入境' : '離境'
    ]));
}
if (operator) {
    bind('crossing-filter',crossingRows);
    act(crossingRows)();
    el('explain').addEventListener('click',act(async () => {
        const result = await api('ops/database-lab');
        el('plans').replaceChildren(...(result.day ? [
            node('p','查詢日期 ' + result.day),
            ...result.plans.flatMap(plan => [node('h3',plan.label),node('pre',plan.sql+'\n'+plan.plan)])
        ] : [node('p','請先匯入官方客流。')]));
    }));
}