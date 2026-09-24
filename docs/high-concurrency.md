# High-Concurrency & Request Handling

**Status:** Core production requirement for Platinum Security Group Shifts.

The system must reliably handle multiple concurrent requests from many users without producing uncontrolled server errors, request failures, timeouts, or application instability.

This is not a “never show an error” guarantee. No production system can promise that. The professional standard is:

> Failures must be **contained**, **handled gracefully**, **logged**, **recoverable**, and must **not bring down the system** or **expose technical errors** to users.

---

## 1. Functional requirements

The system should:

1. Support many users accessing the system simultaneously.
2. Handle concurrent requests for dashboards, shifts, deployments, searches, reports, HR, payroll, and administration.
3. Use optimized database queries, proper indexing, eager loading, pagination, and caching to reduce unnecessary database load.
4. Prevent N+1 queries and inefficient repeated database operations.
5. Use database transactions for critical operations such as deployments, replacements, transfers, payroll processing, and manpower updates.
6. Use queues/background jobs for resource-intensive operations such as large reports, exports, notifications, backups, and bulk processing.
7. Prevent duplicate submissions when users accidentally click a button multiple times.
8. Handle concurrent updates safely when two users attempt to modify the same record.
9. Use appropriate database locking / concurrency controls where required.
10. Apply rate limiting to authentication and other sensitive / high-volume endpoints.
11. Configure appropriate PHP / web-server / database connection limits and timeouts.
12. Monitor queue health, failed jobs, slow requests, database performance, and application errors.
13. Provide graceful error handling instead of exposing Laravel / PHP / server exceptions to users.

### User-facing errors

Return user-friendly messages such as:

> The request could not be completed at this time. Please try again.

Never expose stack traces, SQL errors, file paths, credentials, or other technical information to normal users.

Automatically log the underlying technical error for administrators / developers.

Ensure failed requests do not leave partial or corrupted transactions.

Design the application so that increased users and data volume can be accommodated without requiring a complete architectural rewrite.

---

## 2. Why this matters for PSG

If 10 Shift Managers / Operations users simultaneously deploy guards, create shifts, check manpower, and update replacements, the system must **not** surface:

- `500 Server Error` with framework dump pages  
- `SQLSTATE...`  
- `Maximum execution time exceeded` as a raw PHP message  

Instead it should safely process requests, queue heavy work where appropriate, and inform the user if something genuinely cannot be completed.

---

## 3. Performance targets

Do not rely on vague “the system should be fast” language. Use measurable expectations:

| Class | Expectation |
|-------|-------------|
| **Normal interactive requests** (dashboards, lists, forms, searches) | Generally complete within **≤ 2 seconds** p95 under the expected production workload (see load scenarios below). |
| **Moderate writes** (single deploy, replacement, HR update) | Generally complete within **≤ 3 seconds** p95; use transactions + row locks so concurrent writers do not corrupt state. |
| **Heavy operations** (payroll calculate, large CSV/PDF exports, backups, bulk import) | Must be **queued or strongly throttled** so they do not block normal users or exhaust PHP workers. Users see progress / completion messaging, not a hung browser tab. |
| **Auth endpoints** | Rate-limited; lockouts after repeated failures. |

Tune targets after first load-test baseline; document any agreed SLA changes in release notes.

---

## 4. Load / stress testing before production

Required scenarios (automate where possible — see `tests/load/`):

| Scenario | Intent |
|----------|--------|
| Concurrent user testing | Many authenticated sessions active together |
| Login / load testing | Auth under burst traffic |
| Shift creation testing | Concurrent shift writes |
| Deployment concurrency testing | Multiple deploy/replace/transfer posts on overlapping guards/sites |
| Dashboard / report testing | Read-heavy dashboards + report pages |
| Bulk import / export testing | CSV import/export under load |
| Database stress testing | Connection pool and query latency under peak |
| Queue stress testing | Job backlog drain without worker collapse |
| Backup while users are active | Backup must not take the app offline |
| Peak-hour testing | Combined realistic mix of the above |

Pass criteria:

- No uncontrolled 5xx rate spike beyond an agreed threshold (e.g. &lt; 1% of requests).
- No technical error leakage to HTML responses when `APP_DEBUG=false`.
- Failed jobs are recorded and recoverable; interactive traffic remains responsive.
- Critical write paths leave no partial transactions on failure.

---

## 5. Implementation map (current codebase)

| Control | Where |
|---------|--------|
| Transactions on critical ops | `DeploymentService`, `ReplacementService`, payroll / payment services, manpower gap flows |
| Row locks | Deployments (guard/site), replacements, payments, payroll calculate |
| Queues | Payroll calculate, CSV import, accounting export, workflow mail; keep `queue:work` + `schedule:work` running |
| Client duplicate-submit guard | `resources/js/app.js` form busy state |
| Rate limits | Auth routes + named limiters for search, exports, mutations, backups |
| Friendly errors | `resources/views/errors/*` + `APP_DEBUG=false` in production |
| Health / ops | `psg:production-check`, `psg:queue-health`, `psg:backup-health`, `/up` |
| Load scripts | `tests/load/` |

---

## 6. Server capacity checklist

Document and verify for each environment:

- [ ] PHP-FPM / Apache `MaxRequestWorkers` (or equivalent) sized for expected concurrent users  
- [ ] MySQL `max_connections` &gt; (PHP workers × 1.2) with headroom for CLI/queue  
- [ ] Reasonable PHP `max_execution_time` for web; longer timeouts only for queue workers  
- [ ] `QUEUE_CONNECTION` not `sync` in production  
- [ ] `CACHE_STORE` and `SESSION_DRIVER` suitable for multi-process (database/redis)  
- [ ] `APP_DEBUG=false`; Sentry (or equivalent) DSN configured when available  

See also [DEPLOY.md](../DEPLOY.md) and [ops-queue-scheduler.md](./ops-queue-scheduler.md).
