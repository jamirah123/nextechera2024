/**
 * PSG Shifts — minimal k6 smoke / concurrency script.
 *
 * Install: https://k6.io/docs/get-started/installation/
 *
 * Run against a staging environment with seeded users:
 *
 *   k6 run -e BASE_URL=https://staging.example.com \
 *          -e EMAIL=shift.manager@example.com \
 *          -e PASSWORD='your-password' \
 *          tests/load/k6-smoke.js
 *
 * Pass criteria (adjust after baselining):
 * - http_req_failed < 1%
 * - http_req_duration p95 < 2000ms for interactive GETs
 * - No responses containing "SQLSTATE" or Ignition "Stack trace"
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Trend } from 'k6/metrics';

const BASE_URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const EMAIL = __ENV.EMAIL || '';
const PASSWORD = __ENV.PASSWORD || '';

const interactive = new Trend('psg_interactive_ms');

export const options = {
  scenarios: {
    peak_mix: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 5 },
        { duration: '1m', target: 10 },
        { duration: '30s', target: 0 },
      ],
      gracefulRampDown: '20s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<3000'],
    psg_interactive_ms: ['p(95)<2000'],
  },
};

function extractCsrf(body) {
  const match = body.match(/name="_token"\s+value="([^"]+)"/);
  return match ? match[1] : '';
}

function assertNoLeak(res) {
  return check(res, {
    'no SQLSTATE leak': (r) => !String(r.body || '').includes('SQLSTATE'),
    'no Ignition stack': (r) => !String(r.body || '').includes('Stack trace'),
  });
}

export default function () {
  const jar = http.cookieJar();

  group('login', () => {
    const loginPage = http.get(`${BASE_URL}/login`);
    assertNoLeak(loginPage);

    if (!EMAIL || !PASSWORD) {
      // Unauthenticated health probe only.
      const up = http.get(`${BASE_URL}/up`);
      check(up, { 'health up': (r) => r.status === 200 });
      sleep(1);
      return;
    }

    const token = extractCsrf(loginPage.body);
    const login = http.post(
      `${BASE_URL}/login`,
      {
        _token: token,
        email: EMAIL,
        password: PASSWORD,
      },
      { redirects: 0, jar },
    );

    check(login, {
      'login accepted': (r) => r.status === 302 || r.status === 200,
    });
  });

  if (!EMAIL || !PASSWORD) {
    return;
  }

  group('interactive reads', () => {
    const paths = ['/dashboard', '/deployments', '/shifts', '/manpower-coverage', '/search?q=a'];
    for (const path of paths) {
      const started = Date.now();
      const res = http.get(`${BASE_URL}${path}`);
      interactive.add(Date.now() - started);
      check(res, {
        'interactive ok': (r) => r.status === 200 || r.status === 302 || r.status === 403,
      });
      assertNoLeak(res);
      sleep(0.3);
    }
  });

  sleep(1);
}
