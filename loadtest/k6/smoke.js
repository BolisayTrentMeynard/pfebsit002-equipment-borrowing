// Week 16, chunk 3: 15-second smoke test. NOT a measurement and not part of the evidence.
// Checks the plumbing before a long run: staging answers, Caddy's certificate is accepted (no --insecure-skip-tls-verify),
// LOAD_TEST_PASSWORD matches the seeded accounts, and the LT items exist. Read-only: it creates nothing, so no reset is needed afterwards.
import { call, guardAndLogin, makeSummary, TREND_STATS, API, BASE } from './common.js';

export const options = { setupTimeout: '2m', summaryTrendStats: TREND_STATS, vus: 1, iterations: 1 };

export function setup() { return guardAndLogin(1); } // logs in borrower 01, the approver and the staff user (3 logins, about 14 s)

export default function (tokens) {
  call('GET', BASE + '/up', 'up', null);
  call('GET', API + '/health/ready', 'ready', null);
  call('GET', API + '/equipment', 'equipment', tokens.borrowers[0]);
  call('GET', API + '/requests', 'requests_list', tokens.borrowers[0]);
  call('GET', API + '/custody', 'custody', tokens.staff);
  call('GET', API + '/manual-handling', 'manual', tokens.approver);
  call('GET', API + '/requests?status=PENDING', 'requests_pending', tokens.approver);
}

export function handleSummary(data) { return makeSummary(data, 'smoke', null); }
