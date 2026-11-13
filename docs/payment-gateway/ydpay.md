# YDPAY VND integration

Protocol source: https://merchant.ydpay.vip/api-docs/ (checked 2026-11-09).

## Merchant configuration

The merchant portal shows API merchant ID **502002001**. `cafebustelo` is the login name, not the API `mchId`.
The payment-gateway screen shows deposit gateway **20**; the payout-gateway screen shows **22** enabled.
The separate integration-info screen says no payout gateway is enabled: resolve this discrepancy with YDPAY before live payout testing.

Set these server environment variables, then rebuild Laravel's configuration cache through the normal deployment process:

```dotenv
THIRD_PARTY_OUTBOUND_PROXY=http://100.123.147.68:3128
YDPAY_ENABLED=false
YDPAY_BASE_URL=https://gateway.ydpay.vip
YDPAY_MERCHANT_ID=502002001
YDPAY_SECRET=
YDPAY_DEPOSIT_GATEWAY_ID=20
YDPAY_WITHDRAW_GATEWAY_ID=22
YDPAY_DEPOSIT_ENABLED=false
YDPAY_WITHDRAW_ENABLED=false
YDPAY_CALLBACK_IPS=38.22.88.53
```

Store the formal API key in server secrets or `.env`; never commit it. The public sandbox uses a different key and protocol and is not a production endpoint replacement. Login/payment passwords and the authenticator are managed by the merchant account owner.

Set production `APP_URL=https://cafebustl.online`; it determines both callbacks (independently of incoming Host headers):

- `https://cafebustl.online/payment-gateway/ydpay/deposit/callback`
- `https://cafebustl.online/payment-gateway/ydpay/withdraw/callback`

Provide the production server's **outbound** public IP to YDPAY for its API whitelist; do not use a developer workstation IP. Allow inbound callbacks from `38.22.88.53` at the firewall/reverse proxy. Configure trusted proxies correctly so Laravel sees the real callback IP. Signature verification is mandatory even for allowed IPs.

YDPay follows GameCenter's explicit forward-proxy pattern: `THIRD_PARTY_OUTBOUND_PROXY=http://<tailscale-proxy-address>:<port>`. All YDPay create/query requests use this proxy with TLS verification enabled, redirects disabled and no direct fallback. Missing or malformed configuration blocks new deposits and withdrawal approvals. An unreachable proxy leaves ambiguous existing orders reserved for reconciliation; it does not refund or resubmit them.

The owner supplied Tailscale address `100.123.147.68`. A read-only HTTPS IP-echo request from this workstation through `http://100.123.147.68:3128` returned HTTP 200 and public IP **206.189.178.28** on 2026-11-09. Repeat this explicit-proxy check from the deployed application server/PHP container to verify tailnet reachability there. A direct `curl https://api.ipify.org` tests the system route, not this application's configured proxy.

GameCenter's local proxy `http://100.81.90.15:3128` has a different recorded egress (`159.223.185.70`); it must not be copied into CafeBustelo's deployment.

This setting applies only to the new YDPay integration; existing QC168 transport is unchanged. Incoming `TRUSTED_PROXIES` controls forwarded client IP interpretation only. Callbacks do not travel through the outbound proxy.

## Administration

Dashboard → system settings → **YDPAY** opens deposit and withdrawal toggles and the withdrawal delay (1–720 hours, default 24). It uses the existing `ManagePaymentGateway:Settings` permission. Every save records an immutable event; changes do not alter existing withdrawal release times. Server `YDPAY_ENABLED` remains the master switch, and complete credentials and outbound proxy configuration are also required.

When `YDPAY_ENABLED=true`, the admin topbar shows **YDPAY Available Payout Balance** from the signed, read-only `/api/agentpay/query_balance` response field `availableAgentpayBalance`, converted from minor units (÷100). This lookup works while the deposit/withdrawal switches remain off. It uses the configured proxy, a five-minute cache, a refresh lock and a ten-second manual refresh cooldown. Failed lookups retain the last successful balance for up to one day, visibly marked stale; they never substitute QC168 or the total `agentpayBalance`. With YDPAY disabled, the topbar continues showing QC168.

Pending withdrawals have separate **YDPAY · payout in Nh** and **YDPAY · send payout now** actions on both list and detail pages. QC168 settings and payment action controls are hidden; manual approval is labelled as using no gateway. Scheduled and instant actions require their respective existing permissions and the YDPAY withdrawal switch. Approval keeps the existing full reserve locked and snapshots the selected delay (zero for instant); new attempts round `net_amount` to the nearest whole VND, with .50 rounding up. The platform pays any upward difference; rounding down reduces the payout by less than 0.50 VND. Successful verified payout consumes the full original reserve once through `WithdrawService::finalizePayout`, without adding another debit. Provider fees are merchant costs. Gateway 22 uses its own numeric bank codes (e.g. Vietcombank `44`, not QC168 `970436`). Supported mappings are restricted to banks already configured in this project. Gateway 21 uses a different bank/contact-data contract and is deliberately blocked.

