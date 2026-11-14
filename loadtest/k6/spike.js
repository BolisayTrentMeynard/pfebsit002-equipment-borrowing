// Week 16, chunk 3: spike. 0 to 40 users in 30 s, hold 1 min, down in 30 s, then 2 min of observation.
// Peak journey weights and think time (2 to 5 s). No warm-up phase: the whole run is measured.
// Borrowers 31 to 40 reuse the tokens of borrowers 1 to 10 (30 fake borrowers), so 422 answers for a duplicate open request are expected and counted apart.
// A probe (GET /equipment every 5 s) shows recovery: its lines go to the console file (use --console-output, see the README).
import exec from 'k6/execution';
import { guardAndLogin, runJourney, monitorOnce, think, call, API, makeSummary, TREND_STATS } from './common.js';
import { sleep } from 'k6';

export const options = {
  setupTimeout: '10m',
  summaryTrendStats: TREND_STATS,
  scenarios: {
    spike: { executor: 'ramping-vus', startVUs: 0, stages: [{ duration: '30s', target: 40 }, { duration: '1m', target: 40 }, { duration: '30s', target: 0 }], gracefulRampDown: '30s', exec: 'user' },
    monitor: { executor: 'constant-vus', vus: 1, duration: '4m', exec: 'monitor' },
    probe: { executor: 'constant-vus', vus: 1, duration: '4m', exec: 'probe' },
  },
  thresholds: { err_steady: ['rate<0.05'] },
};

export function setup() { return guardAndLogin(parseInt(__ENV.LOAD_TEST_BORROWERS || '30', 10)); }
export function user(tokens) { runJourney(tokens, 'steady'); think(2, 5); }
export function monitor() { monitorOnce(); }

export function probe(tokens) {
  const t = Math.round((Date.now() - exec.scenario.startTime) / 1000);
  const r = call('GET', API + '/equipment', 'probe', tokens.borrowers[0], undefined, 'steady');
  console.log('probe,t=' + t + 's,status=' + r.status + ',ms=' + Math.round(r.timings.duration));
  sleep(5);
}

export function handleSummary(data) { return makeSummary(data, __ENV.RUN_NAME || 'spike', null); }
