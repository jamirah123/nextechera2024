# Load / stress testing

See the full requirement in [docs/high-concurrency.md](../../docs/high-concurrency.md).

## Prerequisites

- [k6](https://k6.io/docs/get-started/installation/) installed
- A **staging** (or local) environment with realistic seeded data
- `APP_DEBUG=false` on the target when validating error leakage

## Smoke / peak mix

```bash
k6 run -e BASE_URL=http://127.0.0.1:8000 \
       -e EMAIL=ops@example.com \
       -e PASSWORD='Password@123' \
       tests/load/k6-smoke.js
```

Without credentials, the script only hits `/login` and `/up`.

## Required scenarios before production

| Scenario | How to exercise |
|----------|-----------------|
| Concurrent users | Raise k6 VUs / stages in `k6-smoke.js` |
| Login load | Burst POSTs to `/login` (keep throttle expectations in mind) |
| Shift creation | Authenticated POSTs to `/shifts` (add a dedicated script when ready) |
| Deployment concurrency | Parallel POSTs to `/deployments` on overlapping guards |
| Dashboard / reports | GETs already in `k6-smoke.js`; add export URLs carefully |
| Bulk import / export | Run import job + export routes under load |
| Database stress | Combine writes + reads while watching MySQL slow query log |
| Queue stress | Flood payroll/import jobs; watch `psg:queue-health` |
| Backup while active | `psg:backup-database` during a k6 run |
| Peak hour | Mix of the above at expected max concurrent operators |

## Pass criteria

- `http_req_failed` under agreed threshold (script default &lt; 1%)
- Interactive p95 within target (script default &lt; 2s for tagged metric)
- No `SQLSTATE` / Ignition stack traces in HTML when debug is off
- Critical writes leave no partial transactions on failure
