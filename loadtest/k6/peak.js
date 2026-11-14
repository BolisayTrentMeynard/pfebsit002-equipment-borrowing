// Week 16, chunk 3: peak workload. 25 virtual users, think time 2 to 5 s, 1 min warm-up then 5 min measured.
import { guardAndLogin, runJourney, monitorOnce, think, phaseOf, makeSummary, TREND_STATS } from './common.js';

const WARMUP_MS = 60 * 1000;

export const options = {
  setupTimeout: '10m',
  summaryTrendStats: TREND_STATS,
  scenarios: {
    peak: { executor: 'ramping-vus', startVUs: 0, stages: [{ duration: '1m', target: 25 }, { duration: '5m', target: 25 }], gracefulRampDown: '30s', exec: 'user' },
    monitor: { executor: 'constant-vus', vus: 1, duration: '6m', exec: 'monitor' },
  },
  thresholds: {
    lat_all: ['p(95)<2000'],
    err_steady: ['rate<0.02'],
  },
};

export function setup() { return guardAndLogin(parseInt(__ENV.LOAD_TEST_BORROWERS || '30', 10)); }
export function user(tokens) { runJourney(tokens, phaseOf(WARMUP_MS)); think(2, 5); }
export function monitor() { monitorOnce(); }
export function handleSummary(data) { return makeSummary(data, __ENV.RUN_NAME || 'peak', 300); }
