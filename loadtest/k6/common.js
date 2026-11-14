// Week 16, chunk 3: shared code for the k6 runs. Fake accounts only. Nothing here prints or stores a token.
import http from 'k6/http';
import { sleep } from 'k6';
import exec from 'k6/execution';
import { Counter, Rate, Trend } from 'k6/metrics';

export const BASE = (__ENV.BASE_URL || 'https://localhost:9443').replace(/\/+$/, '');
export const API = BASE + '/api';
const PASSWORD = __ENV.LOAD_TEST_PASSWORD || '';
export const BORROWERS = parseInt(__ENV.LOAD_TEST_BORROWERS || '30', 10);
export const RESULT_DIR = (__ENV.RESULT_DIR || 'loadtest/results').replace(/[\\/]+$/, '');

// Guard: only the local STAGING stack. The dev stack (8443 / 8000) uses the development database.
const STAGING_OK = /^https:\/\/localhost:9443$|^http:\/\/127\.0\.0\.1:8001$/.test(BASE);

export const ENDPOINTS = {
  // reads
  equipment: 'read', requests_list: 'read', request_detail: 'read', requests_pending: 'read',
  requests_approved: 'read', custody: 'read', manual: 'read',
  // writes
  create: 'write', approve: 'write', reject: 'write', release: 'write', return: 'write',
  // outside the journeys
  login: 'login', up: 'monitor', ready: 'monitor', probe: 'monitor',
};

// ---- metrics (created at init time) ----
const lat = {}; const cnt = {};
for (const ep of Object.keys(ENDPOINTS)) { lat[ep] = new Trend('lat_' + ep, true); cnt[ep] = new Counter('n_' + ep); }
export const latReads = new Trend('lat_reads', true);
export const latWrites = new Trend('lat_writes', true);
export const latAll = new Trend('lat_all', true);
export const errSteady = new Rate('err_steady');   // unexpected answers, measured phase only
export const errAll = new Rate('err_all');         // unexpected answers, whole run
export const rejected = new Counter('business_rejections');  // 409 / 422 on a write, measured phase only
export const created201 = new Counter('created_201');        // ALL 201 answers to POST /requests (integrity check 3)
export const writesOk = new Counter('writes_ok');             // ALL successful writes (integrity check 4)
export const rateLimited = new Counter('http_429_seen');
export const serverErrors = new Counter('http_5xx_seen');
export const otherUnexpected = new Counter('other_unexpected');
export const loginFails = new Counter('login_failures');

// Chunk 4 (additive): a script may set hooks.onResponse(res, ep, kind) to see every recorded answer.
// Unset in the baseline, ordinary, peak and spike runs, so their behavior did not change.
export const hooks = { onResponse: null };

export const TREND_STATS = ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'];

const READ_OK = http.expectedStatuses(200);
const WRITE_OK = http.expectedStatuses(200, 201, 409, 422);

// ---- helpers ----
export function rand(min, max) { return min + Math.random() * (max - min); }
export function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }
export function think(min, max) { sleep(rand(min, max)); }

function safeJson(res, selector) {
  try { return res.json(selector); } catch (e) { return undefined; }
}

export function phaseOf(warmupMs) {
  if (!warmupMs) return 'steady';
  return (Date.now() - exec.scenario.startTime) < warmupMs ? 'warmup' : 'steady';
}

// One HTTP call, recorded. `auth` is a token or null. The token goes only into the header.
export function call(method, url, ep, auth, body, phase) {
  const kind = ENDPOINTS[ep];
  const headers = { Accept: 'application/json' };
  if (auth) headers.Authorization = 'Bearer ' + auth;
  let payload = null;
  if (body !== undefined) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
  const res = http.request(method, url, payload, {
    headers,
    tags: { name: ep },
    responseCallback: kind === 'write' ? WRITE_OK : READ_OK,
  });
  record(res, ep, kind, phase || 'steady');
  return res;
}

function record(res, ep, kind, phase) {
  const s = res.status;
  const steady = phase === 'steady';
  let unexpected = false;

  if (s === 200 || s === 201) {
    if (kind === 'write') { writesOk.add(1); if (ep === 'create' && s === 201) created201.add(1); }
  } else if (kind === 'write' && (s === 409 || s === 422)) {
    if (steady) rejected.add(1);
  } else {
    unexpected = true;
    if (s === 429) rateLimited.add(1);
    else if (s >= 500) serverErrors.add(1);
    else otherUnexpected.add(1);
  }

  errAll.add(unexpected);
  if (steady) {
    errSteady.add(unexpected);
    const ms = res.timings.duration;
    lat[ep].add(ms); cnt[ep].add(1); latAll.add(ms);
    if (kind === 'read') latReads.add(ms);
    if (kind === 'write') latWrites.add(ms);
  }
  if (hooks.onResponse) hooks.onResponse(res, ep, kind);
}

// ---- setup(): log in the fake accounts once, one about every 7 s, so the login limit (10 per minute per IP) stays ON ----
const LOGIN_GAP_S = 7; // floor(60 / 7) + 1 = 9 logins in any one-minute window, under the limit of 10

