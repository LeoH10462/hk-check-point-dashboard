import assert from 'node:assert/strict';
import {randomBytes} from 'node:crypto';

const url = process.env.BORDERFLOW_URL || 'http://localhost:8000';
const password = process.env.OPERATOR_PASSWORD;
assert.ok(password, 'Set OPERATOR_PASSWORD for a disposable demo instance');
const cookies = new Map();
let csrf;
async function request(path, data, expected = 200, updateCookies = false) {
    const headers = {Accept:'application/json', Cookie:[...cookies.values()].join('; ')};
    if (data) {
        headers['Content-Type'] = 'application/json';
        headers['X-CSRF-TOKEN'] = csrf;
    }
    const response = await fetch(url+path,{headers,method:data ? 'POST':'GET',body:data ? JSON.stringify(data):undefined,redirect:'manual'});
    if (updateCookies) {
        for (const cookie of response.headers.getSetCookie()) {
            const pair = cookie.split(';')[0];
            cookies.set(pair.split('=')[0],pair);
        }
    }
    const body = await response.text();
    if (expected !== null) assert.equal(response.status,expected,body);
    return {status:response.status,body:body.startsWith('{') || body.startsWith('[') ? JSON.parse(body) : body};
}

await request('/api/ops/travellers',null,401);
await request('/api/ops/travellers/1',null,401);
const guest = await request('/operations',null,200,true);
assert.match(guest.body, /<html lang="en">/);
assert.match(guest.body, /name="username"/);
const cantonese = await request('/?lang=zh-HK',null,200,true);
assert.match(cantonese.body, /<html lang="zh-HK">/);
assert.match(cantonese.body, /口岸等候概況/);
await request('/operations/checkpoints/LWS',null,302);
csrf = cantonese.body.match(/name="csrf-token" content="([^"]+)"/)[1];
await request('/api/login',{username:'incorrect-account',password},401);
csrf = (await request('/api/login',{username:process.env.OPERATOR_USERNAME || 'operator',password},200,true)).body.csrf;
const publicData = (await request('/api/checkpoints?direction=Arrival&audience=R')).body;
assert.equal(publicData.points.find(point => point.code === 'LWS').chinese,'羅湖');
assert.equal(publicData.points.find(point => point.code === 'LWS').transport,'港鐵東鐵綫');
assert.equal((await request('/api/ops/travellers/2')).body.profile.display_name,'示範旅客 02');
const minute = (await request('/api/ops/checkpoints/LWS/minute')).body;
assert.match(minute.minute,/^\d{4}-\d\d-\d\d \d\d:\d\d$/);
assert.equal(minute.source,'simulation');
assert.ok(minute.arrivals >= 0 && minute.departures >= 0);
const detail = (await request('/api/ops/checkpoints/LWS/details')).body;
assert.equal(detail.lastSeven.length,7);
assert.equal(detail.lastSeven.at(-1).day,detail.date);
assert.ok(detail.history.length > 0);
const page = await request('/operations/checkpoints/LWS');
assert.match(page.body,/id="toggle-crossings"/);
const crossings = (await request('/api/ops/crossings?code=LWS&day='+detail.date)).body;
assert.ok(crossings.every(row => row.name === 'Lo Wu' && row.occurred_at.startsWith(detail.date)));
assert.ok(publicData.points.every(point => !('lanes' in point)));
const history = (await request('/api/history?direction=Arrival&code=LWS')).body;
assert.equal(history.length,28,'Import official daily data before running');
assert.equal((await request('/api/ops/travellers/1')).body.count,2);
const result = (await request('/api/ops/simulations',{code:'LWS',base:30,increase:35,ramp:20,lanes:12,initial:0})).body;
assert.equal(result.baseline.at(-1).wait,0);
assert.ok(result.scenario.at(-1).wait > result.scenario[19].wait);
for (const [i,row] of result.scenario.entries()) {
    assert.equal(row.queue,(result.scenario[i-1]?.queue || 0)+row.arrivals-row.served);
}
const slots = (await request('/api/ops/slots')).body;
const first = slots.find(slot => Number(slot.remaining) === 5);
assert.ok(first,'Run against a fresh demonstration database');
const second = slots.find(slot => slot.day === first.day && slot.id !== first.id);
const tag = randomBytes(3).toString('hex').toUpperCase();
const attempts = Array.from({length:200},(_,i) => ({slot:i % 2 ? first.id:second.id,vehicle:'DEMO-'+tag+i}));
const results = await Promise.all(attempts.map(data => request('/api/ops/bookings',data,null)));
assert.deepEqual([...new Set(results.map(row => row.status))].sort(),[201,409]);
const accepted = results.filter(row => row.status === 201);
assert.equal(accepted.length,8);
const audit = (await request('/api/ops/bookings/'+accepted[0].body.id+'/audit')).body;
assert.equal(audit[0].status,'booked');
for (const row of accepted) await request('/api/ops/bookings/'+row.body.id+'/status',{status:'cancelled'});
await request('/api/logout',{},200,true);
await request('/api/ops/travellers',null,401);
console.log('PASS: access isolation, official history, queue conservation, booking audit and 200 booking requests (8 accepted, 192 conflicts).');
