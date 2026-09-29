# 2026-09-29 withdrawal scheduler recovery

## Confirmed production state

Read-only Forge checks at approximately 21:12–21:18 (UTC+7) found:

- Withdrawal `4836`: approved, `waiting_release`, net payout VND 186,000, zero payout attempts and no merchant order ID. Release time was September 29 at 18:57:16 (UTC+7).
- Cron is active, enabled at boot, and `/etc/crontab` runs this site's `schedule:run` every minute using PHP 8.4. No withdrawal dispatch or inquiry process was running when checked.
- Database cache contains the dispatch scheduler lock below, created September 29 at 14:29:02 (UTC+7), before the server restarted at approximately 14:35. Its old 24-hour expiry is September 30 at 14:29:02 (UTC+7).

The withdrawal has not reached the provider through this application's payout flow. There is no need to create another withdrawal or refund and deduct the balance again.

## Restore the existing scheduler

An operator should execute the following **once in this site's production database**, after rechecking that no dispatch process is running. Releasing this lock lets cron resume real payouts for all approved, due `waiting_release` withdrawals, including `4836`.

```sql
DELETE FROM cache_locks
WHERE `key` = 'cafebustelo_cache_framework/schedule-8493ee3dbaeff0f8be4b04658c26cebe0a25f5f0'
  AND expiration = 1790753342;
```

The expiry condition deliberately matches only the observed old lock. Expect one affected row. If zero rows are affected, inspect the current state; do not broaden the deletion or clear the application cache. This does not modify withdrawal records, wallet balances, OTP limits, or other scheduler locks.

Wait for the next minute's cron run, then verify:

```sql
SELECT id, status, payout_status, gateway_current_merchant_order_id,
       gateway_payment_status, gateway_submitted_at, gateway_fail_reason
FROM withdraw_requests
WHERE id = 4836;

SELECT id, attempt_no, merchant_order_id, gateway_status, submitted_at,
       completed_at, failure_reason
FROM withdrawal_payout_attempts
WHERE withdraw_request_id = 4836
ORDER BY attempt_no;
```

One new attempt means dispatch resumed; it does not by itself mean the provider completed payment. If submission is uncertain, reconcile the existing merchant order ID. Do not reset it to `waiting_release` or create another payout blindly.

## Prevent recurrence

Deploy the changes in `routes/console.php` and the two withdrawal console commands. New dispatch and inquiry overlap locks expire after five minutes. Each command stops starting new orders after 45 seconds and finishes the current provider request. Remaining work stays eligible for the next scheduled run. No migration is required, and the existing per-withdrawal database claim still prevents repeat submission.

Deployment alone does not alter the existing old lock's expiry; the one-time recovery above handles it separately.

Validation: all three new regression tests pass (34 assertions), including database-backed stale locks and continuation without duplicate dispatch. The full withdrawal suite reports 58 passes, one existing SQLite migration rollback error, and one MySQL-only concurrency test skipped. The rollback error also reproduces with the unchanged HEAD test. Formatting and diff checks pass.
