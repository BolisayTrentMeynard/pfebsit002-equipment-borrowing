// Week 16, chunk 3: single-user baseline. One virtual user, one request at a time, 30 repeats per endpoint, no think time.
// Login is timed separately, in its own scenario, paced at 7 s so the login limit (10 per minute per IP) stays ON.
// Run (PowerShell, repo root): see loadtest/README.md
import { call, guardAndLogin, makeSummary, TREND_STATS, API, BASE, emailOf } from './common.js';
import { sleep } from 'k6';
import exec from 'k6/execution';

export const options = {
  setupTimeout: '5m',
  summaryTrendStats: TREND_STATS,
  scenarios: {
    // first the endpoints (predicted about 1 to 2 minutes), then the paced logins from minute 4, so the two never overlap
    endpoints: { executor: 'per-vu-iterations', vus: 1, iterations: 30, maxDuration: '5m', exec: 'endpoints', startTime: '0s' },
    login_timing: { executor: 'per-vu-iterations', vus: 1, iterations: 30, maxDuration: '10m', exec: 'loginTiming', startTime: '5m15s' },
  },
  thresholds: { err_all: ['rate<0.01'] },
};

export function setup() {
  return guardAndLogin(1); // 3 accounts only: borrower 01, the approver, the staff user (about 14 s)
}

export function endpoints(tokens) {
  const bt = tokens.borrowers[0];
  const i = exec.scenario.iterationInInstance;
  call('GET', BASE + '/up', 'up', null);
  call('GET', API + '/health/ready', 'ready', null);
  const eq = call('GET', API + '/equipment', 'equipment', bt);
  call('GET', API + '/requests', 'requests_list', bt);

  // a different item each repeat, so POST /requests is a real create every time (30 of the 50 LT items)
  let itemId = null;
  try { itemId = eq.json('data')[i].id; } catch (e) { /* the 201 count in the summary shows if this failed */ }
  if (itemId !== null) {
    const r = call('POST', API + '/requests', 'create', bt, { equipment_id: itemId, reason: 'Baseline request', requested_days: 3 });
    let newId = null;
    try { newId = r.json('data.id'); } catch (e) { /* no id when the create was refused */ }
    if (newId !== null) call('GET', API + '/requests/' + newId, 'request_detail', bt);
  }
  call('GET', API + '/custody', 'custody', tokens.staff);
  call('GET', API + '/manual-handling', 'manual', tokens.approver);
  call('GET', API + '/requests?status=PENDING', 'requests_pending', tokens.approver);
}

export function loginTiming() {
  // borrower 01; the password comes from the environment and is never printed
  call('POST', API + '/login', 'login', null, { email: emailOf(1), password: __ENV.LOAD_TEST_PASSWORD || '' });
  sleep(7);
}

export function handleSummary(data) {
  return makeSummary(data, 'baseline', null);
}