export function emailOf(i) { return 'loadborrower' + String(i).padStart(2, '0') + '@example.test'; }

function login(email, tries) {
  const res = http.post(API + '/login', JSON.stringify({ email, password: PASSWORD }), {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    tags: { name: 'login_setup' },
    responseCallback: http.expectedStatuses(200, 401, 429),
  });
  if (res.status === 429 && tries > 0) {
    const wait = parseInt(res.headers['Retry-After'] || '60', 10);
    sleep(Math.min(Math.max(wait, 1), 65) + 1);
    return login(email, tries - 1);
  }
  if (res.status !== 200) {
    // never print the response body or the password
    exec.test.abort('Setup login failed for one fake account (status ' + res.status + '). Is the staging database seeded with LoadTestSeeder, and is LOAD_TEST_PASSWORD the same one used for seeding?');
  }
  return safeJson(res, 'token');
}

export function guardAndLogin(borrowerCount) {
  if (!STAGING_OK && __ENV.ALLOW_NON_STAGING !== '1') {
    exec.test.abort('BASE_URL is ' + BASE + '. Load runs are for local staging only (https://localhost:9443, or http://127.0.0.1:8001 as the loopback fallback).');
  }
  if (PASSWORD.length < 12) {
    exec.test.abort('LOAD_TEST_PASSWORD is not set in this PowerShell session (12+ characters, the one used when seeding).');
  }
  const ready = http.get(API + '/health/ready', { tags: { name: 'ready_setup' }, responseCallback: http.expectedStatuses(200, 503) });
  if (ready.status !== 200) exec.test.abort('Readiness did not answer 200 (status ' + ready.status + '). Is staging running and MySQL up?');

  const tokens = { borrowers: [], approver: null, staff: null };
  const accounts = [];
  for (let i = 1; i <= borrowerCount; i++) accounts.push(['b', emailOf(i)]);
  accounts.push(['a', 'loadapprover@example.test'], ['s', 'loadstaff@example.test']);

  accounts.forEach(([role, email], idx) => {
    if (idx > 0) sleep(LOGIN_GAP_S);
    const t = login(email, 2);
    if (role === 'b') tokens.borrowers.push(t); else if (role === 'a') tokens.approver = t; else tokens.staff = t;
  });

  // The seeder must have run: LT-xxx items must exist.
  const eq = http.get(API + '/equipment', { headers: { Authorization: 'Bearer ' + tokens.borrowers[0], Accept: 'application/json' }, tags: { name: 'equipment_setup' } });
  const items = safeJson(eq, 'data') || [];
  const lt = items.filter((e) => (e.asset_tag || '').indexOf('LT-') === 0).length;
  if (lt < 1) exec.test.abort('No LT-xxx items found. Run LoadTestSeeder on equipment_staging first.');
  return tokens;
}

// ---- journeys ----
const WEIGHTS = [['B1', 35], ['B2', 15], ['B3', 10], ['A1', 20], ['A2', 5], ['S1', 15]];
function chooseJourney() {
  let r = Math.random() * 100;
  for (const [id, w] of WEIGHTS) { if (r < w) return id; r -= w; }
  return 'B1';
}

// per-VU state (each VU has its own copy of the module)
const myRequested = new Set();

function borrowerToken(tokens) {
  return tokens.borrowers[(exec.vu.idInInstance - 1) % tokens.borrowers.length];
}

export function runJourney(tokens, phase) {
  const j = chooseJourney();
  const bt = borrowerToken(tokens);

  if (j === 'B1') {
    call('GET', API + '/equipment', 'equipment', bt, undefined, phase);
    call('GET', API + '/requests', 'requests_list', bt, undefined, phase);
  } else if (j === 'B2') {
    const list = call('GET', API + '/requests', 'requests_list', bt, undefined, phase);
    const rows = safeJson(list, 'data') || [];
    if (rows.length) call('GET', API + '/requests/' + pick(rows).id, 'request_detail', bt, undefined, phase);
  } else if (j === 'B3') {
    const eq = call('GET', API + '/equipment', 'equipment', bt, undefined, phase);
    const items = (safeJson(eq, 'data') || []).filter((e) => e.status === 'available' && !myRequested.has(e.id));
    if (items.length) {
      const it = pick(items);
      const r = call('POST', API + '/requests', 'create', bt,
        { equipment_id: it.id, reason: 'Load test request', requested_days: 1 + Math.floor(Math.random() * 7) }, phase);
      if (r.status === 201 || r.status === 422) myRequested.add(it.id);
    }
  } else if (j === 'A1') {
    const list = call('GET', API + '/requests?status=PENDING', 'requests_pending', tokens.approver, undefined, phase);
    const rows = safeJson(list, 'data') || [];
    if (rows.length) {
      const row = pick(rows);
      const approve = Math.random() < 0.8; // mostly approve, so items keep flowing to release and return
      call('POST', API + '/requests/' + row.id + (approve ? '/approve' : '/reject'), approve ? 'approve' : 'reject', tokens.approver, {}, phase);
    }
  } else if (j === 'A2') {
    call('GET', API + '/manual-handling', 'manual', tokens.approver, undefined, phase);
  } else if (j === 'S1') {
    const cu = call('GET', API + '/custody', 'custody', tokens.staff, undefined, phase);
    const out = safeJson(cu, 'data') || [];
    if (out.length && Math.random() < 0.6) {
      call('POST', API + '/requests/' + pick(out).request_id + '/return', 'return', tokens.staff, { inspection_result: 'good' }, phase);
    } else {
      // the journey table's "one approved request" needs a lookup of the approved ones (one extra read)
      const ap = call('GET', API + '/requests?status=APPROVED', 'requests_approved', tokens.staff, undefined, phase);
      const rows = safeJson(ap, 'data') || [];
      if (rows.length) call('POST', API + '/requests/' + pick(rows).id + '/release', 'release', tokens.staff, {}, phase);
    }
  }
}

