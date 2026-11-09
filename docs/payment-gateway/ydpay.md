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

Pending withdrawals have a separate **YDPAY · Nh** approval action. Existing QC168/manual approvals retain their existing behavior. Approval reserves the full original amount and snapshots the selected delay; dispatch pays `net_amount` only. Successful verified payout consumes the full original reserve once through `WithdrawService::finalizePayout`, without adding another debit. Provider fees are merchant costs. Gateway 22 uses its own numeric bank codes (e.g. Vietcombank `44`, not QC168 `970436`). Supported mappings are restricted to banks already configured in this project. Gateway 21 uses a different bank/contact-data contract and is deliberately blocked.

Web recharge exposes YDPAY when enabled. API callers submit `provider=ydpay` to the existing `/api/v1/recharge/gateway` endpoint; omitted provider continues using QC168. Recharge listing exposes both providers separately.

The scheduler runs these independently every minute:

```shell
php artisan ydpay:process-orders deposit
php artisan ydpay:process-orders withdraw
```

Each run is bounded. Submission errors retain the original order and reserve and require querying that order; they never trigger automatic resubmission or refund. Admin retry requires a freshly verified final failure and creates a new attempt/order ID. Admin cancel uses the existing locked, ledger-guarded cancellation path. Callbacks after cancellation cannot regress it; a late successful payout goes to `refund_review`.

## Verification

Requests use form encoding, uppercase MD5 signatures and VND × 100 integer strings. Callbacks are POST with query parameters and a plain `success` acknowledgment only after verification and processing. Callback string whitespace must survive middleware. Payout callbacks intentionally do not require currency or merchant ID, which YDPAY does not send; identity, gateway and amount are checked against the stored attempt. Deposit status 2/3 represents one payment, never two credits.

Automated checks use mocked HTTP and isolated SQLite. No live order or production repair command is part of installation. Before enabling, configure the formal key and deployment proxy URL, verify the production egress IP/whitelist and callback domain, resolve the portal payout discrepancy, and perform a separately authorized controlled live payment test.