Web recharge exposes YDPAY when enabled. API callers submit `provider=ydpay` to the existing `/api/v1/recharge/gateway` endpoint; omitted provider continues using QC168 for backward compatibility. QC168 is no longer offered in web/API deposit method selection. Historical records and their provider filters remain available, and legacy services, callbacks and scheduled reconciliation are unchanged.

The scheduler runs these independently every minute:

```shell
php artisan ydpay:process-orders deposit
php artisan ydpay:process-orders withdraw
```

Each run is bounded. Submission errors retain the original order and reserve and require querying that order; they never trigger automatic resubmission or refund. Admin retry requires a freshly verified final failure and creates a new attempt/order ID. Admin cancel uses the existing locked, ledger-guarded cancellation path. Callbacks after cancellation cannot regress it; a late successful payout goes to `refund_review`.

### Whole-VND payouts and confirmed non-acceptance recovery

YDPay support confirmed on 2026-11-10 that VND payouts cannot contain fractional dong. Per the revised business rule, all new instant, scheduled and retry attempts use **round half up**, calculated with integer arithmetic: `469377.49 → 469377.00`, `469377.50 → 469378.00`, `469377.51 → 469378.00`. Wire amounts still use the documented VND × 100 units (`46937800`). Each new attempt snapshots its actual amount, original net amount, `rounding_policy=half_up_vnd` and signed `rounding_adjustment` (actual minus original net, e.g. `-0.49`, `0.50`); details show these separately. Positive adjustments are platform costs; negative adjustments reduce the actual payout without changing the original wallet reserve or fee. A rounded amount below 1 VND is blocked before attempt creation/submission. Existing request amounts, fees, reserves and old attempt amounts/order IDs are not rewritten, including previous `ceil_vnd_merchant_cost` / `rounding_subsidy` snapshots. No new environment variable or migration is required.

For historical `create_pending` attempts with no acceptance evidence, use **YDPAY · Confirm not accepted and retry** on the withdrawal list/detail:

1. Obtain YDPay's explicit confirmation for the **original merchant order number** that it was not accepted, debited or paid and cannot execute later. An unsigned “order not found” query alone is insufficient.
2. An administrator with both `RetryGateway:Withdrawal` and `CancelGateway:Withdrawal` permissions enters the support reference, confirmation time/content, reason and acknowledgement. The withdrawal switch must be enabled.
3. The backend queries that original order again. Only HTTP 200 / `retCode=9999` / exact `retMsg=代付订单不存在`, together with the human confirmation, permits this exceptional recovery. Any successful query follows normal status processing and blocks this action; timeouts, other errors and invalid evidence cannot create a replacement.
4. A transaction locks the withdrawal and latest attempt, rechecks the original order and eligibility, records an immutable `ydpay.payout.not_accepted_confirmed` event and creates one new attempt/order number. The old provider status, amount and response remain intact. No wallet entry or reserve change occurs on retry. Repeated/stale forms cannot create another attempt.
5. The new order is submitted once. If its response is uncertain, synchronize that **new** order and obtain separate provider confirmation before any further recovery; do not reuse the old confirmation. There is no automatic resubmission.

Old-attempt paid responses, or acceptance activity after a non-acceptance confirmation, enter sticky financial review. They never consume the reserve a second time or silently finalize the replacement. Local row locks and order checks prevent overlapping operator actions from issuing two replacement attempts; they cannot prevent a provider from paying an old order contrary to its explicit confirmation, which must be investigated as a financial incident. No historical production retries run during deployment.

### Bank account boundary whitespace

New YDPay attempts trim leading/trailing whitespace (spaces, tabs, line breaks, Unicode separators such as non-breaking/full-width spaces, and copied BOM characters) from the withdrawal's account-number snapshot. This applies to instant/scheduled submissions, verified-failure retries and confirmed-non-acceptance retries. Leading zeroes and internal characters are preserved. The cleaned value must be nonempty and at most 64 characters, and is saved in the new attempt before signing/submission. The new attempt and its submission/retry audit event record `bank_account_no_trimmed`.

Original withdrawal snapshots, user bank records and previously submitted attempt account numbers are not rewritten. Cleanup does not authorize a retry: existing fresh-query, confirmation, row-lock and finalization guards still apply. Deployment does not resubmit historical orders or alter wallets. A pending attempt with the old whitespace error still requires the existing confirmed-non-acceptance workflow before a new, cleaned attempt can be submitted. No migration or additional configuration is needed.

## Verification

Failed create/query requests retain sanitized diagnostics in immutable `ydpay.request.error` events, linked to the original recharge or payout attempt: endpoint, HTTP status, `retCode`, `retMsg`, signature verification and timestamp. No raw error body, request signature, API secret or proxy credential is stored. Transport errors use a safe local explanation without logging the underlying exception. Balance lookups retain their existing cache/log behavior.

