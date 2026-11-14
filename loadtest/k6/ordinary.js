// Week 16, chunk 3: ordinary workload. 5 virtual users, think time 5 to 15 s, 1 min warm-up then 5 min measured.
// Monitor scenario beside it: /up and /api/health/ready every 10 s.
import { guardAndLogin, runJourney, monitorOnce, think, phaseOf, makeSummary, TREND_STATS } from './common.js';

const WARMUP_MS = 60 * 1000;

export const options = {
  setupTimeout: '10m',   // 32 logins, one about every 7 s (about 4 min)
  summaryTrendStats: TREND_STATS,
  scenarios: {
    ordinary: { executor: 'ramping-vus', startVUs: 0, stages: [{ duration: '1m', target: 5 }, { duration: '5m', target: 5 }], gracefulRampDown: '30s', exec: 'user' },
    monitor: { executor: 'constant-vus', vus: 1, duration: '6m', exec: 'monitor' },
  },
  thresholds: {
    lat_reads: ['p(95)<800'],
    lat_writes: ['p(95)<1500'],
    err_steady: ['rate<0.01'],
  },
};

export function setup() { return guardAndLogin(parseInt(__ENV.LOAD_TEST_BORROWERS || '30', 10)); }
export function user(tokens) { runJourney(tokens, phaseOf(WARMUP_MS)); think(5, 15); }
export function monitor() { monitorOnce(); }
export function handleSummary(data) { return makeSummary(data, __ENV.RUN_NAME || 'ordinary', 300); }
