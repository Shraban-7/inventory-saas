/**
 * k6 load profile: 200 concurrent requests through the app (ProxySQL-backed DB path).
 *
 * Defaults to /readyz so the probe exercises primary DB (via ProxySQL), Redis, and
 * Horizon queue heartbeats. A healthy stack must return HTTP 200 with status "ok".
 * HTTP 503 / component failures are hard failures — never treated as success
 * (ReadinessProbe can hide raw "too many connections" behind components.database=fail).
 *
 *   k6 run -e BASE_URL=http://localhost:8080 -e K6_TARGET_PATH=/readyz tests/load/proxysql-k6.js
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate } from 'k6/metrics';

const connectionExhaustion = new Counter('connection_exhaustion_errors');
const readinessDatabaseFailures = new Counter('readiness_database_failures');
const readinessHttpFailures = new Counter('readiness_http_failures');
const failureRate = new Rate('request_failures');

const baseUrl = (__ENV.BASE_URL || 'http://localhost:8080').replace(/\/$/, '');
const targetPath = __ENV.K6_TARGET_PATH || '/readyz';
const url = `${baseUrl}${targetPath.startsWith('/') ? targetPath : `/${targetPath}`}`;
const expectReadyz = /(^|\/)readyz\/?$/.test(targetPath);

export const options = {
  scenarios: {
    proxysql_burst: {
      executor: 'constant-vus',
      vus: 200,
      duration: '30s',
    },
  },
  thresholds: {
    http_req_failed: ['rate==0'],
    connection_exhaustion_errors: ['count==0'],
    readiness_database_failures: ['count==0'],
    readiness_http_failures: ['count==0'],
    checks: ['rate==1'],
  },
};

function parseBody(res) {
  try {
    return res.json();
  } catch (e) {
    return null;
  }
}

export default function () {
  const res = http.get(url, {
    timeouts: { read: '10s', connect: '5s' },
    tags: { endpoint: targetPath },
  });

  const body = String(res.body || '');
  const json = parseBody(res);
  const databaseFailed =
    (json && json.components && json.components.database === 'fail') ||
    /"database"\s*:\s*"fail"/.test(body);

  const explicitExhaustion =
    /too many connections/i.test(body) || /SQLSTATE\[HY000\]/i.test(body);

  // ReadinessProbe maps DB connection failures (including exhaustion) to 503 + database=fail.
  if (explicitExhaustion || (res.status === 503 && databaseFailed)) {
    connectionExhaustion.add(1);
  }

  if (databaseFailed) {
    readinessDatabaseFailures.add(1);
  }

  if (res.status === 503 || (expectReadyz && res.status !== 200)) {
    readinessHttpFailures.add(1);
  }

  const statusOk = expectReadyz
    ? res.status === 200
    : res.status >= 200 && res.status < 300;

  const readinessOk =
    !expectReadyz ||
    (json &&
      json.status === 'ok' &&
      json.components &&
      json.components.database === 'ok' &&
      json.components.redis === 'ok');

  const ok = check(res, {
    'status is 200 for readiness (2xx otherwise); never 503': () =>
      statusOk && res.status !== 503,
    'readiness body reports ok database and redis': () => readinessOk,
    'no connection exhaustion or masked DB readiness failure': () =>
      !explicitExhaustion && !databaseFailed,
  });

  failureRate.add(!ok);
  sleep(0.1);
}
