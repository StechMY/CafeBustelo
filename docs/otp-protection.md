# OTP entry-point protection

## Scope

Web and App registration, bank verification and password-change sends require a fresh
Cloudflare Turnstile token. The server checks success, expected action and
hostname. Missing setup, provider outages and invalid/reused tokens block sending.

**Breaking change: all three App OTP send endpoints require `turnstile_token`.**
There is no exemption by version, User-Agent or client header. Coordinate the
backend deployment with the updated App; old clients cannot send OTP after deployment.
The server-hosted `/otp/challenge/{purpose}` page supplies tokens through the
`OtpChallenge` JavaScript channel; it never sends SMS itself. See the
[Flutter Turnstile implementation handoff](flutter-turnstile-handoff.md).

All six endpoints share the existing three-requests-per-minute limit by IP.
Authenticated requests also share a three-requests-per-minute account limit.
Changing phone, purpose or Web/API endpoint does not reset these keys. There are
**no new hourly, daily or global quotas**.

## Deployment requirements

Create a managed Turnstile widget for the Web hostnames and set server variables:

```dotenv
TURNSTILE_SITE_KEY=your-public-site-key
TURNSTILE_SECRET_KEY=your-private-secret
TURNSTILE_HOSTNAMES=your-web-hostname.example
TRUSTED_PROXIES=
```

- Configure matching hostnames at Cloudflare, comma-separated without scheme/path.
  An absent `TURNSTILE_HOSTNAMES` defaults to APP_URL's host; an explicitly empty
  value blocks sending. Only the site key is public. Set these before deployment:
  **missing Turnstile configuration blocks Web OTP sends**.
- The old trust-all proxy setting is removed. `TRUSTED_PROXIES` must contain the
  actual proxy IPs/CIDRs if behind Cloudflare/a load balancer. Direct deployments
  leave it empty. Never use `*`, `0.0.0.0/0` or `REMOTE_ADDR`. Configure before
  rollout or all users behind a proxy may share its IP limit. Restrict origin
  access to intended proxies and sanitize forwarded headers. This global setting
  also affects forwarded host/protocol: verify HTTPS and redirects after deploy.
- Use shared database cache (`cache` and `cache_locks` tables) or Redis across all
  workers. Atomic locks/add are required. Do not use array/null stores or isolated
  per-server file stores in production. Cache failures block sends. Clearing cache
  resets replay, cooldown and rate-limit state.
- Rebuild config/Blade caches and restart long-lived workers through the normal
  deployment workflow. No new migration is needed. Test against mocked SMS, not
  bulk requests to the paid account.

## Request behavior

Web and App sends include `turnstile_token`. Widget actions are `otp_register`,
`otp_bank_account` and `otp_change_password`. Missing/invalid tokens produce 422
validation errors on `turnstile_token`, or a form redirect with errors. AJAX sends
clear/reset the widget after every attempt, including failure/timeout. A fresh
token is required next time. App requests receive the existing JSON error envelope.

Numbers normalize to `84` plus nine mobile digits starting with 3, 5, 7, 8 or 9.
Format validation does not prove a phone exists. Password OTP uses the logged-in
account phone, ignoring a supplied destination. Bank verification preserves the
existing flow: an authenticated user without an active bank account may verify
another contact phone. Registration still rejects an existing user phone.

One phone shares a cooldown across purposes/formats (default 60 seconds). A lock
prevents simultaneous sends; a reservation is written before contacting AboSEND.
Rejection or uncertain timeout retains cooldown, preventing immediate retries.
Only confirmed acceptance creates an OTP; a timeout is not proof of no delivery.
`SMS_ENABLED=false` now makes OtpService reject sending without creating a fake
successful OTP. The low-level SmsClient development stub cannot bypass this.

## Audit and investigation

`storage/logs/otp-YYYY-MM-DD.log` retains 30 days of `otp.send` events: timestamp,
generated request ID, route, purpose, account ID, resolved client IP, direct peer
IP, truncated User-Agent, HMAC phone hash, last four digits, HTTP status, result
and accepted provider UID. No request body, challenge token, provider secret,
plaintext OTP or full phone is logged. Restrict access and preserve incident logs.

Results cover challenge rejection/unavailability, invalid destination, cooldown,
concurrent sends, throttling, disabled sending, provider rejection/uncertainty,
accepted-but-not-persisted and submitted. Submission is not final delivery or
billing: correlate UID/time with AboSEND. An unrelated provider submission IP
requires a separate credential investigation.

Tests: `tests/Feature/OtpProtectionTest.php` and `tests/Unit/Services/Sms` use
in-memory SQLite and fake HTTP, covering mandatory Web/App challenges, invalid/replayed
challenges, phone validation, destination binding, shared limits, trusted proxy
handling, lock ownership and timeout cooldown.

`node --test tests/js/otp-send.test.mjs` checks browser-handler behavior with a
mocked widget/network: missing/expired challenges, token submission/reset,
validation/network failures, double clicks and the password form guard.

Reference: https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
