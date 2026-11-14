// Week 16, chunk 4: failure test. MySQL is stopped by hand while the ORDINARY workload runs (AC-48).
// Same users, journeys and think time as ordinary.js (5 users, 5 to 15 s). The only new things are the cues
// on the terminal and the per-phase numbers.
//
// Time is counted in seconds from the moment the virtual users start (after setup(), about 4.5 min of logins):
//    0 to  60  warm-up (not measured)
//   60 to 180  "before"    normal operation
//  180         CUE: STOP MySQL now (XAMPP Control Panel, MySQL row, Stop)
//  180 to 200  "stopping"  guard window (the click takes a few seconds), not judged
//  200 to 300  "during"    MySQL is down
//  300         CUE: START MySQL now
//  300 to 330  "starting"  guard window (MySQL start, requests still queued), not judged
//  330 to 450  "after"     recovery, judged
// A request belongs to the phase in which its ANSWER arrived. The guard windows exist because the click is
// done by a person, so the real stop and start times differ by a few seconds from the cues. The real times
// are the lines the monitor prints (readiness changes) and the timestamps of the manual checks.
//
// The monitor polls /up and /api/health/ready every 10 s, exactly as in the ordinary run, so that the load is
// the same. Do NOT use --console-output: the cues and the readiness changes are printed to the terminal.
import exec from 'k6/execution';
import { sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';
import { guardAndLogin, runJourney, think, call, hooks, BASE, API, makeSummary, TREND_STATS, RESULT_DIR } from './common.js';

const WARMUP_S = 60;
const PHASES = [
  { id: 'before', from: 60, to: 180 },
  { id: 'stopping', from: 180, to: 200 },
  { id: 'during', from: 200, to: 300 },
  { id: 'starting', from: 300, to: 330 },
  { id: 'after', from: 330, to: 450 },
];
const END_S = 450;

export const options = {
  setupTimeout: '10m',   // 32 logins, one about every 7 s (about 4.5 min)
  summaryTrendStats: TREND_STATS,
  scenarios: {
    user: { executor: 'ramping-vus', startVUs: 0, stages: [{ duration: '1m', target: 5 }, { duration: '6m30s', target: 5 }], gracefulRampDown: '30s', exec: 'user' },
    monitor: { executor: 'constant-vus', vus: 1, duration: '7m30s', exec: 'monitor' },
    clock: { executor: 'constant-vus', vus: 1, duration: '7m30s', exec: 'clock' },
  },
  // Pass/fail only where the expected result is clear: nothing fails before the stop, everything works after the guard window.
  // The outage itself has no threshold: its numbers are the result.
  thresholds: {
    fo_before_user_5xx: ['count<1'],
    fo_before_ready_503: ['count<1'],
    fo_after_user_5xx: ['count<1'],
    fo_after_user_other: ['count<1'],
    fo_after_ready_503: ['count<1'],
  },
};

// ---- per-phase metrics (created at init time; every answer adds a 0 or a 1 so that each counter exists) ----
const M = {};
for (const p of PHASES) {
  const id = p.id;
  M[id] = {
    user_n: new Counter('fo_' + id + '_user_n'),
    user_ok: new Counter('fo_' + id + '_user_ok'),
    user_5xx: new Counter('fo_' + id + '_user_5xx'),
    user_other: new Counter('fo_' + id + '_user_other'),
    user_ms: new Trend('fo_' + id + '_user_ms', true),
    up_n: new Counter('fo_' + id + '_up_n'),
    up_200: new Counter('fo_' + id + '_up_200'),
    up_ms: new Trend('fo_' + id + '_up_ms', true),
    ready_n: new Counter('fo_' + id + '_ready_n'),
    ready_200: new Counter('fo_' + id + '_ready_200'),
    ready_503: new Counter('fo_' + id + '_ready_503'),
    ready_ms: new Trend('fo_' + id + '_ready_ms', true),
  };
}

function secondsNow() { return (Date.now() - exec.scenario.startTime) / 1000; }
function phaseAt(sec) {
  for (const p of PHASES) if (sec >= p.from && sec < p.to) return p.id;
  return null; // warm-up, or after the planned end
}
function stamp() {
  const d = new Date();
  const pht = new Date(d.getTime() + 8 * 3600 * 1000);
  return d.toISOString().substr(11, 8) + ' UTC / ' + pht.toISOString().substr(11, 8) + ' PHT';
}

hooks.onResponse = function (res, ep, kind) {
  const id = phaseAt(secondsNow());
  if (!id) return;
  const m = M[id];
  const s = res.status;
  const ms = res.timings.duration;
  if (ep === 'up') {
    m.up_n.add(1); m.up_200.add(s === 200 ? 1 : 0); m.up_ms.add(ms);
  } else if (ep === 'ready') {
    m.ready_n.add(1); m.ready_200.add(s === 200 ? 1 : 0); m.ready_503.add(s === 503 ? 1 : 0); m.ready_ms.add(ms);
  } else {
    const ok = s === 200 || s === 201 || (kind === 'write' && (s === 409 || s === 422));
    m.user_n.add(1); m.user_ok.add(ok ? 1 : 0); m.user_5xx.add(s >= 500 ? 1 : 0);
    m.user_other.add(!ok && s < 500 ? 1 : 0); m.user_ms.add(ms);
  }
};

export function setup() { return guardAndLogin(parseInt(__ENV.LOAD_TEST_BORROWERS || '30', 10)); }

export function user(tokens) {
  // The first 60 s are warm-up and are left out of the common metrics too (as in the ordinary run).
  runJourney(tokens, secondsNow() < WARMUP_S ? 'warmup' : 'steady');
  think(5, 15);
}

// The monitor prints a line whenever readiness or liveness changes (wall-clock time, so it can be compared with the log).
let lastReady = null;
let lastUp = null;
export function monitor() {
  const u = call('GET', BASE + '/up', 'up', null, undefined, 'steady');
  const r = call('GET', API + '/health/ready', 'ready', null, undefined, 'steady');
  const t = Math.round(secondsNow());
  if (u.status !== lastUp) {
    console.log('MONITOR ' + stamp() + ' t=' + t + 's  /up changed ' + lastUp + ' -> ' + u.status + ' (' + Math.round(u.timings.duration) + ' ms)');
    lastUp = u.status;
  }
  if (r.status !== lastReady) {
    console.log('MONITOR ' + stamp() + ' t=' + t + 's  readiness changed ' + lastReady + ' -> ' + r.status + ' (' + Math.round(r.timings.duration) + ' ms)');
    lastReady = r.status;
  }
  sleep(10);
}

// The clock only prints cues; it sends no request.
const CUES = [
  [1, 'Load has started. Warm-up for 60 s. Have the XAMPP Control Panel in front of you (MySQL row: Stop and Start).'],
  [60, 'Warm-up over. "before" phase: 2 minutes of normal operation. Do not touch anything.'],
  [165, 'IN 15 s: STOP MySQL (XAMPP Control Panel, MySQL, Stop).'],
  [175, 'IN 5 s: STOP MySQL.'],
  [180, '>>>>>>>>  STOP MYSQL NOW  <<<<<<<<  Write down the time of your click.'],
  [200, 'Outage window. Keep MySQL stopped. Do the manual checks M1 to M6 now (loadtest\\README.md, chunk 4).'],
  [285, 'IN 15 s: START MySQL. Finish the manual checks.'],
  [295, 'IN 5 s: START MySQL.'],
  [300, '>>>>>>>>  START MYSQL NOW  <<<<<<<<  Write down the time of your click.'],
  [330, 'Recovery phase "after" (2 minutes). Do not restart php or Caddy. Do not touch anything.'],
  [450, 'Load ends. Wait for the k6 table. Then stop the sampler.'],
];
const printed = {};
export function clock() {
  const t = Math.floor(secondsNow());
  for (const c of CUES) {
    if (t >= c[0] && !printed[c[0]]) {
      printed[c[0]] = true;
      console.log('CUE ' + stamp() + ' t=' + t + 's  ' + c[1]);
    }
  }
  sleep(1);
}

// ---- summary: the common table (run level, all phases mixed) plus one table per phase ----
function f(v) { return (v === undefined || v === null) ? '-' : (Math.round(v * 10) / 10).toString(); }

export function handleSummary(data) {
  const base = makeSummary(data, __ENV.RUN_NAME || 'failure', null);
  const val = (k, s) => (data.metrics[k] && data.metrics[k].values && data.metrics[k].values[s] !== undefined) ? data.metrics[k].values[s] : undefined;
  const pct = (a, b) => (b ? f(100 * a / b) + ' %' : '-');
  const L = [];
  L.push('## Per phase (a request counts in the phase in which its answer arrived; seconds from the start of the load)');
  L.push('');
  L.push('Run-level numbers above mix all phases. Read these tables instead. Guard windows ("stopping", "starting") are shown but not judged.');
  L.push('');
  L.push('### Users (journey requests)');
  L.push('| Phase | Window (s) | n | ok | 5xx | other | 5xx share | p50 ms | p95 ms | max ms |');
  L.push('|---|---|---|---|---|---|---|---|---|---|');
  for (const p of PHASES) {
    const k = 'fo_' + p.id + '_user_';
    const n = val(k + 'n', 'count') || 0;
    L.push('| ' + p.id + ' | ' + p.from + ' to ' + p.to + ' | ' + n + ' | ' + (val(k + 'ok', 'count') || 0) + ' | ' + (val(k + '5xx', 'count') || 0) + ' | ' + (val(k + 'other', 'count') || 0) +
      ' | ' + pct(val(k + '5xx', 'count') || 0, n) + ' | ' + f(val(k + 'ms', 'med')) + ' | ' + f(val(k + 'ms', 'p(95)')) + ' | ' + f(val(k + 'ms', 'max')) + ' |');
  }
  L.push('');
  L.push('### Monitor (one poll every 10 s)');
  L.push('| Phase | /up n | /up 200 | /up p50 ms | /up max ms | ready n | ready 200 | ready 503 | ready p50 ms | ready max ms |');
  L.push('|---|---|---|---|---|---|---|---|---|---|');
  for (const p of PHASES) {
    const u = 'fo_' + p.id + '_up_';
    const r = 'fo_' + p.id + '_ready_';
    L.push('| ' + p.id + ' | ' + (val(u + 'n', 'count') || 0) + ' | ' + (val(u + '200', 'count') || 0) + ' | ' + f(val(u + 'ms', 'med')) + ' | ' + f(val(u + 'ms', 'max')) +
      ' | ' + (val(r + 'n', 'count') || 0) + ' | ' + (val(r + '200', 'count') || 0) + ' | ' + (val(r + '503', 'count') || 0) + ' | ' + f(val(r + 'ms', 'med')) + ' | ' + f(val(r + 'ms', 'max')) + ' |');
  }
  L.push('');
  L.push('Pass/fail rules (only where the result is clear): no 5xx and no readiness 503 in "before"; no 5xx, no other unexpected answer and no readiness 503 in "after". The outage has no threshold; its numbers are the result.');
  L.push('The real stop and start times are in the CUE and MONITOR lines printed on the terminal and in the timestamps of the manual checks.');
  L.push('');
  const extra = L.join('\n');
  const out = { stdout: base.stdout + '\n' + extra + '\n' };
  const mdKey = RESULT_DIR + '/' + (__ENV.RUN_NAME || 'failure') + '-summary.md';
  out[mdKey] = base.stdout + '\n' + extra + '\n';
  out[RESULT_DIR + '/' + (__ENV.RUN_NAME || 'failure') + '-summary.json'] = base[RESULT_DIR + '/' + (__ENV.RUN_NAME || 'failure') + '-summary.json'];
  return out;
}