Admin lists show the latest order error; order details show both the original submission error and latest request error. Subsequent query errors do not erase the original submission diagnostic, and a retry starts fresh diagnostics for its new attempt while immutable events preserve all previous attempts. Diagnostics alone never change payment status, enable retry/cancellation, credit wallets or release reserves. Missing/unverified signatures are explicitly labelled; a provider error such as `9999` is not evidence of a final payment failure. Historical errors that were never stored cannot be reconstructed; a later sync can only record its current response.

YDPay status numbers are interpreted separately from QC168:

| Business | Provider status | Local meaning / financial action |
| --- | --- | --- |
| Deposit | 0 / 1 | Initialized / awaiting payment; no credit |
| Deposit | 2 / 3 | Paid / paid with notification acknowledged; one credit total |
| Deposit | -1 / -2 | Failed / closed; no credit |
| Deposit | 4 | Refund reported; sticky financial review, no automatic credit or clawback |
| Withdrawal | 0 / 1 / 4 / 5 | Pending / processing / provider review / merchant confirmation; reserve retained |
| Withdrawal | 2 | Paid; consume the original reserve once |
| Withdrawal | 3 | Failed; reserve retained until an authorized cancel or retry |
| Withdrawal | 6 | Refunded; financial review, no automatic refund |

Creation success is never payment evidence. Deposit callbacks accept only 2, -1 and -2; a successful callback also requires `income` to equal the requested minor-unit amount. Deposit queries accept the full documented status set and require `retCode=0` and `currency=VND`. Payout callbacks accept 2/3; queries accept 0–6. All paths verify signatures, snapshotted gateway/amount and order identity. Sync checks the returned order against the order requested, and never falls back to QC168. Old processing messages cannot regress verified failures; payment after a cancellation goes to financial review. The API exposes `payment_confirmed` and `requires_review` separately from historical local approval.

Only unresolved deposits are polled automatically. An already-credited deposit's later refund can be detected by an explicit admin sync; refunds do not automatically reverse wallet entries. The provider's documented deposit callback set does not include refund status 4.

### Unpaid deposit expiry

Dashboard → YDPAY → unpaid deposit expiry defaults to **60 minutes**, configurable from 1 to 1440. New orders snapshot their deadline in `gateway_meta.deposit_expires_at`; subsequent settings changes do not move that deadline. Existing orders without a snapshot use `created_at + 60 minutes`. No schema migration or additional environment variable is required.

The existing minute scheduler queries the original YDPAY order. Only a verified status 0 or 1 received at or after the deadline marks `gateway_payment_status=expired`, records `deposit_expired_at`, and writes one immutable `ydpay.deposit.expired` event. Query errors, invalid signatures, mismatched evidence, sandbox/review states and paid orders never trigger expiry. Under load, expiry happens when the bounded reconciliation batch next reaches the order; the scheduler and `YDPAY_ENABLED` must be active.

This is **local expiry**, not a YDPAY close request or proof that the upstream cashier cannot accept payment. Admin/customer screens display “Expired locally (unpaid)”; the API returns `gateway_status=expired` and no payment URL. The original provider status remains in `gateway_meta.ydpay_status`. The financial approval `status` remains `pending` so polling and verified late payments still work. Repeated unpaid responses cannot reopen an expired order. A valid late paid callback/query uses the normal exactly-once credit path; expiry itself never changes a wallet or rejects a paid order.

### Formal endpoint returning an upstream sandbox

On 2026-11-09, production merchant 502002001 / deposit gateway 20 used the formal `https://gateway.ydpay.vip` endpoint, but its returned FluxPay checkout displayed **SANDBOX API TEST** and explicitly stated that no real payment or funds movement would occur. The saved create response had `payAction=GET`, `payMethod=formJump` and no `sandbox` flag; subsequent query responses likewise had no sandbox flag. The local deposits examined remained pending and uncredited.

This requires YDPAY to confirm and correct the upstream environment for gateway 20. A formal API hostname and valid signature alone cannot distinguish an unmarked simulated upstream result. Do not use a simulated success callback as evidence of a real payment. Explicit sandbox responses/URLs are quarantined, and the documented sandbox base URL is blocked, but those checks cannot detect an unmarked upstream sandbox. Do not treat live acceptance as verified until YDPAY confirms the real channel and a controlled real payment is reconciled.

Requests use form encoding, uppercase MD5 signatures and VND × 100 integer strings. Callbacks are POST with query parameters and a plain `success` acknowledgment only after verification and processing. Callback string whitespace must survive middleware. Payout callbacks intentionally do not require currency or merchant ID, which YDPAY does not send; identity, gateway and amount are checked against the stored attempt. Deposit status 2/3 represents one payment, never two credits.

Automated checks use mocked HTTP and isolated SQLite. No live order or production repair command is part of installation. Before enabling, configure the formal key and deployment proxy URL, verify the production egress IP/whitelist and callback domain, resolve the portal payout discrepancy, and perform a separately authorized controlled live payment test.
