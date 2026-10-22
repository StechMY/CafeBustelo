# Withdrawal reconciliation

In **Withdraw Requests → Filters**, select **Dashboard total withdrawals (exclude virtual users)** to match the dashboard's default total-withdrawal population. The logged-in administrator's existing downline restrictions still apply. This includes system-paid withdrawals and the legacy approved/manual records counted by `WithdrawRequest::paid()`.

Clear other filters and search when comparing the all-time total. The summary includes every matching page:

- **Total requested amount** matches the dashboard's gross total withdrawals.
- **Total fees** is the sum of the stored fees.
- **Total net payout** is the sum of the stored net amounts. Use this for net reconciliation instead of applying a fixed percentage to the gross total.

For a day or date range, also set **Withdrawal statistics date**. It uses `gateway_completed_at`, falling back to `reviewed_at` when no gateway completion timestamp exists, matching the dashboard's daily withdrawal calculation. Dates use the application's configured timezone. An empty start or end date leaves that side unbounded.

**Approval date** is a separate filter and can differ from the statistics date for gateway payouts. Use it only when investigating approvals; combining both date filters intersects their conditions.

These filters query current records. They do not restore historical status, historical administrator downlines, or yesterday's dashboard snapshot. A system-paid status is not independent evidence of a bank transfer. To explain changes in historical totals, compare the original reconciliation record with payment events, database backups, and bank/provider evidence as needed.