// Beside every load run: 1 request every 10 s to /up and /api/health/ready (not part of the user count).
export function monitorOnce() {
  call('GET', BASE + '/up', 'up', null, undefined, 'steady');
  call('GET', API + '/health/ready', 'ready', null, undefined, 'steady');
  sleep(10);
}

// ---- summary: a small Markdown table (no tokens in it), plus the raw summary JSON for the developer's own files ----
function f(v) { return (v === undefined || v === null) ? '-' : (Math.round(v * 10) / 10).toString(); }

export function makeSummary(data, name, steadySeconds) {
  const m = data.metrics;
  const val = (k, s) => (m[k] && m[k].values && m[k].values[s] !== undefined) ? m[k].values[s] : undefined;
  const lines = [];
  lines.push('# k6 run: ' + name + '  (' + new Date().toISOString() + ')');
  lines.push('');
  lines.push('Base URL: ' + BASE + '. Times in ms. "steady" = the measured phase (warm-up left out).');
  lines.push('');
  lines.push('| Endpoint | n | avg | med | p90 | p95 | p99 | max |');
  lines.push('|---|---|---|---|---|---|---|---|');
  const order = Object.keys(ENDPOINTS).concat(['reads', 'writes', 'all']);
  for (const ep of order) {
    const k = 'lat_' + ep;
    if (!m[k] || val(k, 'max') === undefined) continue;
    const n = ENDPOINTS[ep] ? val('n_' + ep, 'count') : undefined;
    lines.push('| ' + ep + ' | ' + f(n) + ' | ' + f(val(k, 'avg')) + ' | ' + f(val(k, 'med')) + ' | ' + f(val(k, 'p(90)')) + ' | ' +
      f(val(k, 'p(95)')) + ' | ' + f(val(k, 'p(99)')) + ' | ' + f(val(k, 'max')) + ' |');
  }
  lines.push('');
  const steadyReqs = Object.keys(ENDPOINTS).reduce((a, ep) => a + (val('n_' + ep, 'count') || 0), 0);
  lines.push('- Requests in the measured phase: ' + steadyReqs + (steadySeconds ? ' (about ' + f(steadyReqs / steadySeconds) + ' per second over ' + steadySeconds + ' s)' : ''));
  lines.push('- Whole run: ' + f(val('http_reqs', 'count')) + ' requests, ' + f(val('http_reqs', 'rate')) + ' per second on average; iterations ' + f(val('iterations', 'count')));
  lines.push('- Error rate (unexpected answers), measured phase: ' + f((val('err_steady', 'rate') || 0) * 100) + ' %; whole run: ' + f((val('err_all', 'rate') || 0) * 100) + ' %');
  lines.push('- 5xx: ' + f(val('http_5xx_seen', 'count') || 0) + ', 429: ' + f(val('http_429_seen', 'count') || 0) + ', other unexpected: ' + f(val('other_unexpected', 'count') || 0) + ', business rejections (409/422, measured phase): ' + f(val('business_rejections', 'count') || 0));
  lines.push('- 201 answers to POST /requests (whole run, for integrity check 3): ' + f(val('created_201', 'count') || 0) + '; successful writes (whole run, for check 4): ' + f(val('writes_ok', 'count') || 0));
  lines.push('- Data sent / received: ' + f((val('data_sent', 'count') || 0) / 1024) + ' KB / ' + f((val('data_received', 'count') || 0) / 1024) + ' KB');
  lines.push('');
  const failed = [];
  for (const k of Object.keys(m)) {
    const t = m[k].thresholds;
    if (t) for (const e of Object.keys(t)) { if (t[e] === false || (t[e] && t[e].ok === false)) failed.push(k + ': ' + e); }
  }
  lines.push('Thresholds: ' + (failed.length ? 'FAILED -> ' + failed.join('; ') : 'all passed'));
  lines.push('');
  const md = lines.join('\n');
  const out = { stdout: md + '\n' };
  out[RESULT_DIR + '/' + name + '-summary.md'] = md;
  out[RESULT_DIR + '/' + name + '-summary.json'] = JSON.stringify(data, null, 1);
  return out;
}
